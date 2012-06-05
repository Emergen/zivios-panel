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

class Zivios_Notification extends Zend_Db_Table
{
    public $description;

    protected $_name = 'notifications';
    protected $_primary = 'id';

    const TYPE_ERROR = 'ERROR';
    const TYPE_NOTICE = 'NOTICE';
    const TYPE_ALERT = 'ALERT';

    private $id;
    public $long_desc,$short_desc,$type,$refresh_dn,$click_action,
            $click_args_array,$isread,$publish_time,$read_timestamp,$userdn,
            $processed;

    public function __construct($id=0)
    {
        parent::__construct();
        if ($id != 0) {
            $this->read($id);
        }
        $this->processed = 0;
        $this->read = 0;
    }

    public function read($id)
    {
         /*$rows = $this->_db->fetchRow('select short_desc,long_desc,type,refresh_dn,click_action,' .
                                      'click_args_array,isread,UNIX_TIMESTAMP(publish_time) as pub_time,' .
                                      'UNIX_TIMESTAMP(read_timestamp) as read_time from notifications ' .
                                      'where id='.$id);
         */
         $rows = $this->_db->fetchRow('select * from notifications where id=' . $id);
         $this->id = $id;
         $this->short_desc = $rows['short_desc'];
         $this->long_desc = $rows['long_desc'];
         $this->type = $rows['type'];
         $this->refresh_dn = $rows['refresh_dn'];
         $this->click_action = $rows['click_action'];
         $this->click_args_array = unserialize($rows['click_args_array']);
         $this->read = $rows['isread'];

         $this->publish_time = new Zend_Date($rows['publish_time'],Zend_Date::ISO_8601);
         $this->read_timestamp = new Zend_Date($rows['read_timestamp'],Zend_Date::ISO_8601);
         $this->processed = $rows['processed'];
    }

    public function getLatestNofications($limit=5,$includeread=0,$userdn=null)
    {
        if ($userdn == null) {
            $userinfo = Zivios_Ldap_Engine::getUserCreds();
            $userdn = $userinfo['dn'];
        }

        $sql = 'select id from notifications where userdn=\'' . $userdn . '\'';
        if (!$includeread)
            $sql .= ' and isread=0';

        $sql .= ' order by publish_time limit '.$limit;
        Zivios_Log::debug("Getting notifications, sql is " . $sql);
        $rows = $this->_db->fetchAll($sql);
        $notifications = array();
        foreach ($rows as $row)
            $notifications = new Zivios_Notification($rows['id']);

        return $notfications;
    }
    public function setData($short_desc,$type,$long_desc=null,$refresh_dn=null,$click_action=null,
                               $click_args_array=null)
    {
        $userinfo = Zivios_Ldap_Engine::getUserCreds();
        $userdn = $userinfo['dn'];

        $this->short_desc = $short_desc;
        $this->type = $type;
        $this->refresh_dn = $refresh_dn;
        $this->userdn = $userdn;
        $this->long_desc = $long_desc;
        $this->click_action = $click_action;
        $this->click_args_array = $click_args_array;
    }

    public function write()
    {

        $data = array('short_desc' => $this->short_desc,
                         'long_desc' => $this->long_desc,
                         'type' => $this->type,
                         'refresh_dn' => $this->refresh_dn,
                         'click_action' => $this->click_action,
                         'click_args_array' => serialize($this->click_args_array),
                         'userdn' => $this->userdn);

        $this->id = $this->insert($data);
    }

    public function markProcessed()
    {
        $data = array('processed',1);
        $this->update($data,"id = ".$this->id);
        $this->processed = 1;
    }
    public function markRead()
    {
        $this->read_timestamp = Zend_Date::now();
        $this->isread = 1;
        $data = array('isread' => 1,
                      'read_timestamp' => $this->read_timestamp->get(Zend_Date::ISO_8601));
        $this->update($data,"id = ".$this->id);
    }



}
