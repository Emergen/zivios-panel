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

class Zivios_Package_Report extends Zend_Db_Table
{
    protected $_name = 'package_reports';
    protected $_id = 'id';

    public $id,$emscomputerdn,$rtime;

    public function __construct()
    {
        parent::__construct();
    }

    public function loadReport($emscomputerdn)
    {
        $sql = "select * from package_reports where emscomputerdn=:emsdn";
        $row = $this->_db->fetchRow($sql,array("emsdn" => $emscomputerdn));
        return $this->loadRow($row);
    }

    public function loadRow($row)
    {
        if (sizeof($row) > 0) {
            $this->id = $row['id'];
            $this->emscomputerdn = $row['emscomputerdn'];
            $this->rtime = new Zend_Date($rows['rtime'],Zend_Date::ISO_8601);
            return true;
        } else {
            return false;
        }
    }

    public function setData($emscomputerdn)
    {
        $this->emscomputerdn = $emscomputerdn;

    }

    public function write()
    {
        /**
         * check if one exists already, overwrite everything if it does
         */

        $test = new Zivios_Package_Report();
        if ($test->loadReport($this->emscomputerdn)) {
            //Erase!
            $test->delete();

        }


        $data = array("emscomputerdn" => $this->emscomputerdn);

        $this->id = $this->insert($data);
    }

    public function getAllItems()
    {
        $item = new Zivios_Package_ReportItem();
        return $item->getAll($this->id);
    }

    public function delete()
    {
        /**
         * Delete sub reports first
         */
        $subreport = new Zivios_Package_ReportItem();
        $subreport->deleteAll($this->id);
        $this->delete("id=".$this->id);

    }
}
