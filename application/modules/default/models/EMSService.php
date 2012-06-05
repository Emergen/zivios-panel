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
 * @package     mod_default
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

abstract class EMSService extends EMSObject
{
    /**
     * @var $_serviceCfgGeneral Holds the "general" section of the
     * service.ini configuration file.
     * @var $_serviceCfgLib holds the 'libraries' section
     */
    protected $_serviceCfgGeneral,$_serviceCfgLib, $_serviceCfgDistro,
              $_compConfig, $_commAgent;
    protected $_serviceConfig, $_clusterCfg;
    public    $mastercomp;


    public function init()
    {
        parent::init();

        if (null !== ($mcdn = $this->getProperty('emsmastercomputerdn'))) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
        }
    }

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'emsmastercomputerdn';
        $attrs[] = 'emscomputersdn';

        $this->_iniConfig();
        parent::__construct($dn,$attrs,$acls);
    }

    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->setProperty('emstype',EMSObject::TYPE_SERVICE);
        $this->setProperty('emsmodulename',$this->returnModuleName());
        //$this->addObjectClass('namedObject');
        $this->addDependence($this->getProperty('emsmastercomputerdn'));
        return parent::add($parent, $tgroup,$description);
        
    }

    //abstract public function update(Zivios_Transaction_Handler $handler=null);
    //abstract public function delete(Zivios_Transaction_Handler $handler=null);

    public function getrdn()
    {
        return 'cn';
    }

    /**
     * Returns the service display name
     *
     * @return string
     */
    public function returnDisplayName()
    {
        return $this->getProperty('cn');
    }

    /**
     * Returns the module name.
     *
     * @return string
     */
    public function returnModuleName()
    {
        return $this->_serviceCfgGeneral->modulename;
    }

    /**
     * Check if the Zivios agent is responding.
     *
     * @return boolean true|false
     */
    public function pingZiviosAgent()
    {
        $this->_initCommAgent();
        if ($this->_commAgent->agentStatus()) {
            Zivios_Log::info('Zivios Agent communication initialized.');
            return true;
        } else {
            Zivios_Log::error('Zivios Agent communication failed. Agent may not be running.');
            return false;
        }
    }    

    /**
     * Generate a Context Menu based on a module's
     * service menu file, or return false.
     *
     * @return Zend_Config_Ini
     */
    public function generateContextMenu()
    {
        $appConfig = Zend_Registry::get("appConfig");

        try {
            return new Zend_Config_Ini($appConfig->modules .
                    '/' . $this->_serviceCfgGeneral->modulename .
                    '/config/rcmenu.ini', 'ServiceEntry');
        } catch (Exception $e) {
            Zivios_Log::Debug("Context menu not provided for module: " .
                $this->_serviceCfgGeneral->modulename);
        }
        return false;
    }

    /**
     * Initialize Module configuration as Zend_Ini object
     */
    protected function _iniConfig()
    {
        if (!$this->_serviceCfgGeneral instanceof Zend_Config_Ini) {
            // Get application configuration object
            if (!isset($this->_module) || $this->_module == '') {
                throw new Zivios_Exception("Variable _module must be set by your calling class: " . get_class($this));
            }

            $appConfig = Zend_Registry::get('appConfig');

            // Instantiate cfg object for plugin
            $this->_serviceCfgGeneral = new Zend_Config_Ini($appConfig->modules . '/' .
                $this->_module . '/config/service.ini', 'general');

            $this->_serviceCfgLib = new Zend_Config_Ini($appConfig->modules . '/' .
                $this->_module . '/config/service.ini', 'libraries');

            $this->_serviceCfgDistro = new Zend_Config_Ini($appConfig->modules . '/' .
                $this->_module . '/config/service.ini', 'distros'); 
        }
    }

    /**
     * Initializes the cluster configuration section for a given service. 
     * 
     */
    protected function _iniClusterConfig()
    {
        if (!$this->_clusterCfg instanceof Zend_Config_Ini) {
            $appConfig = Zend_Registry::get('appConfig');

            $this->_clusterCfg = new Zend_Config_Ini($appConfig->modules . '/' .
                $this->_module . '/config/service.ini', 'clusterconfig');
        }
    }

    /**
     * Returns the cluster target host configuration section
     *
     * @return Zend_Config_Ini object
     */
    protected function _getClusterTargetConfig($targetDistro)
    {
        $tgtClsCfg = $targetDistro . '-cluster';

        $appConfig = Zend_Registry::get('appConfig');
        
        return new Zend_Config_Ini($appConfig->modules . '/' .
            $this->_module . '/config/service.ini', $tgtClsCfg);
    }

    /**
     * Initializes the mastercomp variable with an instance of the
     * computer DN set in attr: emsmastercomputerdn.
     *
     * @return EMSComputer object $this->mastercomp
     */
    public function getMasterComputer()
    {
        if ($this->mastercomp == null) {
            $mcdn = $this->getProperty('emsmastercomputerdn');
            if ($mcdn != null) {
                $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
                return $this->mastercomp;
            } else {
                throw new Zivios_Exception('Master computer is undefined.');
            }
        } else {
            return $this->mastercomp;
        }
    }

    protected function _setMasterComputerConfig()
    {
        if ($this->_compConfig instanceof Zend_Config_Ini)
            return;

        $this->getMasterComputer();
        $cfgSection = $this->mastercomp->getComputerDistroId();
        $appConfig = Zend_Registry::get('appConfig');

        $this->_compConfig = new Zend_Config_Ini(
            $appConfig->modules . '/' . $this->_module .
            '/config/service.ini', $cfgSection);
    }
    
    /**
     * Get all groups which subscribe to the service. This function is safe as long
     * as we do not allow service entries to appear in the base DN. If that is required,
     * the ->getParent() call needs to check if baseDN has been loaded before a second
     * call to ->getParent is issued. Currently, Zivios will not allow service entries
     * in EMSControl container, hence no such check is in place.
     *
     * @param false $loadModel
     * @return array $groups
     */
    public function getAllSubscribingGroups($loadModel=false)
    {
        // Unless loadModel is set to false, we do not load the heavy ZV Object.
        isset($loadModel) ? $loadModel = null : $loadModel = 'NOMODEL';

        $dn     = $this->getdn();
        $filter = '(&(objectclass=groupofnames)(emsservicemap=*:'.$dn.':*))';
        $parent = $this->getParent()->getParent();
        $groups = $parent->getAllChildren($filter, 'SUB', true, null, $loadModel);
        
        return $groups;
    }

    public function delete(Zivios_Transaction_Group $tgroup,$description = null)
    {
        $groups = $this->getAllSubscribingGroups();
        $this->_iniConfig();

        foreach ($groups as $group) {
            $handler = $group->removePlugin($this->_serviceCfgLib->group,$tgroup);
        }

        return parent::delete($tgroup,$description);
    }
    
    /**
     * Probes a computer object for required packages.
     * 
     */
    public function probeComputer()
    {
        $reqPackages = $this->getServicePrereqPackages();
        $packages = explode(',', $reqPackages);
        $reqpackages = array();
        foreach ($packages as $package) {
            $piter = explode('|',$package);
            $reqpackages[$piter[0]] = $piter[1];
        }
        
        Zivios_Log::debug("Required packages array :");
        Zivios_Log::debug($reqpackages);
        return $this->getMasterComputer()->hasPackages($reqpackages);
    }

    /**
     * Returns array of computer objects which are compatible with the
     * service module in question, or if no computers found, a list of
     * compatible computer operating systems for the module.
     *
     * @return array (of objects) $compList | string $compList
     */
    protected function _getCompatibleComputers($parentObject)
    {
        $compComputers = explode(",", rtrim($this->_serviceCfgDistro->supported, ','));

        if (empty($compComputers)) {
            Zivios_Log::info("No Compatible Computers found for Service: " .
                $this->_serviceCfgGeneral->displayname);
            return array();
        }

        $searchFrom = $parentObject->getParent();
        $filter = '(&(objectclass=EMSComputer)(|';
        foreach ($compComputers as $cc) {
            $ccInfo = explode ('-', $cc);
            $filter .= '(&(emscomputerdistro='.$ccInfo[0].')(emsdistrocodename='.$ccInfo[1].'))';
        }

        $filter .= '))';

        $compList = $parentObject->getAllChildren($filter, "SUB", null, $searchFrom->getdn());

        if (empty($compList)) {
            return $this->_serviceCfgDistro->supported;
        } else {
            return $compList;
        }
    }

    public function getCompatibleComputers(EMSObject $serviceContainer)
    {
        $compList = $this->_getCompatibleComputers($serviceContainer);
        return $compList;
    }

    /**
     * Initializes the service configuration file, returning all config sections.
     *
     * @return object $_serviceConfig
     */
    protected function _initializeServiceConfig()
    {
        if (!$this->_serviceConfig instanceof Zend_Config_Ini) {
            // look for service configuration file.
            $appConfig  = Zend_Registry::get('appConfig');
            $configFile = $appConfig->modules . '/'.$this->_module.'/config/service.ini';

            if (!file_exists($configFile) && !is_readable($configFile)) {
                throw new Zivios_Exception('Could not find/read module configuration file.');
            }

            $this->_serviceConfig = new Zend_Config_Ini($configFile);
        }

        return $this->_serviceConfig;
    }
    
    /**
     * Initialize distribution specific configuration section for the service.
     *
     * @return Zend_Config_Ini Object $config
     */
    protected function getTargetComputerConfig()
    {
        if (null == $this->mastercomp) {
            Zivios_Log::debug('Initializing service computer object.');
            $this->getMasterComputer();
        }

        $distroid   = $this->mastercomp->getComputerDistroId();
        $appConfig  = Zend_Registry::get('appConfig');
        $configFile = $appConfig->modules . '/' . $this->_module . '/config/service.ini';

        return new Zend_Config_Ini($configFile, $distroid);
    }
    
    /**
     * Retrieve all packages required by a remote system to run the
     * given service.
     *
     * @return $packages
     */
    protected function getServicePrereqPackages()
    {
        $this->getMasterComputer();
        $distroid = $this->mastercomp->getComputerDistroId();
        $config   = $this->getTargetComputerConfig();
        $packages = $config->reqpackages;

        return $packages;
    }

    /**
     * Initialize communication with Zivios agent on the service side.
     *
     * @return object $_commAgent
     */
    protected function _initCommAgent()
    {
        if ($this->mastercomp == null) {
            $this->getMasterComputer();
        }

        if (!$this->_commAgent instanceof Zivios_Comm_Agent) {
            Zivios_Log::debug('Initializing communication agent for module: ' . $this->_module);
            $this->_commAgent = new Zivios_Comm_Agent($this->mastercomp->getIp(), $this->_module);
            return $this->_commAgent;
        }

        return $this->_commAgent;
    }
}

