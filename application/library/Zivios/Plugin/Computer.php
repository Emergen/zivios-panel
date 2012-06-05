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
 * @subpackage  Core
 **/

abstract class Zivios_Plugin_Computer extends Zivios_Plugin
{
    public $_computerobj;
    protected $_serviceCfgGeneral, $_compConfig, $_commAgent;

    public function init(EMSPluginManager $comp)
    {
        parent::init($comp);
        $this->_computerobj = $this->_pmobj;

        /**
         * Initialize service and computer configuration defaults
         */
        $this->_iniServiceConfig();
        $this->_iniCompConfig();
    }

    public function getAttrs()
    {
        $attrs =parent::getAttrs();
        $attrs[] = 'emsservicemap';
        return $attrs;
    }

    public function returnDisplayName()
    {
        return $this->_serviceCfgGeneral->displayname;
    }

    public function returnModuleName()
    {
        return $this->_serviceCfgGeneral->modulename;
    }

    protected function _iniServiceConfig()
    {
        if (!$this->_serviceCfgGeneral instanceof Zend_Config_Ini) {

            if (!isset($this->_module) || $this->_module == '')
                throw new Zivios_Exception("Variable _module MUST be set by your calling class.");

            $this->appConfig = Zend_Registry::get('appConfig');

            /**
             * Instantiate cfg object for plugin
             */
            $this->_serviceCfgGeneral = new Zend_Config_Ini($this->appConfig->modules . '/' .
                $this->_module . '/config/service.ini', 'general');
        }
    }

    protected function _iniCompConfig()
    {
        if (!$this->_compConfig instanceof Zend_Config_Ini) {

            if (!isset($this->_module) || $this->_module == '') {
                throw new Zivios_Exception("Variable _module *MUST* be set by your calling class.");
            }

            $cfgSection = $this->_computerobj->getComputerDistroId();
            $appConfig = Zend_Registry::get('appConfig');

            $this->_compConfig = new Zend_Config_Ini(
                $appConfig->modules .'/'. $this->_module . '/config/computer.ini', $cfgSection);
        }
    }

    /**
     * Initialize communication with the master agent
     */
    protected function _initCommAgent()
    {
        if (!isset($this->_module))
            throw new Zivios_Exception('$_module MUST be defined by the calling class');

        if (!$this->_commAgent instanceof Zivios_Comm_Agent) {
            $this->_commAgent = new Zivios_Comm_Agent($this->_computerobj->getIp(),
                $this->_module);
        }
    }

    public function getService()
    {
        $array = $this->searchMap();
        $service = null;
        if ($array[1] != null && $array[1] != "") {
            $service = Zivios_Ldap_Cache::loadDn($array[1]);
        }

        return $service;
    }

    public function linkToService(EMSService $service)
    {
        $classname = get_class($service);
        $pluginclass = get_class($this);
        $dn = $service->getdn();

        if ($this->searchMap() != null)
            throw new Zivios_Exception("Service already linked to System.");
        else {
            $parameter = $this->getParameter('emsservicemap');
            $str = $pluginclass . ':' . $dn . ':' . $classname;
            $parameter->addValue($str);
        }
    }

    private function searchMap()
    {
        $pluginclass = get_class($this);
        $map = $this->getProperty('emsservicemap');
        $maparray = array();

        if ($map != null) {
            if (!is_array($map)) {
                $maparray[0] = $map;
            } else {
                $maparray =$map;
            }

            foreach ($maparray as $entry) {
                $array = explode(":",$entry);
                if (trim($array[0]) == $pluginclass) {
                    return $array;
                }
            }
        }
        return null;
    }

    public function getComputer()
    {
        return $this->_computerobj;
    }

    public function _getSshHandler()
    {
        $this->_computerobj->_getSshHandler();
    }

    public function _iniSshHandler()
    {
        $this->_computerobj->_iniSshHandler();
    }

    public function _shellCmd($cmd, $trim_last=false, $timeout=10, $expect="", $cmdlog=0)
    {
        return $this->_computerobj->_shellCmd($cmd,$trim_last,$timeout,$expect,$cmdlog);
    }

    public function _remoteScp($srcFile, $dstFile, $direction)
    {
        return $this->_computerobj->_remoteScp($srcFile,$dstFile,$direction);
    }

    abstract public function generateContextMenu();
}
