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
 * @package		mod_kerberos
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: KerberosComputer.php 1009 2008-09-04 09:45:39Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-09-04 14:45:39 +0500 (Thu, 04 Sep 2008) $
 **/


class KerberosComputer extends Zivios_Plugin_Computer
{
	protected $_module = 'kerberos';

    public function getAttrs()
    {
        $attr = parent::getAttrs();

		/**
		 * Push Kerberos LDAP attributes to user obj.
		 */
		$attr[] = 'krb5principalname';
		$attr[] = 'krb5keyversionnumber';
		$attr[] = 'krb5realmname';
		$attr[] = 'krb5encryptiontype';
		$attr[] = 'krb5kdcflags';

        return $attr;
    }

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);

	}

	public function generateContextMenu()
	{
		return false;
	}

	function add(Zivios_Transaction_Group $tgroup,$description=null)
	{
		/**
		 * Set the servicemap accordingly.
		 */
		//ship default krb5.conf
        $krb5Config = Zend_Registry::get('krbMaster');
		$hostname = $this->getProperty('cn');

		/**
		 * Set krb params in userobj
		 */
		$this->setProperty('krb5principalname', 'host/'.$hostname."@".$krb5Config->realm);
		$this->setProperty('krb5keyversionnumber','1');
		$this->setProperty('krb5realmname', $krb5Config->realm);
		$this->setProperty('krb5encryptiontype', $krb5Config->encryptiontype);
		$this->setProperty('krb5kdcflags', $krb5Config->kdchostflags);
		$this->addObjectClass('krb5Realm');
        $this->addObjectClass('krb5Principal');
        $this->addObjectClass('krb5KDCEntry');

        return parent::add($tgroup,$description);

	}

    public function postAdd()
    {
        parent::postAdd();

        $service    = $this->getService();
        $krb5Config = Zend_Registry::get('krbMaster');
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $appConfig  = Zend_Registry::get('appConfig');

        $valarray = array();
        if ($service->getProperty('emsmodelclass') == 'KerberosReplicaService') {
            $valarray['krb5kdcserver'] = $service->getProperty('emskrbreplicahostname');
        } else {
            $valarray['krb5kdcserver'] = $service->getProperty('emskrbserver');
        }

        $valarray['krb5realm'] = $krb5Config->realm;
        $valarray['krb5kadminserver'] = $krb5Config->host;
        $valarray['domain'] = strtolower($krb5Config->realm);

        $valarray['ldap_base_dn'] = $ldapConfig->basedn;
        $tmplfile = $appConfig->modules .'/'. $this->_module . '/config/krb5.conf.tmpl';

        $file = Zivios_Util::renderTmplToCfg($tmplfile,$valarray);
        $this->_iniCompConfig();

        $this->_computerobj->putFileFromString($file,$this->_compConfig->krbconf,0644);
        Zivios_Log::debug("Successfully sent Kerberos config file");

        /*$service->setrandpw("host/".$this->getProperty("cn"));
        Zivios_Log::debug("Random Password Generated for host: ".$this->getProperty("cn"));

        $keytab = $service->extractKeyTab($this->getProperty("krb5principalname"));
        $this->_computerobj->putFileFromString($keytab,$this->_compConfig->krbkeytab,0644);
        */
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
		$sdn = 'cn=Zivios Kerberos,ou=master services,ou=core control,ou=zivios,'
			. $ldapConfig->basedn;

        $service = Zivios_Ldap_Cache::loadDn($sdn);
		return $service;
	}

	public function getKeytabPath()
	{
		$this->_iniCompConfig();
		return $this->_compConfig->krbkeytab;
	}
}

