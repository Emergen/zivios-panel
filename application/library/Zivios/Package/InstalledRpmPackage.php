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
 * @package     mod_package
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_Package_InstalledRpmPackage extends Zend_Db_Table
{
    protected $_name = 'inst_rpm_packages';
    protected $_id = 'id';

    const STATUS_INSTALLED = 'INSTALLED';
    const STATUS_ERROR = 'ERROR';
    const STATUS_REMOVED = 'REMOVED';
    const STATUS_UNKNOWN = 'UNKNOWN';

    public $id,$name,$short_desc,$long_desc,$emscomputerdn,$version,$status,$replaces,
            $conflicts,$depends;

    public function __construct($id=null)
    {
        parent::__construct();
        $this->packageclass = get_class($this);
        if ($id != null) {
            $this->id = $id;
            $this->revive($id);
        }

    }

    public function getByName($name,$emscomputerdn)
    {
        $sql = "select * from inst_rpm_packages where name=:name and emscomputerdn=:emsdn";
        $rows = $this->_db->fetchRow($sql,array("name" => $name,
                                                "emsdn" => $emscomputerdn));

        if (is_array($rows) && sizeof($rows) >0) {
            Zivios_Util::autoPopulateFromRows($this,$rows);
            return $this;
        } else
            return null;


    }

    public function isInstalled($name,$emscomputerdn)
    {
        return ($this->getByName($name,$emscomputerdn) != null);
    }


    public function getAllForComputer($computerdn)
    {
        $sql = "select id from inst_rpm_packages where emscomputerdn='".$computerdn."' order by name";
        Zivios_Log::debug("Computer package finding sql : $sql");
        $cols = $this->_db->fetchCol($sql);

        $packages = array();
        foreach ($cols as $id) {
            $packages[] = new $this->packageclass($id);

        }
        return $packages;
    }

    public function searchAllForComputer($computerdn,$name,$limit=100000,$offset=0)
    {
        if ($name != "" && $name != null)
        {
            $pnamesql = "and name LIKE '%".$name."%'";
        } else 
            $pnamesql = "";
        
        $sql = "select id from inst_rpm_packages where emscomputerdn='".$computerdn."' "
        . $pnamesql . "  order by name limit $limit offset $offset" ;
        
        Zivios_Log::debug("Search for all packages : ".$sql);
        $cols = $this->_db->fetchCol($sql);

        $packages = array();
        foreach ($cols as $id) {
            $packages[] = new $this->packageclass($id);

        }
        return $packages;
    }
    
    private function revive($id)
    {
        $sql = "select * from inst_rpm_packages where id=".$id;
        $rows = $this->_db->fetchRow($sql);

        Zivios_Util::autoPopulateFromRows($this,$rows);
    }

    public function setData($name,$short_desc,$long_desc,$version,$release,$status,$conflicts,$replaces,$depends)
    {
        $this->name = $name;
        $this->short_desc = $short_desc;
        $this->long_desc = $long_desc;
        $this->release = $release;
        $this->version = str_replace('~','#',$version);
        $this->status = $status;
        $this->conflicts = str_replace('~','#',$conflicts);
        $this->replaces = str_replace('~','#',$replaces);
        $this->depends  = str_replace('~','#',$depends);
    }


    public function associateWithComputer($emscomputerdn)
    {
        $this->emscomputerdn = $emscomputerdn;
    }

    public function write()
    {
        $data = array('name' => $this->name,
                      'short_desc' => $this->short_desc,
                      'long_desc' => $this->long_desc,
                      'version' => $this->version,
                      'status' => $this->status,
                      'conflicts' => $this->conflicts,
                      'replaces' => $this->replaces,
                      'depends' => $this->depends,
                      'release' => $this->release,
                      'emscomputerdn' => $this->emscomputerdn);

        try {
            $this->id = $this->insert($data);
        } catch (Zend_Db_Statement_Exception $e)
        {
            Zivios_Log::debug("Package  ".$this->name." already exists on host, updating!");
            $where = 'name = "'.$this->name.'" and emscomputerdn = "'.$this->emscomputerdn.'"';
            $this->update($data,$where);
        }
    }

    public function deleteAllForComputer($emscomputerdn)
    {
        Zivios_Log::debug("Deleting all package cache for computer : " . $emscomputerdn);

        if ($emscomputerdn != null && $emscomputerdn != "")
            $this->delete('emscomputerdn="'.$emscomputerdn.'"');
        else
            Zivios_Log::error("blank emscomputerdn passed, would cause a complete truncation of package database!");
    }


}

