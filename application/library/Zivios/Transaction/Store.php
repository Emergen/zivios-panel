<?php
/**
 * Copyright (c) 2008 Zivios, LLC.
 *
 * This file is part of Zivios.
 *
 * Zivios is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zivios is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Zivios.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     Transaction
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_Transaction_Store extends Zend_Db_Table
{
    protected $_name = 'trans_store';
    protected $_primary = 'trans_group_id';
    public $store,$new,$status,$tgroup;

    const STATUS_INITIALIZE = 1;
    const STATUS_READY = 2;

    public function __construct(Zivios_Transaction_Group $tgroup)
    {
        parent::__construct();
        $this->tgroup = $tgroup;

        if ($tgroup->isNew()) {
            $this->store = array();
            $this->new = 1;
            $this->status = self::STATUS_INITIALIZE;
        } else {
            $this->new = 0;
            $this->revive();
        }
    }
    
    public function __destruct()
    {
        $this->closeObjects();
        unset($this->store);
        unset($this->tgroup);
        
    }
    
    public function closeObjects()
    {
        foreach ($this->store as $key => $obj) {
            if (method_exists($obj,'close')) {
                $obj->close();
            }
            if (is_object($obj)) {
                $obj->__destruct();
                unset($obj);
            }
        }
    }

    public function put($itemid,$name,$object)
    {
        Zivios_Log::debug("Adding Object " . $name . " from itemid: " . $itemid );
        if ($this->status == self::STATUS_INITIALIZE)
            $this->store[$itemid][$name] = $object;
        else throw new Zivios_Exception("Transaction Object Store already comitted, no further additions allowed");
    }

    public function get($itemid,$name)
    {
        return $this->store[$itemid][$name];
    }

    public function write()
    {
        $start = microtime(true);
        $data = array ('objstore' => serialize($this->store),
                       'status' => $this->status,
                       'trans_group_id' => $this->tgroup->getId());
        
        if ($this->new) {
            $this->insert($data);
            $this->new = 0;
        } else {
            $this->update($data,'trans_group_id='.$this->tgroup->getId());
        }
        $stop = microtime(true);
        $bt = debug_backtrace(10);
        Zivios_Log::info("Expensive update called from function: ");
        Zivios_Log::info($bt[2]['class']."::".$bt[2]['function']);
        Zivios_Log::info($bt[3]['class']."::".$bt[3]['function']);
        Zivios_Log::info($bt[4]['class']."::".$bt[4]['function']);
        
        Zivios_Log::info("Trans store write/update took : ".($stop-$start)."s");
    }

    public function commit()
    {
        $this->status = self::STATUS_READY;
        $this->write();
    }

    public function getArrayForItem($itemid)
    {
        return $this->store[$itemid];
    }
    
    public function revive()
    {
        $start = microtime(true);
        Zivios_Log::Debug("Reviving Transaction Group Object Store id : ".$this->id);
        $rows = $this->_db->fetchRow('select * from trans_store where trans_group_id='.$this->tgroup->getId());
        $this->store = unserialize($rows['objstore']);
        $this->status = $rows['status'];
        Zivios_Log::debug("Done Reviving object store with no errors)");
        $stop = microtime(true);
        Zivios_Log::info("Trans store Revive took : ".($stop-$start)."s");
    }
}

