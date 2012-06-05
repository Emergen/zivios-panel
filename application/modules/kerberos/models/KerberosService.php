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
 * @package     mod_kerberos
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class KerberosService extends EMSService
{
    public    $mastercomp, $krb5realm;
    protected $_module = 'kerberos';

    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emskrbserver';
        $attrs[] = 'krb5realmname';
        $attrs[] = 'emsmastercomputerdn';
        $attrs[] = 'emskrbpasslifedays';
        $attrs[] = 'emskrbpasspolicies';
        $attrs[] = 'emskrbpassminlength';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();

        $param = $this->getParameter('emskrbserver');
        $param->addValidator(new Zend_Validate_Hostname(Zend_Validate_Hostname::ALLOW_DNS |
            Zend_Validate_Hostname::ALLOW_IP), Zivios_Validate::errorCode2Array('hostname',$param->dispname));

        $this->krb5realm = $this->getProperty("krb5realmname");
        if ($this->krb5realm == NULL) {
            throw new Zivios_Exception("No realm available for Master Kerberos Service.");
        }

        $mcdn = $this->getProperty('emsmastercomputerdn');
        if ($mcdn != NULL) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
        } else {
            throw new Zivios_Exception('Core Service Kerberos missing Master Computer DN');
        }
    }

    public function setpw($user,$pass)
    {
        /* NEW ZIVIOS DESIGN DOES NOT PERMIT WRITE ACCESS TO KADMIN
        $this->_initCommAgent();
        return $this->_commAgent->setpw($user,$pass,$this->krb5realm);
        */
    }

    public function setRandPw($host)
    {
        /* NEW ZIVIOS DESIGN DOES NOT PERMIT WRITE ACCESS TO KADMIN
        $this->_initCommAgent();
        return $this->_commAgent->setrandpw($host,$this->krb5realm);
        */
    }

    public function extractKeytab($principal)
    {
        /* NEW ZIVIOS DESIGN DOES NOT PERMIT WRITE ACCESS TO KADMIN
        $this->_initCommAgent();
        $resp = $this->_commAgent->extractkeytab($principal);
        return base64_decode($resp);
        */
    }

    public function getServerTime()
    {
        $this->_initCommAgent();
        return $this->_commAgent->currentTime();
    }

    public function getServiceStatus()
    {
        $this->_initCommAgent();
        return $this->_commAgent->status();
    }

    /**
     * Stop the key distribution center service (kdc)
     *
     * return boolean $status
     */
    public function stopKdc()
    {
        $this->_initCommAgent();
        return $this->_commAgent->stopKdc();
    }

    public function startKdc()
    {
        $this->_initCommAgent();
        return $this->_commAgent->startKdc();
    }

    public function startKadmind()
    {
        $this->_initCommAgent();
        return $this->_commAgent->startKadmind();
    }

    public function stopKadmind()
    {
        $this->_initCommAgent();
        return $this->_commAgent->stopKadmind();
    }

    public function startKpasswdd()
    {
        $this->_initCommAgent();
        return $this->_commAgent->startKpasswdd();
    }

    public function stopKpasswdd()
    {
        $this->_initCommAgent();
        return $this->_commAgent->stopKpasswdd();
    }
    
    public function updateCfg()
    {
    	$valarray = array();
    	$ldapConfig = Zend_Registry::get("ldapConfig");
    	$caConfig = Zend_Registry::get("casystem");
    	
    	$basedn = $ldapConfig->basedn;
    	$ca = $caConfig->CA_pubkey;
    	
    	$realm = $this->getProperty('krb5realmname');
    	$host = $this->mastercomp->getProperty('cn');
    	
    	$cakeyhost = 'kdc.'.strtolower($realm);
    	
    	$valarray['krb5realm'] = strtoupper($realm);
    	$valarray['lckrb5realm'] = strtolower($realm);
    	$valarray['kdc_host'] = $host;
    	$valarray['kadmin_host'] = $host;
    	$valarray['db_base'] = $basedn;
    	$valarray['zivios_ca_pubkey'] = $ca;
    	$valarray['kdc_pubkey'] = "/opt/zivios/zivios-ca/public/".$cakeyhost.".crt";
    	$valarray['kdc_prvkey'] = "/opt/zivios/zivios-ca/private/".$cakeyhost.".key";
    	
    	$policies = $this->getProperty('emskrbpasspolicies',1);
    	if (in_array('external-check',$policies))
    		$valarray['kdc_externcheckpolicy'] = "policies = external-check";
    	else
    		$valarray['kdc_externcheckpolicy'] = "";
    		
		$valarray['kdc_passmaxlifedays'] = $this->getProperty('emskrbpasslifedays');
		$valarray['kdc_passminlength'] = $this->getProperty('emskrbpassminlength');
		
		$appConfig = Zend_Registry::get('appConfig');
        $tmplfile = $appConfig->modules .'/'. $this->_module . '/config/krb5kdc.conf.tmpl';
        
		$file = Zivios_Util::renderTmplToCfg($tmplfile,$valarray);
		$krb5conf = $this->_serviceCfgGeneral->kdcconf;
		$this->mastercomp->putFileFromString($file,$krb5conf,0644);
		
    }

	
}
