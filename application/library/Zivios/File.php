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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_File extends Zend_Db_Table
{
    protected $_id = "id";
    protected $_name = "fileversions";
    
    public $filename,$version,$data,$emscomputerdn,$insertdate;
    
    public function __construct($filename,$emscomputerdn)
    {
        $this->filename = $filename;
        $this->emscomputerdn = $emscomputerdn;
        parent::__construct();
    }
    
    public function getLatestVersionNumber()
    {
        $sql = "select max(version) from fileversions where filename=:fname and emscomputerdn=:cdn";
        return $this->_db->fetchOne($sql,array('fname' => $this->filename,
                                               'cdn' => $this->emscomputerdn));
    }
    
    public function getVersion($version)
    {
        $sql = "select * from fileversion where filename=:fname and emscomputerdn=:cdn and " .
        "version = :version";
        $row = $this->_db->fetchRow($sql,array('fname' => $this->filename,
                                               'cdn' => $this->emscomputerdn,
                                               'version' => $version));
        $this->version = $version;
        $this->data = $row['data'];
        $this->insertdate = new Zend_Date($rows['insertdate'],Zend_Date::ISO_8601);
        return $this;
    }
    
    public function addNewVersion($data)
    {
        $latest = $this->getLatestVersionNumber();
        if ($latest == null || $latest == 0)
            $nextversion = 1;
        else 
            $nextversion = $latest + 1;
        
        $this->insertdate = Zend_Date::now();
        $data = array ('filename' => $this->filename,
                       'emscomputerdn' => $this->emscomputerdn,
                       'version' => $nextversion,
                       'data' => $data,
                       'insertdate' => $this->insertdate->get(Zend_Date::ISO_8601));
        
        $this->id = $this->insert($data);
        $this->version = $nextversion;
        $this->data = $data;
        Zivios_Log::debug("File : " . $this->filename . " on emscomputerdn " . $this->emscomputerdn . 
                          " backed up to revision " . $this->version);
    }
}

    
