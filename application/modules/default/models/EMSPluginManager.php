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

class EMSPluginManager extends EMSObject
{

    protected $_plugins;

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs =array();
        $attrs[] = 'emsplugins';
        $attrs[] = 'emsmodules';
        
        parent::__construct($dn,$attrs,$acls);

        $this->_plugins = array();
        // Do plugin processing for attrs!
        if ($acls == null)
            $acls = array();

        $plugret = Zivios_Ldap_Engine::getPlugins($dn);

        if ($plugret != null) {

            if (!is_array($plugret)) {
                $plugarray = array();
                $plugarray[] = $plugret;
            } else
                $plugarray = $plugret;
            //Compile ATTRS now

            foreach ($plugarray as $plug)
            {
                $plugin = new $plug();
                $plug_attr = $plugin->getAttrs();

                if ($plug_attr != null) {
                   // $attrs = array_merge($attrs,$plug_attr);
                   $this->addAttrs($plug_attr,null,get_class($plugin));
                }

                $plug_acls = $plugin->getAcls();

                if ($plug_acls != null)
                    $acls = array_merge($acls,$plug_acls);

                $this->_plugins[$plug] = $plugin;
            }
            
            $this->addEmsAcls($acls);
        }

        // Plugin compilation complete



    }

    /**
     * Properly instantiates and returns a plugin
     */
    public function newPlugin($pluginname)
    {
        $plug = new $pluginname();
        $plug_attr = $plug->getAttrs();
        $this->addAttrs($plug_attr,null,$plug->getNameSpace());
        $plug->init($this);
        return $plug;
    }

    public function init()
    {
        parent::init();

        // $paramslist = print_r($this->params,1);
        // Zivios_Log::debug("param list now : $paramslist");

        if ($this->_plugins != null) {

            foreach ($this->_plugins as $plug) {
                $plug->init($this);
            }
        }
        //invalidate the cache and store a new copy (due to plugin mods!);
        $this->store();
    }

    public function add (Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $group,$description=null)
    {
        $group = parent::add($parent,$group,$description);
        /*foreach ($this->_plugins as $plug) {
            $plug->add($parent,$handler);
        }*/
        return $group;
    }

    /*
    public function _update() {
        return parent::update();
        /** foreach ($this->_plugins as $plug) {
            $plug->update();
        }
    }
    */

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        // Not passing original description as it would be misleading!
        foreach ($this->_plugins as $plug) {
            $handler = $plug->delete($tgroup);
        }
        return parent::delete($tgroup,$description);
    }

    public function hasPlugin($pluginname)
    {
        $plugins = $this->getProperty('emsplugins');
       // Zivios_Log::debug($plugins);
        if (is_array($plugins))
        {
            return in_array($pluginname,$plugins);
        }
        else if ($pluginname == $plugins)
        {
            return true;
        }
        return false;
    }


    public function getAllPlugins()
    {
        return $this->_plugins;
    }

    public function wakeup()
    {
        parent::wakeup();
        foreach ($this->_plugins as $plugin) {
            $plugin->wakeup();
        }
    }

    /**
     * Function does a reverse transversal in the tree looking for 
     * service entries which are not currently subscribed to by the user, 
     * group or computer.
     *
     * Existing services that an entry may subscribe to are skipped. The
     * check for existing entries is keyed by the service-dn and not the
     * module name.
     *
     * New objects being evaluated must pass a parentDn to ensure the
     * service scan has a starting scan base.
     *
     * The returned associative array holds two keys, namely: 
     *    availableServices &
     *    existingServices
     *
     * @param  string $parentDn
     * @return array  $serviceData
     */
    public function initializeServiceScan($parentDn='')
    {
        $ldapConfig         = Zend_Registry::get('ldapConfig');
        $appConfig          = Zend_Registry::get('appConfig');
        $containerFilter    = '(emstype=ServiceContainer)';
        $serviceFilter      = '(emstype=ServiceEntry)';
        $availableServices  = array();
        
        if ($this->isNew()) {
            // ensure emstype is set.
            if ($this->getProperty('emstype') == '') {
                throw new Zivios_Exception('Could not load attribute: emstype.');
            }
            
            // Load the parent object.
            $parent            = Zivios_Ldap_Cache::LoadDn($parentDn);
            $extServiceDetails = array();
            $existingModules   = array();
        } else {
            $parent            = $this;
            $extServiceDetails = array();
            $existingModules   = $this->getProperty('emsmodules');
            
            switch (strtolower($this->getProperty('emstype'))) {
                case "userentry" : 
                    // currently users do not have a service map
                break;

                case "serverentry" : case "desktopentry" : case "groupentry" : 
                    // get the service map
                    $serviceMap = $this->getProperty('emsservicemap');
                    if (!is_array($serviceMap)) {
                        if ($serviceMap != '') {
                            $serviceMap = array($serviceMap);
                        } else {
                            $serviceMap = array();
                        }
                    }
    
                    if (!empty($serviceMap)) {
                        foreach ($serviceMap as $serviceEntry) {
                            $sd           = explode(":", $serviceEntry);
                            $serviceEntry = Zivios_Ldap_Cache::loadDn($sd[1]);
                            $extServiceDetails[$serviceEntry->getProperty('emsmodulename')] = 
                                array(
                                    array('dn' => $serviceEntry->getdn(), 'label' => $serviceEntry->getProperty('cn'))
                                );
                        }
                    }                
                break;

                default: 
                    throw new Zivios_Exception('Unknown object in tree. EMSType not recognized.');

            }
        }
        
        $c = 0;
        while (1) {
            $result = $parent->getAllChildren($containerFilter, 'ONE');

            if (!empty($result)) {
                foreach ($result as $serviceContainer) {
                    foreach ($serviceContainer->getAllChildren($serviceFilter, 'ONE') as $serviceEntry) {

                        // before we register the service as available, we need to ensure the object
                        // type is compatible with the service and is not currently subscribing to it.
                        switch (strtolower($this->getProperty('emstype'))) {
                            case 'userentry':

                            break;

                            case 'groupentry':
                            break;

                            case 'desktopentry':
                            break;

                            case 'serverentry':
                            // Ensure given service is compatible with server distribution.
                            if (file_exists($appConfig->modules .'/'. $serviceEntry->getProperty("emsmodulename")
                                . '/config/computer.ini')) {
                                try {
                                    // initialize configuration file for module based on OS.
                                    $cInf = new Zend_Config_Ini($appConfig->modules . '/' .
                                        $serviceEntry->getProperty('emsmodulename') . '/config/computer.ini', 
                                        strtolower($this->getProperty('emscomputersystem')));

                                    $releaseSupport = explode(",", $cInf->releasesupport);
                                    $osCheck = $this->getComputerDistroId();

                                    if (in_array($osCheck, $releaseSupport)) {
                                        Zivios_Log::debug('Compatible service found: ' . $serviceEntry->getProperty('cn'));
                                        // the module-name (for ex: 'ca') will provide grouping of similar services.

                                        // Server entry holds a subscription to the given module. We discriminate here
                                        // and disallow any further services from the same module as a possible candidate
                                        // for subscription.
                                        if (!in_array($serviceEntry->getProperty('emsmodulename',1), $existingModules)) {
                                            Zivios_Log::debug('Service DN discovered: ' . $serviceEntry->getdn());
                                            Zivios_Log::debug('Service Entry Label: ' . $serviceEntry->getProperty('cn'));

                                            $availableServices[$serviceEntry->getProperty('emsmodulename')][] =
                                                array($serviceEntry->getdn() => $serviceEntry->getProperty('cn'));
                                        }
                                    } else {
                                        Zivios_Log::debug('OS Release incompatible with service: ' . 
                                            $serviceEntry->getProperty('cn'));
                                    }
                                } catch (Exception $e) {
                                    // OS incompatible with service.
                                    Zivios_Log::debug('Server Operating system incompatible with service.');
                                }
                            }

                            break;

                            default: 
                                throw new Zivios_Exception('Unknown EMS Object type.');
                        }
                    }
                }
            }
            
            if (isset($zvForceBreak) && $zvForceBreak == 1) {
                /**
                 * Reassign parent to baseDN & break.
                 */
                Zivios_Log::debug("Reassigning Parent and breaking Loop.");
                $parent = $saveParent;
                unset($saveParent);
                break;
            }

            if ($parent->getdn() != $ldapConfig->basedn) {
                $parent = $parent->getParent();
                continue;
            } else {
                /**
                 * We've hit the base dn -- instantiate the following DN:
                 * ou=Master Services, ou=Core Control, ou=Zivios, base_dn &
                 * scan for services.
                 */
                $zvServDn = 'ou=Core Control,ou=Zivios,' . $ldapConfig->basedn;
                $zvForceBreak = 1;
                $saveParent = $parent;
                $parent = Zivios_Ldap_Cache::loadDn($zvServDn);

                Zivios_Log::debug("Making parent: " . $zvServDn);
                Zivios_Log::debug("Continuing service scan...");
                continue;
            }
        }

        return array('existingServices' => $extServiceDetails, 'availableServices' => $availableServices);
    }

    /**
     * Function to be removed. Please see function: initializeServiceScan
     *
     * @deprecated
     */
    public function getAvailableServices()
    {
        Zivios_Log::info('DEPRECATED FUNCTION CALL! Please use function: ' . 
            'initializeServiceScan() instead.');

        /**
         * From the existing object DN, do a reverse transversal
         * through the tree and seek available services the object
         * currently does not subscribe to.
         *
         * We further want to make sure that the "type" of object we are searching
         * under has options available for service subscription.
         *
         * Lastly, we will descend into masterservice,zivios,base_dn (forcefully) to
         * scan for services in a hardcoded dn.
         */
        $lconf = Zend_Registry::get('ldapConfig');
        $aconf = Zend_Registry::get('appConfig');
        $containerFilter = "(emstype=ServiceContainer)";
        $serviceFilter = '(emstype=ServiceEntry)';
        $availableServices = array();
        $existingModules = $this->getModules();
        $parent = $this;
        $registeredServices = array();
        $c=0;

        while (1) {
            $result = $parent->getAllChildren($containerFilter,'ONE');

            if (!empty($result)) {
                Zivios_Log::debug("Found service container(s) in: ". $parent->getdn());
                foreach ($result as $serviceContainer) {
                    Zivios_Log::debug("Looking for Services in Service Container: " .
                        $serviceContainer->getProperty("cn"));

                    foreach($serviceContainer->getAllChildren($serviceFilter, 'ONE') as $serviceEntry) {
                        Zivios_Log::debug("Service Entry Found. Service Available: " .  $serviceEntry->getProperty("cn"));

                        if (!in_array($serviceEntry->getProperty("cn"), $registeredServices)) {
                            $registeredServices[] = $serviceEntry->getProperty("cn");
                        } else {
                            /**
                             * Service already registered -- this is required as computers, users &
                             * groups coming out of zivios core control will hit the forceful service
                             * scan in ou=Master Services.
                             */
                            Zivios_Log::Debug("Skipping already registered service: " .
                                $serviceEntry->getProperty("cn"));
                            continue;
                        }

                        /**
                         * Initially we check the object type and ensure the module provides a
                         * configuration file for it.
                         */
                        switch ($this->getProperty("emstype")) {
                            case "UserEntry" :

                            break;

                            case "GroupEntry" :
                            if (file_exists($aconf->modules .'/'. $serviceEntry->getProperty("emsmodulename")
                                . '/config/group.ini')) {
                                if (!in_array($serviceEntry->getProperty("emsmodulename"), $existingModules)) {
                                    $availableServices[] = $serviceEntry;
                                    Zivios_Log::debug("Adding Service : ".$serviceEntry->getdn());
                                }
                            }

                            break;

                            case "ServerEntry" :
                            case "DesktopEntry" :
                            /**
                             * For computer entries, we need to ensure that the module is compatible
                             * with the computer's os.
                             */
                            if (file_exists($aconf->modules .'/'. $serviceEntry->getProperty("emsmodulename")
                                . '/config/computer.ini')) {

                                /**
                                 * Load the INI file and figure out if the computer in question is
                                 * compatible with the module.
                                 */
                                Zivios_Log::debug("computer.ini found for module: " .
                                    $serviceEntry->getProperty("emsmodulename"));

                                $cInf = new Zend_Config_Ini($aconf->modules . '/' .
                                    $serviceEntry->getProperty("emsmodulename") . '/config/computer.ini');

                                $distros = explode(",", $cInf->distros->supported);
                                $osCheck = $this->getComputerDistroId();

                                if (in_array($osCheck, $distros)) {
                                    if (!in_array($serviceEntry->getProperty("emsmodulename"), $existingModules))
                                        $availableServices[] = $serviceEntry;
                                }
                            }

                            break;

                            default:
                                throw new Zivios_Exception("Unknown EMS object");
                        }
                    }
                }
            }

            if (isset($zvForceBreak) && $zvForceBreak == 1) {
                /**
                 * Reassign parent to baseDN & break.
                 */
                Zivios_Log::DEBUG("Reassigning Parent and breaking Loop.");
                $parent = $saveParent;
                unset($saveParent);
                break;
            }

            if ($parent->getdn() != $lconf->basedn) {
                $parent = $parent->getParent();
                continue;
            } else {
                /**
                 * We've hit the base dn -- instantiate the following DN:
                 * ou=Master Services, ou=Core Control, ou=Zivios, base_dn &
                 * scan for services.
                 */
                $zvServDn = 'ou=Core Control,ou=Zivios,' . $lconf->basedn;
                $zvForceBreak = 1;
                $saveParent = $parent;
                $parent = Zivios_Ldap_Cache::loadDn($zvServDn);

                Zivios_Log::DEBUG("Making parent: " . $zvServDn);
                Zivios_Log::DEBUG("Continuing Search...");

                continue;
            }
        }

        return $availableServices;
    }


    public function getPlugin($plugname)
    {
        if (array_key_exists($plugname,$this->_plugins)) {
            return $this->_plugins[$plugname];
        }
        return null;
    }

    public function ifExists($plugname)
    {
        /**
         * returns true if plugin exists.
         * @return boolean
         */
        if (array_key_exists($plugname,$this->_plugins))
            return 1;

        return 0;
    }

    public function getModules()
    {
        $modules = $this->getProperty('emsmodules');

        if (!is_array($modules))
            return array($modules);
         else
            return $modules;
    }

    public function hasModule($modulename)
    {
        $modules = $this->getModules();
        return in_array($modulename,$modules);
    }

    public function addPlugin($plugin,Zivios_Transaction_Group $group,$description=null)
    {

        $classname = get_class($plugin);

        if (array_key_exists($classname,$this->_plugins)) {
            throw new Zivios_Exception('addPlugin - Plugin already exists');
        } else {
            $this->_plugins[$classname] = $plugin;
            $plugin->add($group);

            $this->addPropertyItem('emsplugins',$classname);
            $this->addPropertyItem('emsmodules',$plugin->returnModuleName());

            if ($description == null)
                $description = "Updating ".$this->getdn()." after Plugin $classname addition";
            
            $this->update($group,$description);
        }

        return $group;
    }

    public function removePlugin($pluginname,Zivios_Transaction_Group $tgroup,$description=null)
    {

        if (array_key_exists($pluginname,$this->_plugins)) {
            $plugin = $this->getPlugin($pluginname);
            $modulename = $plugin->returnModuleName();
            $this->removePropertyItem('emsplugins',$pluginname);
            $this->removePropertyItem('emsmodules',$modulename);

            if ($description == null)
                $description = "Updating ".$this->getdn()." after deleting $pluginname entries from Ldap";

            $plugin->delete($tgroup);
            $this->update($tgroup,$description);

            return $tgroup;
        } else {
            throw new Zivios_Exception("Plugin to be removed does not exist!");
        }
    }

    /*public function prepare($childcall=0) {


        foreach ($this->_plugins as $plug) {
            $plug->prepare();
        }
        parent::prepare(1);

    }*/
}
