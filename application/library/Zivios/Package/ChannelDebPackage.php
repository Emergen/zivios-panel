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

class Zivios_Package_ChannelDebPackage extends Zend_Db_Table
{
    protected $_name = 'chan_deb_packages';
    protected $_id = 'id';


    const STATUS_ERROR = 'ERROR';
    const STATUS_REMOVED = 'REMOVED';
    const STATUS_UNKNOWN = 'UNKNOWN';

    public $id,$name,$short_desc,$long_desc,$emscomputerdn,$version,$channel_id,
            $conflicts,$replaces,$depends;


    public function __construct($id=null)
    {
        parent::__construct();
        $this->packageclass = get_class($this);
        if ($id != null) {
            $this->id = $id;
            $this->revive($id);
        }

    }

    public function getMostRecentByName($name,$channelidarray)
    {
        Zivios_Log::debug($channelidarray);
        $sql = 'select * from chan_deb_packages where name="'.$name.'" and (';
        for ($i=0;$i<sizeof($channelidarray);$i++) {
            if ($i==0) {
                $sql .= 'channel_id='.$channelidarray[0];
            } else {
                $sql .= ' OR channel_id='.$channelidarray[$i];
            }
        }

        $sql .= ") order by version DESC";

        Zivios_Log::debug("Getting by name packages, sql is ".$sql);
        $rows = $this->_db->fetchAll($sql);
        if (sizeof($rows) >0) {
            Zivios_Util::autoPopulateFromRows($this,$rows[0]);
            return $this;
        } else
            return null;
    }

    public function getMostRecentByNameAndVersion($name,$channelidarray,$version,$comparator)
    {
        $sql = 'select * from chan_deb_packages where name="'.$name.'" and (';
        for ($i=0;$i<sizeof($channelidarray);$i++) {
            if ($i==0) {
                $sql .= 'channel_id='.$channelidarray[0];
            } else {
                $sql .= ' OR channel_id='.$channelidarray[$i];
            }
        }

        $sql .= ') and version '.$comparator.'"'.$version.'"';

        $sql .= " order by version DESC";

        Zivios_Log::debug("Getting by name packages, sql is ".$sql);
        $rows = $this->_db->fetchAll($sql);
        if (is_array($rows) && sizeof($rows) >0) {
            Zivios_Util::autoPopulateFromRows($this,$rows[0]);
            return $this;
        } else
            return null;

    }

    public function isMoreRecentThan(Zivios_Package_InstalledDebPackage $instdeb)
    {
        return (strcmp($this->version,$instdeb->version) == 1);
    }

    public function getAllForChannel($channel)
    {
        $sql = "select id from chan_deb_packages where channel_id=".$channel;
        $cols = $this->_db->fetchCol($sql);

        $packages = array();
        foreach ($cols as $id) {
            $packages[] = new $this->packageclass($id);

        }
        return $packages;
    }

    private function revive($id)
    {
        $sql = "select * from packages where id=".$id;
        $rows = $this->_db->fetchRow($sql);

        Zivios_Util::autoPopulateFromRows($this,$rows);
    }

    public function setData($name,$short_desc,$long_desc,$version,$conflicts,$replaces,$depends)
    {
        $this->name = $name;
        $this->short_desc = $short_desc;
        $this->long_desc = $long_desc;
        $this->version = str_replace('~','#',$version);
        $this->conflicts = str_replace('~','#',$conflicts);
        $this->replaces =str_replace('~','#',$replaces);
        $this->depends  = str_replace('~','#',$depends);
    }

    public function associateWithChannel($channel_id)
    {
        $this->channel_id = $channel_id;
    }


    public function write()
    {
        $data = array('name' => $this->name,
                      'short_desc' => $this->short_desc,
                      'long_desc' => $this->long_desc,
                      'version' => $this->version,
                      'conflicts' => $this->conflicts,
                      'replaces' => $this->replaces,
                      'depends' => $this->depends,
                      'channel_id' => $this->channel_id);

        try {
            $this->id = $this->insert($data);
        } catch (Zend_Db_Statement_Exception $e)
        {
            Zivios_Log::debug("Package  ".$this->name." already exists on channel, updating!");
            $where = 'name = "'.$this->name.'" and channel_id = '.$this->channel_id;
            $this->update($data,$where);
        }
    }


    public function getChannelId($name,$version,$chanidlist)
    {
        $sql = 'select channel_id from chan_deb_packages where name="'.$name.'" and version="'.$version.'" and (';
        $i=0;
        $chanidstr="";
        foreach ($chanidlist as $chanid) {
            if ($i==0)
                $chanidstr .= 'channel_id='.$chanid;
            else
                $chanidstr .= ' OR channel_id='.$chanid;
            $i++;
        }
        $sql = $sql . $chanidstr . ')';
        Zivios_Log::debug($sql);
        $col = $this->_db->fetchOne($sql);
        return $col;
    }

    public function getUpgradeableDependents($emscomputerdn,$chanidlist)
    {

    }

