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

class Zivios_Package_DebParser
{
    public $packagesfile,$mode,$channel_id,$emscomputerdn;
    const MODE_COMPUTER = 'COMPUTER';
    const MODE_CHANNEL = 'CHANNEL';

    public function __construct($packagesfile,$mode)
    {
        $this->packagesfile = $packagesfile;
        $this->mode = $mode;
    }

    public function setChannelId($id)
    {
        $this->channel_id = $id;
        $this->mode = self::MODE_CHANNEL;
    }

    public function setComputerDn($emscomputerdn)
    {
        $this->emscomputerdn = $emscomputerdn;
        $this->mode = self::MODE_COMPUTER;
    }

    public function getValue($string,$key)
    {

        $matches = array();
        $boo = preg_match_all("/$key: (.+?)\n/",$string,$matches);
        if (isset($matches[1][0])) {
            Zivios_Log::debug("Looking for $key found : ".$matches[1][0]);
            return $matches[1][0];
        } else {
            Zivios_Log::debug("No key $key found");
            return null;
        }
    }

    public function processPackageBlock($packageblock)
    {

        $name = $this->getValue($packageblock,'Package');
        $short_desc = $this->getValue($packageblock,'Description');

        $matches = array();
        preg_match_all("/Description: (.+?)\n([.\s\S]+?)$/",$packageblock,$matches);
        $long_desc = trim($matches[2][0]);
        //Zivios_Log::debug("Long description found : $long_desc");


        $version = $this->getValue($packageblock,'Version');
        $conflicts = $this->getValue($packageblock,'Conflicts');
        $replaces = $this->getValue($packageblock,'Replaces');
        $depends = $this->getValue($packageblock,'Depends');


        if  ($this->mode == self::MODE_COMPUTER) {

            $status = $this->getValue($packageblock,'Status');
            if (preg_match('/installed/',$status))
                $status = Zivios_Package_InstalledDebPackage::STATUS_INSTALLED;
            else
                $status = Zivios_Package_InstalledDebPackage::STATUS_UNKNOWN;

            $zvpackage = new Zivios_Package_InstalledDebPackage();
            $zvpackage->setData($name,$short_desc,$long_desc,$version,$status,$conflicts,$replaces,$depends);
            $zvpackage->associateWithComputer($this->emscomputerdn);
        } else {
            $zvpackage = new Zivios_Package_ChannelDebPackage();
            $zvpackage->setData($name,$short_desc,$long_desc,$version,$conflicts,$replaces,$depends);
            $zvpackage->associateWithChannel($this->channel_id);
        }
        return $zvpackage;
    }
    
    public function parse()
    {
        $packageblocks = explode("\n\n",$this->packagesfile);
        //Zivios_Log::debug($packageblocks);
        foreach ($packageblocks as $packageblock) {
            if (trim($packageblock) != "") {
                $package = $this->processPackageBlock($packageblock);
                $package->write();
            }

        }
    }
}

