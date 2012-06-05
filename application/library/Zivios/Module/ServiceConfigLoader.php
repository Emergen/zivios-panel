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

class Zivios_Module_ServiceConfigLoader
{
    /**
     * Initialize Service availability by scanning all module
     * directories, seeking service.ini in the config directory.
     *
     * @return array of objects $availableServiceConfigs
     */
    public static function initServiceAvailability()
    {
        $registry = new Zend_Config_Ini(APPLICATION_PATH . '/config/app.config.ini',
                      null, array('skipExtends'        => true, 'allowModifications' => true));
        $serviceConfigs = array();

        if (!$dir = dir($registry->general->modules))
            throw new Zivios_Exception("Modules directory not found.");

        // Iterate the directory and establish module listing
        while (false !== ($entry = $dir->read())) {
            if($entry != '.' && $entry != '..') {
                if (file_exists($registry->general->modules . '/'  . $entry .
                    '/config/service.ini')) {
                    $serviceConfigs[$entry] = new Zend_Config_Ini(
                        $registry->general->modules . '/' .  $entry . '/config/service.ini'
                    );
                    Zivios_Log::info("Service Module Available: " .
                        $serviceConfigs[$entry]->general->displayname);
                } else {
                    Zivios_Log::info("No service.ini for module: " . $entry);
                }
            }
        }

        return $serviceConfigs;
    }

    /**
     * Function takes a module name and scans to ensure the module exists.
     * Further checks are done to ensure a $module/config/service.ini file
     * exists, which is read via Zend_Config_Ini, returning the object.
     *
     * @param string $module
     * @return object $config
     */
    public static function initServiceConfig($module)
    {
        $registry = Zend_Registry::get("appConfig");
        if (file_exists($registry->bootstrap->modules . $entry .
            '/config/service.ini')) {
            return new Zend_Config_Ini($registry->bootstrap->modules . $entry .
                '/config/service.ini');
        } else
            throw new Zivios_Exception("service.ini not found for " . $module);
    }
}

