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

class Zivios_Package_DebChannel extends Zivios_Package_Channel
{
    public function updatePackageList()
    {
        $packagesfilelocation = $this->getPackageFileLocation();
        $ch = curl_init($packagesfilelocation);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = bzdecompress($output);

        //Parse the package list from server
        $packages = new Zivios_Package_DebParser($output,Zivios_Package_DebParser::MODE_CHANNEL);
        $packages->setChannelId($this->id);
        $pkgs = $packages->parse();

        Zivios_Log::debug("Processing and Writing!");

    }

    public function getChannelIdForPackage($name,$newversion,$chanidlist)
    {
        $package = new Zivios_Package_ChannelDebPackage();
        return $package->getChannelId($name,$newversion,$chanidlist);
    }

    public function generateSourcesListLine()
    {
        $string = "deb ";
        $string .= $this->baseurl ." ". $this->deb_dists . " " . $this->deb_section;
        return $string;

    }
    public function getPackageFileLocation()
    {
        $location = $this->baseurl."/dists/".$this->deb_dists."/".$this->deb_section."/".$this->arch."/Packages.bz2";
        Zivios_Log::debug("Package File Location calculated as ".$location);
        return $location;
    }

    public function getUpdatedPackagesForComputer($emscomputerdn,$chanidlist)
    {
        $sql =  'select t1.name as name,max(t2.version) as version,t2.id as id,t2.depends as depends,t2.conflicts as ' .
                'conflicts, t2.short_desc as short_desc,t2.long_desc as long_desc,t2.channel_id as channel_id, '.
                't2.replaces as replaces from '.
                'inst_deb_packages as t1 left join chan_deb_packages as t2 on t1.name = t2.name where t2.name is not null '.
                'and t2.version > t1.version and t1.emscomputerdn ="'.$emscomputerdn.'" and (';

        $i=0;
        $chanidstr="";
        foreach ($chanidlist as $chanid) {
            if ($i==0)
                $chanidstr .= 't2.channel_id='.$chanid;
            else
                $chanidstr .= ' OR t2.channel_id='.$chanid;
            $i++;
        }
        $sql = $sql . $chanidstr . ')';

        $sql .= ' group by name';
        $rows = $this->_db->fetchAll($sql);
        if (is_array($rows) && sizeof($rows) > 0) {
            $upgrade = array();
            foreach ($rows as $row) {
                $chan = new Zivios_Package_ChannelDebPackage();
                $chan->setData();
                Zivios_Util::autoPopulateFromRows($chan,$row);
                $upgrade[$chan->name] = $chan;
            }
            return $upgrade;

        } else {
            return null;
        }


    }
}
