<?php
/**
 * Copyright (c) 2008-2010 Zivios, LLC.
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
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class DebComputerPackage extends ComputerPackage
{
    protected $_module = 'package';

    public function populatePackages()
    {
        $computer = $this->getComputer();
        $packagefile = $computer->getFileAsString($this->_compConfig->dpkgstatus);
        // Zivios_Log::debug($packagefile);
        $packages = new Zivios_Package_DebParser($packagefile,Zivios_Package_DebParser::MODE_COMPUTER);
        $packages->setComputerDn($computer->getdn());
        $deleteall = new Zivios_Package_InstalledDebPackage();
        $deleteall->deleteAllForComputer($computer->getdn());
        $pkgs = $packages->parse();
        //Delete all packages for this computer first, necessary waste!
    }

    public function getSubscribedChannels()
    {
        $channels = $this->getProperty("emssubchannels");
        $chanarray = array();
        foreach ($channels as $channel) {
            $iter = new Zivios_Package_DebChannel($channel);
            $chanarray[] = $iter;
        }
        return $chanarray;
    }

    public function generateSourcesListFile()
    {
        $channels = $this->getSubscribedChannels();
        $linearray = array();
        foreach ($channels as $channel) {
            $linearray[] = $channel->generateSourcesListLine();
        }
        return implode("\r\n",$linearray);
    }

    public function hasPackage($name,$version="0")
    {
        $package = $this->getInstalledPackage($name);
        $has = 0;
        if($package != null && strcmp($package->version,$version) <= 0) {
            $has = 1;
            Zivios_Log::debug("Found package : ".$package->name." of version : ".$package->version." Returning : ".$has);
        } else {
            Zivios_Log::debug('Did not found package: ' . $name);
        }
        return $has;
    }
    
    public function getInstalledPackage($packagename)
    {
        $deb = new Zivios_Package_InstalledDebPackage();
        $deb = $deb->getByName($packagename,$this->_computerobj->getdn());
        return  $deb;
    }
    
    public function getAllPackages()
    {
        $deb = new Zivios_Package_InstalledDebPackage();
        return $deb->getAllForComputer($this->_computerobj->getdn());
    }
    
    public function searchInstalledPackages($name,$limit=1000000,$offset=0)
    {
        $deb = new Zivios_Package_InstalledDebPackage();
        return $deb->searchAllForComputer($this->_computerobj->getdn(),$name);
    }

    public function getChannelPackage($packagename)
    {
        $deb = new Zivios_Package_ChannelDebPackage($packagename);
        return $deb->getMostRecentByName($packagename,$this->getProperty('emssubchannels'));

    }

    public function refreshPackages()
    {
        $this->populatePackages();
    }

    /**
     * This function lists all packages that would be acted upon if this package was
     * installed or upgraded. This only suggests packages that NEED to be installed/upgraded
     * - packages which fit the requirement are left AS IS.
     *
     * @param unknown_type $packagename
     * @return unknown
     */

    public function getUpdatedPackagesFromChannels()
    {
        $chan = new Zivios_Package_DebChannel();
        $chan->getUpdatedPackagesForComputer($this->_computerobj->getdn(),$this->getProperty('emssubchannels'));
    }

    public function getInstallDependents($name)
    {
        $chan = new Zivios_Package_ChannelDebPackage();
        $package = $chan->getMostRecentByName($name,$this->getProperty('emssubchannels'));
        $deparray = array();
        $package->getAllInstallDependents($this->_computerobj->getdn(),$this->getProperty('emssubchannels'),$deparray);
        return $deparray;
    }

    public function getUpgradeable()
    {
        $chan = new Zivios_Package_DebChannel();
        $packages = $chan->getUpdatedPackagesForComputer($this->_computerobj->getdn(),$this->getProperty('emssubchannels'));

        return $packages;
    }
    public function queryInstall($names)
    {
        $agent = $this->getComputer()->getAgent('aptfront');
        if (!is_array($names)) {
            $names = array($names);
        }
        $output = $agent->queryinstall($names);
        Zivios_Log::debug($output);
        return $this->processQueryOutput($output,Zivios_Package_PackageQuery::ACTION_INSTALL,$names);
    }

    public function queryUpgrade()
    {
        $agent = $this->getComputer()->getAgent('aptfront');

        $output = $agent->queryupgrade();
        return $this->processQueryOutput($output,Zivios_Package_PackageQuery::ACTION_UPGRADE);
    }

    public function processQueryOutput($output,$originaction,$originpackage=null)
    {
        $pquery= new Zivios_Package_PackageQuery($originaction,$originpackage);
        $subchannels = $this->getProperty('emssubchannels');
        foreach ($output as $line) {
            $record=0;
            $matches = array();
            if (preg_match("/\[/",$line))   {
                Zivios_Log::debug("Processing Line : ".$line);
                preg_match_all("/^(.+?) (.+?) \[([\s\S.]+?)\] \(([\s\S.]+?) ([\s\S.]+?)\)/",$line,$matches);
                $action = $matches[1][0];
                $name = $matches[2][0];
                $curr_ver = $matches[3][0];
                $new_ver = $matches[4][0];
                $channel = $matches[5][0];
            } else {
                preg_match_all("/^(.+?) (.+?) \(([\s\S.]+?) ([\s\S.]+?)\)/",$line,$matches);
                $action = $matches[1][0];
                $name =$matches[2][0];
                $new_ver = $matches[3][0];
                $channel = $matches[4][0];
            }
            if ($action == "Inst") {
                $record = 1;
                if ($curr_ver == "")
                    $action = Zivios_Package_PackageQueryItem::ACTION_INSTALL;
                else
                    $action = Zivios_Package_PackageQueryItem::ACTION_UPGRADE;

            } else if ($action == "Remv") {
                $record = 1;
                $action = Zivios_Package_PackageQueryItem::ACTION_REMOVE;
            }

            $channel = new Zivios_Package_DebChannel();
            $channelid = $channel->getChannelIdForPackage($name,$new_ver,$subchannels);

            if ($record) {
                $pqitem = new Zivios_Package_PackageQueryItem();
                $pqitem->setData($name,$action,$curr_ver,$new_ver,$channelid);
                $pquery->addItem($pqitem);
            }
        }
        return $pquery;



    }


    public function installPackage($name,$titemid)
    {
        Zivios_Log::debug("Installing Package : ".$name);
        $agent = $this->getComputer()->getAgent('aptfront');
        $agent->install($titemid,$name);
    }


}


