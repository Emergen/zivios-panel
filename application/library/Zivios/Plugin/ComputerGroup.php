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

abstract class Zivios_Plugin_ComputerGroup extends Zivios_Plugin
{
    protected $_groupobj, $_serviceobj, $_groupConfig;

    abstract public function generateContextMenu();

    public function init(EMSPluginManager $groupobj)
    {
        parent::init($groupobj);
        $this->_groupobj = $this->_pmobj;
    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();

        $attrs[] = 'emsservicemap';
        return $attrs;
    }

    public function getService()
    {
        if (!isset($this->_serviceobj)) {
            $array = $this->searchMap();
            if ($array == null) {
                throw new Zivios_Exception("No services found for group: " . $this->_pmobj->getdn());
            }
            Zivios_Log::debug("Search map returned array : " . implode(",",$array));
            $this->_serviceobj = Zivios_Ldap_Cache::loadDn($array[1]);
        }

        return $this->_serviceobj;
    }

    public function unlinkService()
    {
        $serventry = $this->searchMap();
        $serventry = implode(":",$serventry);
        $this->removePropertyItem('emsservicemap',$serventry);
    }

    public function linkToService(EMSService $service)
    {
        $classname = get_class($service);
        $pluginclass = get_class($this);
        $dn = $service->getdn();
        if ($this->searchMap() != null) {
            throw new Zivios_Exception("Plugin Already linked!!!");
        } else {
            /**
             * No Entry found, begin linking
             */

            $parameter = $this->getParameter('emsservicemap');
            $str = "$pluginclass:$dn:$classname";
            $parameter->addValue($str);
            $this->_serviceobj = $service;
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
                $maparray = $map;
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

    abstract function addToGroup(EMSComputer $computer,Zivios_Transaction_Group $group);

    abstract function removeFromGroup(EMSComputer $computer,Zivios_Transaction_Group $tgroup);

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        Zivios_Log::debug("Delete Called! on " . get_class($this));


        $tgroup = $this->removeUserPluginConditional($tgroup);
        $this->unlinkService();
        $tgroup = $this->update($tgroup,$description);
        return $tgroup;
    }

    public function returnDisplayName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->general->displayname;
    }

    public function returnModuleName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->general->modulename;
    }

    public function getUserPluginName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->libraries->user;
    }

    protected function _iniGroupConfig()
    {
        if (!$this->_groupConfig instanceof Zend_Config_Ini) {
            if (!isset($this->_module) || $this->_module == '')
                throw new Zivios_Exception("Variable _module MUST be set by your calling class.");

            $this->appConfig = Zend_Registry::get('appConfig');
            if (file_exists($this->appConfig->bootstrap->modules .
                $this->_module . '/config/computergroup.ini')) {

                /**
                * Instantiate cfg object for plugin
                */

                $this->_groupConfig = new Zend_Config_Ini($this->appConfig->bootstrap->modules .
                    $this->_module . '/config/computergroup.ini');
            } else {
                throw new Zivios_Exception('Module: ' . get_class($this) .
                    ' does not provide a comptuergroup.ini.');
            }
        }
    }
}
