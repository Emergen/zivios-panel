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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Os_Linux_Fedora extends Os_Linux
{
    protected $_krb5Handler, $_ldapHandler, $_bindHandler, $_ziviosHandler;

    public function __construct()
    {
        parent::__construct();
        $this->sysDistro = strtolower($this->_session->osDetails['distro']);
        return $this;
    }

    public function runSystemTests()
    {
        /**
         * Probe for required packages and ensure package level
         * configuration is kosher (as deemed by Zivios requirements).
         */
        $this->_probePackages();
    }

    public function iniCaSetup($data)
    {
        /**
         * Ensure a backup exists of the /etc/pki/tls and /etc/pki/CA folder.
         */
        $cbFolder = $this->linuxConfig->backupFolder . '/CA';

        if (!is_dir($cbFolder)) {
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $cbFolder; 
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not create backup folder for CA data.");
            }
        }

        /**
         * Ensure the "/etc/pki/tls and CA" folder has been backed up in the CA backup folder.
         * If not found, take a backup.
         */
        $sbFolder = $cbFolder . '/pki';

        if (!is_dir($sbFolder)) {
			$this->_createFolder($sbFolder,      '0755', 'root', 'root');
		
			$cmd = $this->_session->_cmds['cp'] . ' -a /etc/pki/* ' . $sbFolder;
			$rc = $this->_runLinuxCmd($cmd, true);

			if ($rc['exitcode'] != 0) {
				throw new Zivios_Exception("Could not backup /etc/pki/* to Zivios CA backup folder.");
			}
        }

        /**
         * Ensure /etc/pki/tls does not exist -- we will be creating a symlink to it at
         * a later stage.
         */
        if (is_dir('/etc/pki/tls/certs')) {
			$this->_removeRecursive('/etc/pki/tls/certs');
		}
		
		if (is_dir('/etc/pki/tls/private')) {
            $this->_removeRecursive('/etc/pki/tls/private');
        }
		
		if (is_dir('/etc/pki/CA')) {
            $this->_removeRecursive('/etc/pki/CA');
			
        }
		
		$this->_createFolder('/etc/pki/CA','0755', 'root' ,'root');
		$this->_createFolder('/etc/pki/CA/certs' , '0755' , 'root' ,'root');
		$this->_createFolder('/etc/pki/CA/private' , '0750' , 'root' ,'ssl-cert');
		
        /**
         * Get the Heimdal Service and initialize CA.
         */
		 
        $ubuntuConfig  = $this->_getDistroDetails();
        $webuser       = $ubuntuConfig->webuser;
        $webgroup      = $ubuntuConfig->webgroup;
        $krb5i         = $this->getKrb5Handler()->checkCaStatus()
                                                ->initializeCa($webuser)
                                                ->generateCaCert($data["califetime"])
                                                ->generateWebCert()
                                                ->generateKdcCert();
    
	
		$caConfig = $krb5i->getCaConfig();
		$cacertpubkeyloc = $caConfig->anchors . '/' . $caConfig->rootPubCert;
        $cacertprvkeyloc = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;
		$publicCerts = $caConfig->publicCerts;
		$prvCerts = $caConfig->privateCerts;
		
		$this->_softLink($publicCerts,'/etc/pki/tls/certs');
		$this->_softLink($prvCerts,'/etc/pki/tls/prviate');
		$this->_softLink($cacertpubkeyloc,'/etc/pki/CA/certs/');
		$this->_softLink($cacertprvkeyloc,'/etc/pki/CA/private/');
		
		
    }
	

    /**
     * Function rewrites the default apache vhost template, enabling
     * SSL (and only SSL) access to the system.
     *
     * @return void
     */
    public function iniWebssl()
    {
        $wbFolder = $this->linuxConfig->backupFolder . '/apache';

        if (!is_dir($wbFolder)) {
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $wbFolder; 
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not create backup folder for Apache data.");
            }
        }

        /**
         * Ensure the "default" vhost has been backed up.
         */
        if (!file_exists($wbFolder . '/default')) {
            $cmd = $this->_session->_cmds['cp'] . ' /etc/httpd/conf.d/zivios.conf ' . $wbFolder;
            $rc = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not backup default vhost to Zivios apache backup folder.");
            }
        }

		$template = APPLICATION_PATH . '/library/Zivios/Install/Templates/apache/vhostUbuntu.tmpl';
        $caConfig = $this->getKrb5Handler()->getCaConfig();
        $hostname = $this->_session->localSysInfo["hostname"];

        /**
         * Initialize Zivios CA service.
         */
        $sslcert = $caConfig->publicCerts  . '/' . $hostname . '.pem';
		$sslkey  = $caConfig->privateCerts . '/' . $hostname . '.key';

		if (!file_exists($template) || !is_readable(($template)))
            throw new Zivios_Exception("Could not find apache vhost template for Ubuntu.");

		if (!file_exists($sslcert) || !is_readable(($sslcert)))
            throw new Zivios_Exception("Could not find / read web host public certificate");

		if (!file_exists($sslkey) || !is_readable(($sslkey)))
            throw new Zivios_Exception("Could not find / read web host private certificate");

		$vals = array();
		$vals['ip_address']   = $this->_session->localSysInfo['ip'];
		$vals['server_admin'] = 'webmaster@'.$hostname;
		$vals['server_name']  = $hostname;
		$vals['ssl_pubcert']  = $sslcert;
		$vals['ssl_prvkey']   = $sslkey;
		$vals['doc_root']     = BASE_PATH . '/web';
        $vals['error_log']    = APPLICATION_PATH . '/log/error_log';
        $vals['access_log']   = APPLICATION_PATH . '/log/access_log';

		$vhosttemplate = Zivios_Util::renderTmplToCfg($template,$vals);      
        $tmpVhostFile = $this->linuxConfig->tmpFolder . '/' . 'zivios-apache.vhost';

		if (!$fp = fopen($tmpVhostFile, "w"))
            throw new Zivios_Exception("Could not open file for writing in tmp folder.");

		if (fwrite($fp, $vhosttemplate) === FALSE)
            throw new Zivios_Exception("Could not write apache vhost template to file.");

		fclose($fp);

		/**
		 * Copy the SSL enabled vhost file, overwriting the original
		 * apache vhost file.
		 */
		$cmd = $this->_session->_cmds['cp'] . ' ' . $tmpVhostFile . ' /etc/httpd/conf.d/zivios.conf';
		$rc = $this->_runLinuxCmd($cmd,true);
		if ($rc['exitcode'] != 0)
            throw new Zivios_Exception("Could not copy Zivios vhost file to /etc/httpd/conf.d/zivios.conf");

    }

    /**
     * Initialize LDAP setup and start the service.
     *
     * @return void
     */
    public function iniLdapSetup($data)
    {
        $config  = $this->_getDistroDetails();
        $controlScript = $config->initSlapd;
        $nsswitchFile  = $config->nsswitchLocation;
		$ldapconffile = $config->ldapconffile;

        /**
         * Note: the zadmin password is received as part of this
         * data array. We need to store this in our session as kerberos
         * initialization will requite it.
         */
        $this->_session->zadminPass = $data['zadminpass'];

        $ldapi = $this->getLdapHandler()->clearLdapData()
                                        ->iniLdapConfig($data)
                                        ->serviceAction($controlScript, 'stop')
                                        ->serviceAction($controlScript, 'start')
                                        ->importLdifs($data)
                                        ->addDataTemplate($data)
                                        ->serviceAction($controlScript, 'restart')
                                        ->fixAci($data)
                                        ->updateNis($nsswitchFile,$ldapconffile);
    }

    /**
     * Initialize Kerberos setup and start the service
     *
     * @return void
     */
    public function iniKrb5Setup($data)
    {
        $ubuntuConfig  = $this->_getDistroDetails();
        $controlScript = $ubuntuConfig->initKrb5;
        $krb5Link      = $ubuntuConfig->krb5ConfLocation;
        $hostKeytab    = $ubuntuConfig->hostKeytab;

        /**
         * Generate and register the password for the bind user.
         */
        $bindpass                 = Zivios_Util::randomString(8);
        $this->_session->bindPass = $bindpass;
        $zadminpass               = $this->_session->zadminPass;

        $krb5i = $this->getKrb5Handler()->checkKrb5Setup()
                                        ->iniKrb5Config()
                                        ->createLink($krb5Link)
                                        ->iniKrb5Data()
                                        ->extractKeytabs($hostKeytab)
                                        ->finalizeKrb5Conf()
                                        ->setPassword('binduser', $bindpass)
                                        ->setPassword('zadmin',   $zadminpass)
                                        ->startHeimdal($controlScript);
    }

    /**
     * Initialize Bind setup and start the service
     *
     * @return void
     */
    public function iniBindSetup($data)
    {
        $ubuntuConfig  = $this->_getDistroDetails();
        $controlScript = $ubuntuConfig->initBind;
        $binduserPass  = $this->_session->bindPass;
        $webuser       = $ubuntuConfig->webuser;
        $webgroup      = $ubuntuConfig->webgroup;

        $dnsi = $this->getBindHandler()->iniBindConfig($data, $binduserPass, $webuser, $webgroup)
                                       ->serviceAction($controlScript, "restart");
    }

    public function iniZiviosSetup($data)
    {
        $ubuntuConfig = $this->_getDistroDetails();
        $agentControl = $ubuntuConfig->initAgent;
        $webuser      = $ubuntuConfig->webuser;
        $webgroup     = $ubuntuConfig->webgroup;

        $zvi = $this->getZiviosHandler()->iniAgentSetup()
                                        ->serviceAction($agentControl, "restart")
                                        ->iniPanelSetup($data);

    }


    public function getLdapHandler()
    {
        if (null === $this->_ldapHandler) {
            require_once dirname(__FILE__) . '/Services/Openldap.php';
            $this->_ldapHandler = new Os_Linux_Services_Openldap();
        }

        return $this->_ldapHandler;
    }

    public function getKrb5Handler()
    {
        if (null === $this->_krb5Handler) {
            require_once dirname(__FILE__) . '/Services/Heimdal.php';
            $this->_krb5Handler = new Os_Linux_Services_Heimdal();
        }

        return $this->_krb5Handler;
    }

    public function getBindHandler()
    {
        if (null === $this->_bindHandler) {
            require_once dirname(__FILE__) . '/Services/Bind.php';
            $this->_bindHandler = new Os_Linux_Services_Bind();
        }

        return $this->_bindHandler;
    }

    public function getZiviosHandler()
    {
        if (null === $this->_ziviosHandler) {
            require_once dirname(__FILE__) . '/Services/Zivios.php';
            $this->_ziviosHandler = new Os_Linux_Services_Zivios();
        }

        return $this->_ziviosHandler;
    }

    protected function _probePackages()
    {
        $packageManager   = new Os_Linux_PackageHelper_Rpm();
        $redhatBased      = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', 'redhat_based');
        $requiredPackages = explode (",",$redhatBased->requiredPackages);
        $fedoraConfig     = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', 'fedora');
        $fedoraPackages   = explode (",",$fedoraConfig->requiredPackages);

        if (!empty($fedoraPackages))
            $requiredPackages = array_merge($requiredPackages,$fedoraPackages);
        
        foreach ($requiredPackages as $package) {
            if ($package != "") {
                if (!$packageManager->hasPackage($package))
                    throw new Zivios_Error("Required package: " . $package . " not found.");
            }
        }
    }
}

