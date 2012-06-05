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
 * @package     mod_default
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSComputer extends EMSPluginManager
{
    private $mode;

    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'iphostnumber';
        $attrs[] = 'cn';

        // Computer Related Params
        $attrs[]='emscomputervendormodel';
        $attrs[]='emscomputercpumhz';
        $attrs[]='emscomputercpucount';
        $attrs[]='emscomputerram';
        $attrs[]='emscomputerswap';
        $attrs[]='emscomputersystem';
        $attrs[]='emscomputertype';
        $attrs[]='emscomputerdistro';
        $attrs[]='emscomputerdistrorelease';
        $attrs[]='emscomputerarch';
        $attrs[]='emsdistrocodename';
        $attrs[]='emsdistrodesc';
        
        // For Windows computers (emscomputersystem=windows)
        $attrs[]='uid';
        $attrs[]='sn';

        //$this->initPlugins();
        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();
        $param = $this->getParameter('iphostnumber');
    }

    public function setType($type)
    {
        $this->setProperty('emstype',$type);
    }

    public function getType()
    {
        $this->getProperty('emstype');
    }

    protected function getrdn()
    {
        if ($this->mode == 'windows') {
            return 'uid';
        }
        return 'cn';
    }

    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        if ($this->getProperty('emscomputersystem') == 'windows') {
            $this->mode = 'windows';
            $this->setProperty('uid',$this->getProperty('cn')."$");
            $this->setProperty('sn',$this->getProperty('cn')."$");
        }

        if ($this instanceof EMSComputer) {
            $this->addObjectClass('device');
            $this->addObjectClass('ipHost');
            $this->addObjectClass('EMSComputer');
        }

        return parent::add($parent,$tgroup,$description);
    }

    public function getAgent($plugin)
    {
        $host = $this->getProperty('iphostnumber');
        $agent = new Zivios_Comm_Agent($host, $plugin);
        return $agent;
    }

    /**
     * Simple 'ping' operation for any managed computer to its Zivios Agent. 
     * 
     * @return boolean
     */
    public function pingAgent()
    {
        $agent = $this->getAgent('zvcore');

        try {
            return $agent->testConnect();
        } catch (Zivios_Comm_Exception $e) {
            Zivios_Log::exception($e);
            return 0;
        }
    }
    
    public function getIp()
    {
        return $this->getProperty('iphostnumber');
    }

    public function putFileFromString($string,$destination,$perm=0,$owneruid=null,$ownergid=null)
    {
        $agent = $this->getAgent('netfile');
        $agent->put($destination,base64_encode($string),$perm,$owneruid,$ownergid);
    }

    public function putFile($source,$destination,$perm=0,$owneruid=null,$ownergid=null)
    {
        $this->backupTargetFile($destination);
        $agent = $this->getAgent('netfile');
        $handle = fopen($source, "r");
        $contents = fread($handle, filesize($source));
        $agent->put($destination,base64_encode($contents),$perm,$owneruid,$ownergid);

        fclose($handle);
    }
    
    public function backupTargetFile($filesrc)
    {
        if ($string = $this->getFileAsString($filesrc)) {
            //$file = new Zivios_File($filesrc, $this->getdn());
            //$file->addNewVersion($string);
            Zivios_Log::info('Backup / file version implementation pending.');
        } else {
            Zivios_Log::info('Remote file '.$filesrc.' not found. File backup ignored.');
        }
    }

    public function getFile($source,$destination)
    {
        $agent = $this->getAgent('netfile');
        $handler = fopen($destination, "w");
        $contents = base64_decode($agent->get($source));
        fwrite($handler,$contents);
        fclose($handler);
        Zivios_Log::debug("File $source transferred from ".$this->getProperty('cn')
            ." to destination : $destination");
    }

    public function getFileAsString($source)
    {
        $agent = $this->getAgent('netfile');
        if (false !== ($contents = $agent->get($source))) {
            $contents = base64_decode($contents);
            Zivios_Log::debug('File ' . $source . ' transferred from '. $this->getProperty('cn'));
            return $contents;
        } else {
            Zivios_Log::error('File not found on remote server.');
            return false;
        }
    }

    public function getComputerDistroId()
    {
        return strtolower($this->getproperty("emscomputerdistro"))
            . '-' . strtolower($this->getproperty("emsdistrocodename"));
    }

    /*
    * Probes an array containing string names (keys) of packages along
    * with versions as their values
    *
    * returns true or the name of the package with problems
    */
    public function hasPackages($packagearray)
    {
        $packageplugin = $this->getPackagePlugin();
        
        foreach ($packagearray as $package => $version) {
            if (!$packageplugin->hasPackage($package, $version))
                return $package;
        }

        Zivios_Log::debug("All Packages exist on target Host");
        return true;
    }
    
    
    public function getAllGroups($norecurse=0)
    {
        $grouparray = array();
        $this->__getAllGRecurse($this->getdn(),$grouparray,$norecurse);
        return $grouparray;
    }

    private function __getAllGRecurse($dn,&$grouparray,$norecurse=0)
    {
        // get all immediate groups if norecurse is enabled, 
        // else do a recursive lookup
        $filter = "(&(objectclass=emscomputergroup)(member=$dn))";
        $entries = $this->search($filter,array('dn'),null,'SUB');

        for ($i=0; $i < $entries['count']; $i++) {
            $objdn = $entries[$i]['dn'];
            $arraysize = sizeof($grouparray);
            $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
            $grouparray[] = $emsobjdn;

            if (!$norecurse) {
                $this->__getAllGRecurse($objdn,$grouparray);
            }
        }
    }
}

