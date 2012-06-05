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
 * @package     mod_openldap
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class OpenldapComputer extends Zivios_Plugin_Computer
{
    protected $_module = 'openldap';
    private   $_service;

    public function generateContextMenu()
    {
        return false;
    }

    public function add(Zivios_Transaction_Group $tgroup, $description=null)
    {
        /*
        $handler = parent::add($tgroup, $description);
        return $this->updateCfg($tgroup);
        */
        if ($description === null && $description != '') {
            $description = 'Adding OpenLDAP Computer plugin.';
        }

        parent::add($tgroup, $description);
    }

    public function postAdd()
    {
        parent::postAdd();

        $service = $this->getService();
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $appConfig  = Zend_Registry::get('appConfig');

        $vals = array();
        $vals['ldap_base_dn'] = $ldapConfig->basedn;

        if ($service->getProperty('emsmodelclass') == 'OpenldapReplicaService') {
            $vals['ldap_host'] = $service->getProperty('emsldapreplicahostname');
        } else {
            $vals['ldap_host'] = $ldapConfig->host;
        }

        $ldapconftempl     = $appConfig->modules . '/' . $this->_module . '/config/ldap.conf.tmpl';
        $nsswitchconftempl = $appConfig->modules . '/' . $this->_module . '/config/nsswitch.conf.tmpl';

        $this->_iniCompConfig();
        $ldapconffile = Zivios_Util::renderTmplToCfg($ldapconftempl, $vals);
        $nsswitchconffile = Zivios_Util::renderTmplToCfg($nsswitchconftempl, array());

        $this->_computerobj->putFileFromString($ldapconffile, $this->_compConfig->ldapconf,0644);
        $this->_computerobj->putFileFromString($nsswitchconffile, $this->_compConfig->nsswitchconf,0644);
    }
    
    public function update(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $handler = parent::update($tgroup,$description);
        return $this->updateCfg($tgroup);
    }

    public function updateCfg(Zivios_Transaction_Group $tgroup)
    {
        $titem = $tgroup->newTransactionItem("Updating Ldap/NSS Configuration and transferring to Host");
        $titem->addObject('ldapcomputer',$this);
        $titem->addCommitLine('$this->ldapcomputer->_updateCfg();');
        $titem->commit();
        return $tgroup;
    }

    public function _updateCfg()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $appConfig = Zend_Registry::get('appConfig');

        $valarray = array();
        $valarray['ldap_base_dn'] = $ldapConfig->basedn;
        $valarray['ldap_host'] = $ldapConfig->host;

        $ldapconftempl = $appConfig->modules . '/' . $this->_module . '/config/ldap.conf.tmpl';
        $nsswitchconftempl = $appConfig->modules . '/' . $this->_module . '/config/nsswitch.conf.tmpl';

        $this->_iniCompConfig();
        $ldapconffile = Zivios_Util::renderTmplToCfg($ldapconftempl,$valarray);
        $nsswitchconffile = Zivios_Util::renderTmplToCfg($nsswitchconftempl,array());

        $this->_computerobj->putFileFromString($ldapconffile,$this->_compConfig->ldapconf,0644);
        $this->_computerobj->putFileFromString($nsswitchconffile,$this->_compConfig->nsswitchconf,0644);
    }

    /**
     * Update the libnss-ldap configuration file based on passed values. 
     * Function is meant to be called after OpenLDAP replication initialization
     * to ensure the target replica host communicates with the location machine
     * for NIS information.
     *
     */
    public function updateReplicaLdapConfig($nssldapdata)
    {
        if (!is_array($nssldapdata) || empty($nssldapdata)) {
            throw new Zivios_Exception('NSS LDAP data needs to be passed as a non-empty Array.');
        }

        $appConfig = Zend_Registry::get('appConfig');

        $nssldapconftempl  = $appConfig->modules . '/' . $this->_module . '/library/templates/replica/libnss-ldap.conf.tmpl';
        $nsswitchconftempl = $appConfig->modules . '/' . $this->_module . '/config/nsswitch.conf.tmpl';
        $ldapconftempl     = $appConfig->modules . '/' . $this->_module . '/library/templates/replica/ldap.conf.tmpl';

        $this->_iniCompConfig();
        $nssldapconffile  = Zivios_Util::renderTmplToCfg($nssldapconftempl, $nssldapdata);
        $ldapconffile     = Zivios_Util::renderTmplToCfg($ldapconftempl, $nssldapdata);
        $nsswitchconffile = Zivios_Util::renderTmplToCfg($nsswitchconftempl, array());

        $this->_computerobj->putFileFromString($nssldapconffile,  $this->_compConfig->nssldapconf, 0644);
        $this->_computerobj->putFileFromString($ldapconffile,     $this->_compConfig->ldapconf, 0644);
        $this->_computerobj->putFileFromString($nsswitchconffile, $this->_compConfig->nsswitchconf, 0644);
        $this->_computerobj->putFileFromString($ldapconffile,     '/opt/zivios/openldap/etc/openldap/ldap.conf', 0644);
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
        $sdn = 'cn=Zivios Directory,ou=master services,ou=core control,ou=zivios,'
            . $ldapConfig->basedn;
        Zivios_Log::debug("Loading service dn... " . $sdn);
        $service = Zivios_Ldap_Cache::loadDn($sdn);
        return $service;
    }
}

