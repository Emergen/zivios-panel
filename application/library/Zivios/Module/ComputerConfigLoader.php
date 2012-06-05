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

class Zivios_Module_ComputerConfigLoader
{
    public static function initPluginConfigs($modules)
    {
        if (!is_array($modules) || empty($modules))
            throw new Zivios_Exception("Empty array passed. No modules found");

        $registry = Zend_Registry::get("appConfig");
        $pluginConfigs = array();

        foreach ($modules as $modulename) {
            /**
             * Ensure that a computer.ini is provided by the plugin. Load
             * computer.ini and return Zend_Config_Ini object to caller.
             */
            if (file_exists($registry->bootstrap->modules . $modulename .
                '/config/computer.ini')) {
                $pluginConfigs[$modulename] = new Zend_Config_Ini($registry->bootstrap->modules .
                    $modulename . '/config/computer.ini');
            }
        }

        return $pluginConfigs;
    }
}

