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
 * @package     ZiviosInstaller
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Os_Linux_Redhat extends Os_Linux
{
    protected $_krb5Handler, $_ldapHandler, $_bindHandler, $_ziviosHandler,
              $_packageManager;

    public function __construct()
    {
        parent::__construct();
        $this->sysDistro = strtolower($this->_session->osDetails['distro']);
        return $this;
    }
    
    /**
     * Function runs all distribution level tests to ensure the base system
     * is ready for Zivios.
     * 
     * @return void
     */
    public function runSystemTests()
    {
        // Probe for required packages and ensure package level
        // configuration is kosher (as deemed by Zivios requirements).
        $this->_probePackages();
    }
    
    /**
     * Function initializes Certificate Authority settings for Debian based
     * distributions.
     *
     * @return void
     */
    public function iniCaSetup($data)
    {
        // Initialize CA details for distribution
        $distroConfig = $this->_getDistroDetails();
        $caBase       = $distroConfig->pki_base;
        $caCerts      = $distroConfig->anchors;
        $caKeys       = $distroConfig->anchorkey;
        $pubCerts     = $distroConfig->publicCerts;
        $prvKeys      = $distroConfig->privateCerts;

        // System localhost public & private keys. Will be linked to 
        // generated host keys by the Zivios CA
        $lcpubcert = $pubCerts . '/localhost.crt';
        $lcprvcert = $prvKeys  . '/localhost.key';

        // Ensure we backup system PKI dir.
        $cbFolder = $this->linuxConfig->backupFolder . '/CA';

        if (!is_dir($cbFolder)) {
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $cbFolder; 
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception ("Could not create backup folder for CA data.");
            }
        }

        $sbFolder = $cbFolder . '/pki';

        if (!is_dir($sbFolder)) {
            $cmd = $this->_session->_cmds['mv'] . ' ' . $caBase . ' ' . $cbFolder;
            $rc = $this->_runLinuxCmd($cmd,true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not backup ".$caBase." to Zivios CA backup folder.");
            }
        }

        // Ensure 'caBase dir' does not exist -- we will be creating a symlink to it at
        // a later stage.
        if (is_dir($caBase)) {
            $this->_removeRecursive($caBase);
        }
        
        // Recreate PKI folder structure
        $this->_createFolder($caBase,  '0755', 'root', 'root');
        $this->_createFolder($caBase . '/tls', '0755', 'root', 'root');
        $this->_createFolder($caBase . '/CA/private', '0770', 'root','ssl-cert');
        
        // Get the Heimdal Service and initialize CA.
        $webuser       = $distroConfig->webuser;
        $webgroup      = $distroConfig->webgroup;
        
        // Instantiate krb5 handler and initialize CA.
        $krb5i         = $this->getKrb5Handler()
                            ->checkCaStatus()
                            ->initializeCa($webgroup)
                            ->generateCaCert($data["califetime"])
                            ->generateWebCert()
                            ->generateKdcCert();
                                                
        /** 
         * For Red Hat, SSL Locations are as follows:
         *
         *   /etc/pki/tls/certs   <- has all anchors
         *   /etc/pki/tls/certs   <- has service pub certs
         *   /etc/pki/CA/private  <- has CA private keys
         *   /etc/pki/tls/private <- has service | host private keys
         */
        $caConfig        = $krb5i->getCaConfig();
        $cacertpubkeyloc = $caConfig->anchors    . '/' . $caConfig->rootPubCert;
        $cacertprvkeyloc = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;
        $publicCerts     = $caConfig->publicCerts;
        $privateCerts    = $caConfig->privateCerts;
        
        // Link the public and private server certs
        $this->_softLink($publicCerts,  $pubCerts);
        $this->_softLink($privateCerts, $prvKeys);
        
        // Link CA Certs
        $this->_softLink($cacertpubkeyloc, $caCerts);
        $this->_softLink($cacertprvkeyloc, $caKeys);

        // Link localhost public & private key to generated system host certs
        // Get Zivios CA config.
        $hostname = $this->_session->localSysInfo['hostname'];
        $hsslcert = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $hsslkey  = $caConfig->privateCerts . '/' . $hostname . '.key';

        $this->_softLink($hsslcert, $lcpubcert);
        $this->_softLink($hsslkey,  $lcprvcert);

        // Rehash CA certs.
        $cmd = $this->_session->_cmds['c_rehash'];
        $rc = $this->_runLinuxCmd($cmd, true);

        if ($rc['exitcode'] != 0) {
            throw new Zivios_Error('Could not rehash anchor certs. Is OpenSSL-perl installed?');
        }
    }

    /**
     * Function rewrites the default apache vhost template, enabling
     * SSL (and only SSL) access to the system.
     *
     * @return void
     */
    public function iniWebssl()
    {
        Zivios_Log::info('Initializing SSL for zivios virtual host.', 'clogger');

        $wbFolder = $this->linuxConfig->backupFolder . '/apache';

        if (!is_dir($wbFolder)) {
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $wbFolder; 
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not create backup folder for Apache data.");
            }
        }

        // Ensure the 'zpanel' vhost has been backed up.
        if (!file_exists($wbFolder . '/zpanel.conf')) {
            $cmd = $this->_session->_cmds['mv'] . ' /opt/zivios/httpd/conf/vhosts.d/zpanel.conf ' . $wbFolder;
            $rc = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Could not backup zivios-panel vhost to Zivios apache backup folder.');
            }
        }

        $template = APPLICATION_PATH . '/library/Zivios/Install/Templates/apache/sslvhost.tmpl';
        $hostname = $this->_session->localSysInfo['hostname'];

        // Get Zivios CA config.
        $caConfig = $this->getKrb5Handler()->getCaConfig();
        $sslcert  = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $sslkey   = $caConfig->privateCerts . '/' . $hostname . '.key';

        if (!file_exists($template) || !is_readable(($template))) {
            throw new Zivios_Exception('Could not find apache vhost template.');
        }

        if (!file_exists($sslcert) || !is_readable(($sslcert))) {
            throw new Zivios_Exception('Could not find / read web host public certificate');
        }

        if (!file_exists($sslkey) || !is_readable(($sslkey))) {
            throw new Zivios_Exception('Could not find / read web host private certificate');
        }

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
        $tmpVhostFile = $this->linuxConfig->tmpFolder . '/' . 'zivios-ssl.vhost';

        if (!$fp = fopen($tmpVhostFile, 'w')) {
            throw new Zivios_Exception('Could not open file for writing in tmp folder.');
        }

        if (fwrite($fp, $vhosttemplate) === FALSE) {
            throw new Zivios_Exception('Could not write apache vhost template to file.');
        }

        fclose($fp);

        // Copy the SSL enabled vhost file.
        $cmd = $this->_session->_cmds['cp'] . ' ' . $tmpVhostFile . ' /opt/zivios/httpd/conf/vhosts.d/zpanel.conf';
        $rc = $this->_runLinuxCmd($cmd,true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Could not copy Zivios vhost file to /opt/zivios/httpd/conf/vhosts.d/');
        }

        Zivios_Log::info('SSL initialized successfully. Please restart the web server.', 'clogger');
    }

    /**
     * Initialize LDAP setup and start the service.
     *
     * @return void
     */
    public function iniLdapSetup($data)
    {
        $distroConfig  = $this->_getDistroDetails();
        $controlScript = $distroConfig->initSlapd;
        $saslScript    = $distroConfig->initSasl;
        $nsswitchFile  = $distroConfig->nsswitchLocation;
        $ldapcconffile = $distroConfig->ldapconffile;
        $saslconffile  = $distroConfig->saslconffile;

        /**
         * Note: the zadmin,cnconfig & replicator password is received as part of this
         * data array. We need to store this in our session as kerberos initialization 
         * will set the specified passwords.
         */
        $this->_session->zadminPass     = $data['zadminpass'];
        $this->_session->cnconfigPass   = $data['cnconfigpass'];
        $this->_session->replicatorPass = $data['replicatorpass'];
        $this->_session->companyName    = $data['scompany'];

        $ldapi = $this->getLdapHandler()->clearLdapData()
                                        ->iniLdapConfig($data, $controlScript)
                                        ->addDataTemplate($data, 'RPM')
                                        ->fixAcl($data)
                                        ->reinjectGroupMembers($data)
                                        ->updateNis($nsswitchFile,$ldapcconffile)
                                        ->updateSaslConfig($saslconffile)
                                        ->serviceAction($saslScript, 'start', 'sasl');
    }

    /**
     * Initialize Kerberos setup and start the service
     *
     * @return void
     */
    public function iniKrb5Setup($data)
    {
        $distroConfig  = $this->_getDistroDetails();
        $controlScript = $distroConfig->initKrb5;
        $krb5Link      = $distroConfig->krb5ConfLocation;
        $hostKeytab    = $distroConfig->hostKeytab;

        // Generate and register the password for the bind user.
        $bindpass                        = Zivios_Util::randomString(8);
        $bindreplicapass                 = Zivios_Util::randomString(8);
        $this->_session->bindPass        = $bindpass;
        $this->_session->bindReplicaPass = $bindreplicapass;
        $zadminpass                      = $this->_session->zadminPass;
        $replicatorpass                  = $this->_session->replicatorPass;

        $krb5i = $this->getKrb5Handler()->checkKrb5Setup()
                                        ->iniKrb5Config()
                                        ->createLink($krb5Link)
                                        ->iniKrb5Data()
                                        ->extractKeytabs()
                                        ->linkHostKeytab($hostKeytab)
                                        ->finalizeKrb5Conf()
                                        ->setPassword('zdnsuser', $bindpass)
                                        ->setPassword('zadmin', $zadminpass)
                                        ->setPassword('zldapreplica', $replicatorpass)
                                        ->setPassword('zdnsreplica', $bindreplicapass)
                                        ->startHeimdal($controlScript);

        // restart LDAP to ensure keytab is read.
        $ldapControlScript = $distroConfig->initSlapd;
        $this->getLdapHandler()->serviceAction($ldapControlScript, 'restart');
        Zivios_Log::info('Directory service (slapd) restarted', 'clogger');
    }

    /**
     * Initialize Bind setup and start the service
     *
     * @return void
     */
    public function iniBindSetup($data)
    {
        $distroConfig  = $this->_getDistroDetails();
        $controlScript = $distroConfig->initBind;
        $binduserPass  = $this->_session->bindPass;
        $webuser       = $distroConfig->webuser;
        $webgroup      = $distroConfig->webgroup;

        $dnsi = $this->getBindHandler()->iniBindConfig($data, $binduserPass, $webuser, $webgroup)
                                       ->serviceAction($controlScript, "start");
    }

    /**
     * Initialize Zivios agent and web panel setup.
     *
     * @return void
     */
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
        // Initialize package management helper.
        $packageManager = $this->_getPackageManager();

        // Get common packages for redhat.
        $redhatBased = new Zend_Config_Ini(APPLICATION_PATH . 
            '/config/installer.config.ini', 'redhat_based');
        
        // Get all redhat based packages.
        $requiredPackages = explode(",", $redhatBased->requiredPackages);

        foreach ($requiredPackages as $package) {
            if ($package != '') {
                if (!$packageManager->hasPackage($package)) {
                    throw new Zivios_Error("Required package: " . $package . " not found.");
                }
            }
        }
    }

    protected function _getPackageManager()
    {
        if (null === $this->_packageManager) {
            $this->_packageManager = new Os_Linux_PackageHelper_Rpm();
        }

        return $this->_packageManager;
    }
}

