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

class Zivios_Module_GroupConfigLoader
{
    public static function initPluginConfigs($modules)
    {
        if (!is_array($modules) || empty($modules))
            throw new Zivios_Exception("Empty array passed. No modules found");

        $registry = Zend_Registry::get("appConfig");
        $pluginConfigs = array();

        foreach ($modules as $modulename) {
            /**
             * Ensure that a group.ini is provided by the plugin. Load
             * group.ini and return Zend_Config_Ini object to caller.
             */
            if (file_exists($registry->modules . '/' . $modulename .
                '/config/group.ini')) {
                $pluginConfigs[$modulename] = new Zend_Config_Ini($registry->modules . '/' .
                    $modulename . '/config/group.ini',"general");
            }
        }

        return $pluginConfigs;
    }
    /**
    * This requires a services array and builds a array using zendconfig object suitable 
    * for use with our group plugin view. The return array contains the elements: 
    * srvDn : dn of the service to Add
    * url: action to post to
    * displayname: cn of the service to be added
    */
    
    public static function initAvailServicesConfigs($services)
    {
        if (!is_array($services) || empty($services))
            throw new Zivios_Exception("Empty array passed. No modules found");
        
        $registry = Zend_Registry::get("appConfig");
        $serviceConfigs = array();
        
        foreach ($services as $service) {
            $modulename = $service->returnModuleName();
            Zivios_Log::debug("iterating service : ".$service->getdn());
            if (file_exists($registry->modules . '/' . $modulename .
                '/config/group.ini')) {
                 $config = new Zend_Config_Ini($registry->modules . '/' .
                    $modulename . '/config/group.ini',"general");
                 $obj = new StdClass();
                 $obj->url = $config->linkurl;
                 $obj->srvdn = $service->getdn();
                 $obj->displayname = $service->returnDisplayName();
                 $obj->tabid = $config->tabid;
                 $obj->icon = $config->icon;
                 $serviceConfigs[] = $obj;
                }
        }
        
        return $serviceConfigs;

    }
}