    public function isInstalled($emscomputerdn)
    {
        $pack = new Zivios_Package_InstalledDebPackage();
        return $pack->isInstalled($this->name,$emscomputerdn);

    }

    public function getInstalledPackage($emscomputerdn,$name=null)
    {
        if ($name == null)
            $name = $this->name;

        $pack = new Zivios_Package_InstalledDebPackage();
        return $pack->getByName($name,$emscomputerdn);
    }


    public function getUpgradeDependents($emscomputerdn,$chanidlist,&$deparray)
    {


    }



    public function getAllInstallDependents($emscomputerdn,$chanidlist,&$deparray)
    {
        Zivios_Log::debug("Getting Dependents for ".$this->name." version : ".$this->version);

        $depends = $this->getDepArray($this->depends);

        for ($i=0;$i<sizeof($depends);$i++) {


            $dep = $depends[$i];
            $name = $dep[0];
            $comp = $dep[1];
            $ver = $dep[2];

            if ($comp != null && $comp != "") {
                if ($comp == '<<')
                    $comparator = "<";
                else if ($comp == '>>')
                    $comparator = ">";
                else if ($comp == '=<')
                    $comparator = '=<';
                else if ($comp == '>=')
                    $comparator = '>=';
                else if ($comp == '=')
                    $comparator = '=';
            }
            Zivios_Log::debug("Checking dependent ".$name." comparator : ".$comp." version ".$ver);
            $instpackage = $this->getInstalledPackage($emscomputerdn,$name);

            if ($instpackage == null && !array_key_exists($name,$deparray)) {
                if ($ver == null) {
                // Any version will do. Simply search for whether its installed or not
                    $chanpackage = new Zivios_Package_ChannelDebPackage();
                    $chanpackage = $chanpackage->getMostRecentByName($name,$chanidlist);
                    $deparray[$name] = $chanpackage;
                    $chanpackage->getAllInstallDependents($emscomputerdn,$chanidlist,$deparray);
                }
                else {
                    //Version is not null, a comparison needs to be done.


                    $chanpackage = new Zivios_Package_ChannelDebPackage();
                    $chanpackage = $chanpackage->getMostRecentByNameAndVersion($name,$chanidlist,$ver,$comparator);
                    $deparray[$name] = $chanpackage;
                    $chanpackage->getAllInstallDependents($emscomputerdn,$chanidlist,$deparray);
                }
            } else {
                // Package is installed, check if version number is in range
                if ($instpackage != null && $ver!=null) {
                    //Only if a required version is specified will we do any comparison!
                    $instver = $instpackage->version;


                    if ($comp == '<<')
                        $satisfy =($instver < $ver);
                    else if ($comp == '>>')
                        $satisfy = ($instver > $ver);
                    else if ($comp == '=<')
                        $satisfy = ($instpackage <= $ver);
                    else if ($comp == '>=')
                        $satisfy = ($instver >= $ver);
                    else if ($comp == '=')
                        $satisfy = ($instver == $ver);
                    else
                        $satisfy = 0;

                    if (!$satisfy) {
                        $chanpackage = new Zivios_Package_ChannelDebPackage();
                        $chanpackage = $chanpackage->getMostRecentByNameAndVersion($name,$chanidlist,$ver,$comparator);
                        $deparray[$name] = $chanpackage;
                        $chanpackage->getAllInstallDependents($emscomputerdn,$chanidlist,$deparray);
                    } else {
                        Zivios_Log::debug("Package ".$name." Already installed and passess version dep check");
                    }

                }

            }
        }
    }


    public function getDepArray($depends)
    {
        Zivios_Log::debug("Got depends string : ".$depends);
        if (preg_match("/,/",$depends)) {
            $deparray = explode(",",$depends);
        } else {
            $deparray = array();
            $deparray[] = $depends;
        }
        $deps = array();
        $i=0;
        foreach ($deparray as $dep) {
            $dep = trim($dep);
            if (preg_match("/\|/",$dep)) {
                //Ignore the OR bit right now.... too confusing!
                $dep = explode("|",$dep);
                $dep = trim($dep[0]);
            }

            if (preg_match("/\(/",$dep)) {

                $matches = array();
                preg_match_all("/^([\s\S.]+?) \(([\s\S.]+?) ([\s\S.]+?)\)/",$dep,$matches);
                $name = $matches[1][0];
                $comparison = $matches[2][0];
                $version = $matches[3][0];

            } else {
                $name = $dep;
                $comparison = null;
                $version = null;
            }

            $deps[$i][0] = $name;
            $deps[$i][1] = $comparison;
            $deps[$i][2] = $version;
            $i++;
        }
        return $deps;

    }

    public function deleteAllForChannel($channel)
    {
        Zivios_Log::debug("Deleting all package cache for channel : " . $channel);

        if ($channel != null && $channel != "")
            $this->delete('channel_id='.$this->channel_id);
        else
            Zivios_Log::error("blank channel passed, would cause a complete truncation of package database!");
    }


}

