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

class Zivios_Controller_Service extends Zivios_Controller
{
    protected $_serviceConfig;

    protected function _init()
    {
        $this->_initServiceConfig();
    }

    protected function _initServiceConfig()
    {
        $appConfig = Zend_Registry::get('appConfig');

        if (!$this->_serviceConfig instanceof Zend_Config_Ini) {
            $this->_serviceConfig = new Zend_Config_Ini($appConfig->modules . '/' 
                . $this->_module . '/config/service.ini');
        }
    }

    /**
     * Returns array of computer objects which are compatible with the
     * service module in question, or if no computers found, a list of
     * compatible computer operating systems for the module.
     *
     * @return array (of objects) $compList | string $compList
     */
    protected function _getCompatiableComputers($obj)
    {
        $compComputers = explode(',', rtrim($this->_serviceConfig->distros->supported, ','));

        if (empty($compComputers)) {
            Zivios_Log::info("No Compatible Computers found for Service: " .
                $this->_serviceConfig->general->displayname);
            return array();
        }

        // Establish Search Base
        $searchFrom = $obj->getParent();

        // Generate the required ldap filter.
        $filter = '(&(objectclass=EMSComputer)(|';

        foreach ($compComputers as $cc) {
            $ccInfo = explode ('-', $cc);
            $filter .= '(&(emscomputerdistro='.$ccInfo[0].')(emsdistrocodename='.$ccInfo[1].'))';
        }

        $filter .= '))';

        // Run search.
        $compList = $obj->getAllChildren($filter,"SUB",null,$searchFrom->getdn());

        if (empty($compList)) {
            return $this->_serviceConfig->distros->supported;
        } else {
            return $compList;
        }
    }
}
