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

class Zivios_Package_ReportItem extends Zend_Db_Table
{
    protected $_name = "package_report_items";
    protected $_id = "id";

    public $id,$package_report_id,$name,$action,$current_version,$new_version,$channel_id;

    public function __construct()
    {
        parent::__construct();
    }

    public function getAll($report_id)
    {
        $rows = $this->_db->fetchAll("select * from package_report_items where package_report_id=".$report_id);
        $allitems = array();
        for ($i=0;$i<sizeof($rows);$i++)
        {
            $itemiter = new Zivios_Package_ReportItem();
            Zivios_Util::autoPopulateFromRows($itemiter,$rows[$i]);
            $allitems[$i] = $itemiter;

        }
        return $allitems;
    }

    public function setData($package_report_id,$name,$action,$current_version,$new_version,$channel_id)
    {
        $this->package_report_id = $package_report_id;
        $this->name = $name;
        $this->action = $action;
        $this->current_version = $current_version;
        $this->new_version = $new_version;
        $this->channel_id = $channel_id;
    }

    public function write()
    {
        $data = array("package_report_id" => $this->package_report_id,
                     "name" => $this->name,
                     "action" => $this->action,
                     "current_version" => $this->current_version,
                     "new_version" => $this->new_version,
                     "channel_id" => $this->channel_id);

         $this->id = $this->insert($data);
    }

    public function deleteAll($reportid)
    {
        $this->delete("package_report_id=".$reportid);
    }
}
