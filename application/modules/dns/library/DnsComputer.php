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
 * @package     mod_dns
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class DnsComputer extends Zivios_Plugin_Computer
{
    protected $_module = 'dns';

    public function getAttrs()
    {
        return parent::getAttrs();
    }

    public function generateContextMenu()
    {
        return false;
    }

    function postAdd()
    {
        parent::postAdd();

        $service = $this->getService();
        
        if ($service->getProperty('emsmodelclass') == 'DnsReplicaService') {
            $domain = $service->getPrimaryRootZone();
        } else {
            $domain = $service->getProperty('emsdnsrootzone');
        }

         $nsip = $service->getMasterComputer()->getIp();

         $valarray = array();
         $valarray["domain"] = $domain;
         $valarray["nsip"]   = $nsip;

         $appConfig = Zend_Registry::get('appConfig');
         $tmplfile = $appConfig->modules .'/'. $this->_module . '/config/resolv.conf.tmpl';

         $file = Zivios_Util::renderTmplToCfg($tmplfile,$valarray);

         $this->_iniCompConfig();
         $this->_computerobj->putFileFromString($file,$this->_compConfig->dnsresolve,0644);
    }

    /**
     * Searches for the master service from the basedn under the Zivios
     * Master Services container and returns the service to caller.
     *
     * @return EMSService $masterService
     */
    public function getMasterService()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');
        /**
         * Core service DNs are hardcoded in the system.
         */
        $sdn = 'cn=Zivios DNS,ou=master services,ou=core control,ou=zivios,'
            . $ldapConfig->basedn;

        $service = Zivios_Ldap_Cache::loadDn($sdn);
        return $service;
    }
}


