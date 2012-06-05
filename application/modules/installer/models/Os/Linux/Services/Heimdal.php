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
 * @package     ZiviosInstaller
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Os_Linux_Services_Heimdal extends Os_Linux
{
    protected $_caConfig, $_caBase, $_krb5Config, $_krb5Base;

    public function __construct()
    {
        parent::__construct();
        
        if (null === $this->_session->_krb5cmds) {
            // Set base targets.
            $krb5Config = $this->getKrb5Config();
            $krb5cmds = array();
            $krb5cmds['kadmin'] = $krb5Config->sbin . '/kadmin';
            $krb5cmds['hxtool'] = $krb5Config->bin  . '/hxtool';
            
            // Link command array to session.
            $this->_session->_krb5cmds = $krb5cmds;
        }

        $this->sysDistro = strtolower($this->_session->osDetails['distro']);
        return $this;
    }

    public function checkKrb5Setup()
    {
        $krb5Config = $this->getKrb5Config();
        
        // If configuration or data directories exist for Kerberos, purge them.
        if (is_dir($krb5Config->etc)) {
            $cmd = $this->_session->_cmds['rm'] . ' -rf ' . $krb5Config->etc;
            $rc = $this->_runLinuxCmd($cmd, true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Error: could not remove Kerberos configuration folder.');
            }
        }

        $this->_createFolder($krb5Config->etc, '0755', 'root', 'root');

        if (is_dir($krb5Config->run)) {
            $cmd = $this->_session->_cmds['rm'] . ' -rf ' . $krb5Config->run;
            $rc = $this->_runLinuxCmd($cmd,true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Error: could not remove Kerberos run folder.');
            }
        }

        $this->_createFolder($krb5Config->run, '0700', 'root', 'root');

        Zivios_Log::info('Initialized base kerberos folders', 'clogger');
        
        // Connect to ldap & ensure kerberos data does not exist.
        if (!$conn = ldap_connect('localhost', 389)) {
            throw new Zivios_Exception('Connection to Ldap service failed. Operation: Check KRB5 data.');
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        
        $rootdn = 'cn=admin,' . $this->_session->localSysInfo['basedn'];

        if (!ldap_bind($conn, $rootdn, $this->_session->zadminPass)) {
            throw new Zivios_Exception('Could not bind to Ldap service. Operation: Check KRB5 data.');
        }

        $searchDn = 'o=kerberos,cn=zivios kerberos,ou=master services,ou=core control,ou=zivios,'.
            $this->_session->localSysInfo['basedn'];

        if (false === ($results = ldap_search($conn, $searchDn, "objectClass=*", array('dn')))) {
            throw new Zivios_Exception('Zivios Kerberos base not initialized. Cannot continue.');
        } else {
            $krbinfo = @ldap_get_entries($conn, $results);
            if ($krbinfo['count'] <= 1) {
                Zivios_Log::info('No Kerberos data found in LDAP, Initializing...', 'clogger');
            } else {
                Zivios_Log::info('Kerberos data found in LDAP, purging...', 'clogger');
                foreach ($krbinfo as $key => $edn) {
                    if ($key == "count") {
                        continue;
                    }
                    if (!ldap_delete($conn, $edn['dn'])) {
                        throw new Zivios_Exception('Could not clear existing KRB data in LDAP.');
                    }
                }
                Zivios_Log::info('Existing kerberos data purged.', 'clogger');
            }
        }

        return $this;
    }

    public function iniKrb5Config()
    {
        $krb5Config = $this->getKrb5Config();

        $templates   = array();
        $templates[] = APPLICATION_PATH .
            '/library/Zivios/Install/Templates/heimdal/krb5.conf.tmpl';
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/heimdal/kadmind.acl.tmpl';
        $templates[] = APPLICATION_PATH .
            '/library/Zivios/Install/Templates/heimdal/kdc.conf.tmpl';
        $templates[] = APPLICATION_PATH .
            '/library/Zivios/Install/Templates/heimdal/heimdal.defaults.tmpl';

        foreach ($templates as $template) {
            if (!file_exists($template) || !is_readable($template)) {
                throw new Zivios_Exception("Error: required Kerberos template not found: " 
                    . $template);
            }
        }

        // Copy over the heimdal defaults file.
        $this->_copyFile($templates[3], $krb5Config->krb5Defaults, "0640", "root", "root");

        // Get CA configuration for template data population.
        $caConfig = $this->getDistroClass()
                         ->getKrb5Handler()
                         ->getCaConfig();

        $hostname = strtolower($this->_session->localSysInfo["krb5realm"]);

        // Initialize Zivios CA service.
        $cacert  = $caConfig->anchors      . '/' . $caConfig->rootPubCert;
        $kdccert = $caConfig->publicCerts  . '/' . 'kdc.' . $hostname . '.crt';
        $kdckey  = $caConfig->privateCerts . '/' . 'kdc.' . $hostname . '.key';

        // Ensure the ssl files exist and are readable.
        if (!file_exists($kdccert) || !is_readable(($kdccert))) {
            throw new Zivios_Exception("Could not find / read kdc public certificate: ".$kdccert );
        }

        if (!file_exists($kdckey) || !is_readable(($kdckey))) {
            throw new Zivios_Exception("Could not find / read kdc private certificate: ".$kdckey);
        }

        // Set krb5.conf template data & generate template.
        $vals = array();
        $vals['krb5realm']        = $this->_session->localSysInfo['krb5realm'];
        $vals['kdc_host']         = $this->_session->localSysInfo['hostname'];
        $vals['kadmin_host']      = $this->_session->localSysInfo['hostname'];
        $vals['zivios_ca_pubkey'] = $cacert;
        $vals['kdc_pubkey']       = $kdccert;
        $vals['kdc_prvkey']       = $kdckey;
        $vals['lckrb5realm']      = $hostname;
        
        /**
         * Initially, we are setting the base DN for kerberos initialization to be specific
         * to Zivios configuration. We will reset this variable to the actual base dn at a
         * later stage. 
         */
        $vals['db_base'] = 'o=kerberos,cn=zivios kerberos,ou=master services,ou=core control,ou=zivios,'.
            $this->_session->localSysInfo['basedn'];

        $krb5templategen = Zivios_Util::renderTmplToCfg($templates[0],$vals);

        // Write the template to installer tmp folder.
        $tmpkrb5conf_1  = $this->linuxConfig->tmpFolder . '/' . 'krb5_01.conf';

        if (!$fp = fopen($tmpkrb5conf_1, "w")) {
            throw new Zivios_Exception("Could not open file for writing in tmp folder.");
        }

        if (fwrite($fp, $krb5templategen) === FALSE) {
            throw new Zivios_Exception("Could not write krb5_01.conf template to file.");
        }

        fclose($fp);

        // Using exactly the data generated above, we write the final krb5_02.conf template
        // to file as well. The file will be moved at the end of krb5 data import.
        $vals['db_base'] = $this->_session->localSysInfo['basedn'];
        $krb5templategen = Zivios_Util::renderTmplToCfg($templates[0],$vals);

        $tmpkrb5conf_2  = $this->linuxConfig->tmpFolder . '/' . 'krb5_02.conf';

        if (!$fp = fopen($tmpkrb5conf_2, "w")) {
            throw new Zivios_Exception("Could not open file for writing in tmp folder.");
        }

        if (fwrite($fp, $krb5templategen) === FALSE) {
            throw new Zivios_Exception("Could not write krb5_02.conf template to file.");
        }

        fclose($fp);

        // Generate template for kdc.conf & kadmind.conf as well, writing them to the
        // installer's tmp folder.
        $kadmindtemplategen = Zivios_Util::renderTmplToCfg($templates[1], $vals);
        $kdctemplategen     = Zivios_Util::renderTmplToCfg($templates[2], $vals);
        $tmpkadmindtemplate = $this->linuxConfig->tmpFolder . '/' . 'kadmind.acl';
        $tmpkdctemplate     = $this->linuxConfig->tmpFolder . '/' . 'kdc.conf';

        if (!$fp = fopen($tmpkadmindtemplate, "w")) {
            throw new Zivios_Exception("Could not open file for writing in tmp folder.");
        }

        if (fwrite($fp, $kadmindtemplategen) === FALSE) {
            throw new Zivios_Exception("Could not write kadmind.acl template to file.");
        }

        fclose($fp);

        if (!$fp = fopen($tmpkdctemplate, "w")) {
            throw new Zivios_Exception("Could not open file for writing in tmp folder.");
        }

        if (fwrite($fp, $kdctemplategen) === FALSE) {
            throw new Zivios_Exception("Could not write kdc.conf template to file.");
        }

        fclose($fp);        

        // Copy over krb5.conf, kdc.conf & kadmind.acl to their target locations.
        $this->_copyFile($tmpkrb5conf_1, $krb5Config->krb5ConfFile, "0644", "root", "root");
        $this->_copyFile($tmpkadmindtemplate, $krb5Config->kadmindConfFile, "0640", "root", "root");
        $this->_copyFile($tmpkdctemplate, $krb5Config->kdcConfFile, "0640", "root", "root");

        Zivios_Log::info('Initialized base Kerberos templates and configuration', 'clogger');

        return $this;
    }

    public function createLink($link)
    {
        $krb5Config = $this->getKrb5Config();

        if (is_link($link)) {
            if (readlink($link) == $krb5Config->krb5ConfFile) {
                return $this;
            } else {
                // Remove the symlink.
                $cmd = $this->_session->_cmds['rm'] . ' ' . $link;
                $this->_runLinuxCmd($cmd, true);
            }
        } else {
            if (file_exists($link)) {
                $cmd = $this->_session->_cmds['rm'] . ' ' . $link;
                $this->_runLinuxCmd($cmd, true);
            }
        }

        $cmd = $this->_session->_cmds['ln'] . ' -s ' . $krb5Config->krb5ConfFile . ' ' . $link;
        $this->_runLinuxCmd($cmd, true);

        return $this;
    }

    public function iniKrb5Data()
    {
        $krb5Config  = $this->getKrb5Config();
        $krb5realm   = $this->_session->localSysInfo['krb5realm'];
        $localdomain = $this->_session->localSysInfo['hostname'];

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' init';
        $cmd.= ' --realm-max-ticket-life=1day';
        $cmd.= ' --realm-max-renewable-life=1month';
        $cmd.= ' ' . $krb5realm;

        $rc = $this->_runLinuxCmd($cmd, true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Could not initialize Kerberos realm.');
        }

        $hostkey = 'host/'.$localdomain;
        $ldapkey = 'ldap/'.$localdomain;

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' -r '.$krb5realm;
        $cmd.= ' add -p ' . $hostkey;
        $cmd.= ' --use-defaults ' . $hostkey.'@'.$krb5realm;
        
        $rc = $this->_runLinuxCmd($cmd, true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Could not generate host keytab');
        }

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' -r '.$krb5realm;
        $cmd.= ' add -p ' . $ldapkey;
        $cmd.= ' --use-defaults ' . $ldapkey.'@'.$krb5realm;

        $this->_runLinuxCmd($cmd, true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Could not generate ldap keytab');
        }        

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' -r '.$krb5realm;
        $cmd.= ' cpw -r ' . $hostkey.'@'.$krb5realm;
        
        $rc = $this->_runLinuxCmd($cmd, true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Random pass generation for host key failed');
        }        

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' -r '.$krb5realm;
        $cmd.= ' cpw -r ' . $ldapkey.'@'.$krb5realm;

        $rc = $this->_runLinuxCmd($cmd, true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Random pass generation for ldap key failed');
        }        

        Zivios_Log::info('Kerberos realm: ' . $krb5realm .' initialized successfully', 'clogger');

        return $this;
    }

    public function extractKeytabs()
    {
        $krb5Config = $this->getKrb5Config();
        $ldapConfig = $this->getDistroClass()
                           ->getLdapHandler()
                           ->getLdapConfig();

        $hostKeytab = $krb5Config->hostKeytab;

        $krb5realm   = $this->_session->localSysInfo['krb5realm']; 
        $localdomain = $this->_session->localSysInfo['hostname'];

        $ldapKeytab  = $ldapConfig->ldapKeytab;
        $ldapkey = 'ldap/'.$localdomain;
        $hostkey = 'host/'.$localdomain;

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' ext -k ' . $ldapKeytab;
        $cmd.= ' ' . $ldapkey.'@'.$krb5realm;

        $this->_runLinuxCmd($cmd, true);

        // set ownership of keytab for slapd user.
        $this->_setOwnership($ldapKeytab, $ldapConfig->user, $ldapConfig->group, true);

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' ext -k ' . $hostKeytab;
        $cmd.= ' ' . $hostkey.'@'.$krb5realm;

        $this->_runLinuxCmd($cmd, true);

        Zivios_Log::info('Extracted host and ldap keytabs', 'clogger');

        return $this;
    }

    public function linkHostKeytab($hostkeytab)
    {
        $krb5Config = $this->getKrb5Config();

        if (is_link($hostkeytab)) {
            if (readlink($hostkeytab) == $krb5Config->hostKeytab) {
                return $this;
            } else {
                // Remove invalid symlink.
                $cmd = $this->_session->_cmds['rm'] . ' ' . $hostkeytab;
                $this->_runLinuxCmd($cmd, true);
            }
        } else {
            // remove keytab file
            $cmd = $this->_session->_cmds['rm'] . ' ' . $hostkeytab;
            $this->_runLinuxCmd($cmd, true);
        }
        
        // create symlink for host keytab
        $cmd = $this->_session->_cmds['ln'] . ' -s ' . $krb5Config->hostKeytab . ' ' . $hostkeytab;
        $this->_runLinuxCmd($cmd, true);

        return $this;
    }

    public function finalizeKrb5Conf()
    {
        $krb5Config    = $this->getKrb5Config();
        $finalkrb5Conf = $this->linuxConfig->tmpFolder . '/' . 'krb5_02.conf';
        $krb5ConfFile  = $krb5Config->krb5ConfFile;
        $this->_copyFile($finalkrb5Conf, $krb5ConfFile, "0644", "root", "root");

        Zivios_Log::info('Wrote final krb5.conf file to system', 'clogger');

        return $this;
    }

    public function setPassword($user, $pass)
    {
        $krb5realm = $this->_session->localSysInfo['krb5realm'];

        $cmd = $this->_session->_krb5cmds['kadmin'] . ' -l';
        $cmd.= ' -r '.$krb5realm;
        $cmd.= ' cpw --password="'.$pass.'"';
        $cmd.= ' '.$user.'@'.$krb5realm;

        $this->_runLinuxCmd($cmd, true);

        Zivios_Log::info('Set password for user: ' . $user);

        return $this;
    }

    public function startHeimdal($control)
    {
        $cmd = $control . ' start > /dev/null 2>&1 &';
        $this->_runLinuxCmd($cmd, true);
        
        Zivios_Log::info('Kerberos service started', 'clogger');
        return $this;
    }

    public function checkCaStatus()
    {
        Zivios_Log::info('Checking existing CA status...','clogger');

        /*
         * If an /opt/zivios/zivios-ca folder exists, remove it completely.
         * Create required folders for CA services and, accordingly, create
         * required symlinks.
         */
        $caConfig = $this->getCaConfig();

        if (is_dir($caConfig->base)) {
            Zivios_Log::info('CA base folder found, removing...', 'clogger');

            $cmd = $this->_session->_cmds['rm'] . ' -rf ' . $caConfig->base;
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Could not remove Zivios CA base folder: ' . 
                    $this->caConfig->base);
            }

            Zivios_Log::info('Zivios CA base folder removed.', 'clogger');
        }

        return $this;
    }

    public function initializeCa($webgroup)
    {
        Zivios_Log::info('Initializing Certificate Authority...', 'clogger');
        $caConfig = $this->getCaConfig();

        // Recreate folder structure.
        $this->_createFolder($caConfig->base,         '0755', 'root', 'root');
        $this->_createFolder($caConfig->anchors,      '0775', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->anchorsprv,   '0770', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->publicCerts,  '0775', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->privateCerts, '0770', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->tmpCerts,     '0770', 'root', $webgroup);
        $this->_createFolder($caConfig->userPubCerts, '0775', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->userPrvCerts, '0770', 'root', 'ssl-cert');
        $this->_createFolder($caConfig->workDir,      '0770', 'root', $webgroup); 

        Zivios_Log::info('CA base folders creation complete.', 'clogger');
        return $this;
    }

    /**
     * Generate the root CA cert.
     *
     * @return void
     */
    public function generateCaCert($lifetime)
    {
        $caConfig = $this->getCaConfig();
        $basedn   = $this->_session->localSysInfo['basedn'];

        $cmd  = $this->_session->_krb5cmds['hxtool'];
        $cmd .= ' issue-certificate --self-signed --issue-ca --generate-key=rsa';
        $cmd .= ' --subject="cn=Zivios CA,ou=Master Services,ou=Core Control,ou=Zivios,'.$basedn;
        $cmd .= '" --lifetime='.$lifetime.'years';
        $cmd .= ' --certificate="FILE:'.$caConfig->workDir.'/ca.crt"';

        $rc = $this->_runLinuxCmd($cmd);

        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Error generating certificate for Zivios CA.');
        }

        $cacert = $caConfig->workDir . '/ca.crt';
        $cacertpubkey = $caConfig->workDir . '/' . $caConfig->rootPubCert;
        $cacertprvkey = $caConfig->workDir . '/' . $caConfig->rootPrvCert;

        // Split the certificate into separate files.
        $this->_splitCertificate($cacert,$cacertpubkey,$cacertprvkey);

        // Move the public key of the root CA cert to the anchors folder and
        // the private key to the prv folder. Set permissions on keys as required.
        $cacertpubkeyloc = $caConfig->anchors . '/' . $caConfig->rootPubCert;
        $cacertprvkeyloc = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;

        $this->_moveFile($cacertpubkey, $cacertpubkeyloc, '0644', 'root', 'root');
        $this->_moveFile($cacertprvkey, $cacertprvkeyloc, '0640', 'root', 'ssl-cert');

        Zivios_Log::info('Certificate Authority initialized.','clogger');

        return $this;
    }

    public function generateWebCert()
    {
        Zivios_Log::info('Generating certificate for web panel','clogger');

        $caConfig    = $this->getCaConfig();
        $zvcapubcert = $caConfig->anchors . '/' . $caConfig->rootPubCert;
        $zvcaprvcert = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;
        $hostname    = $this->_session->localSysInfo['hostname'];
        $basedn      = $this->_session->localSysInfo['basedn'];
        $subject     = 'cn='.$hostname.',ou=zservers,ou=core control,ou=zivios,'.$basedn;

        // Command to generate certificate for web server.
        $cmd  = $this->_session->_krb5cmds['hxtool'] . ' issue-certificate --subject="'.$subject.'"';
        $cmd .= ' --type="https-server" --hostname="'.$hostname.'"';
        $cmd .= ' --ca-certificate="FILE:'.$zvcapubcert.','.$zvcaprvcert.'"';
        $cmd .= ' --certificate="FILE:'.$caConfig->workDir.'/'.$hostname.'.tmp.crt"';
        $cmd .= ' --generate-key=rsa';

        $rc   = $this->_runLinuxCmd($cmd,true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Error generating certificate for web host.');
        }

        // Split the certificate.
        $tmpcert = $caConfig->workDir . '/' . $hostname . '.tmp.crt';
        $pubkey  = $caConfig->workDir . '/' . $hostname . '.crt';
        $prvkey  = $caConfig->workDir . '/' . $hostname . '.key';

        $this->_splitCertificate($tmpcert, $pubkey, $prvkey);
        
        // Move the cert files to the public/private folders.
        $pubkeyloc = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $prvkeyloc = $caConfig->privateCerts . '/' . $hostname . '.key';

        $this->_moveFile($pubkey, $pubkeyloc, '0644', 'root', 'ssl-cert');
        $this->_moveFile($prvkey, $prvkeyloc, '0640', 'root', 'ssl-cert');

        Zivios_Log::info('Web panel certificate generated successfully.', 'clogger');

        return $this;
    }

    public function generateKdcCert()
    {
        Zivios_Log::info('Generating certificate for KDC','clogger');

        $caConfig    = $this->getCaConfig();
        $basedn      = $this->_session->localSysInfo['basedn'];
        $krb5realm   = $this->_session->localSysInfo['krb5realm'];
        $hostname    = $this->_session->localSysInfo['hostname'];
        $zvcapubcert = $caConfig->anchors      . '/' . $caConfig->rootPubCert;
        $zvcaprvcert = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;

        $subject = 'cn=KDC,cn=Zivios CA,ou=Master Services,ou=Core Control,ou=Zivios,'.$basedn;

        $cmd  = $this->_session->_krb5cmds['hxtool'] . ' issue-certificate --subject="'.$subject.'"';
        $cmd .= ' --type="pkinit-kdc" --pk-init-principal="krb5tgt/'.$krb5realm.'@'.$krb5realm.'"';
        $cmd .= ' --hostname="'.$hostname.'"';
        $cmd .= ' --ca-certificate="FILE:'.$zvcapubcert.','.$zvcaprvcert.'"';
        $cmd .= ' --certificate="FILE:'.$caConfig->workDir .'/kdc.'.strtolower($krb5realm).'.tmp.crt"';
        $cmd .= ' --generate-key=rsa';

        $rc = $this->_runLinuxCmd($cmd,true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Error generating certificate for KDC service.');
        }

        // Split the certificate and move to linked pub/prv folders.
        $tmpcert = $caConfig->workDir .'/kdc.' . strtolower($krb5realm) . '.tmp.crt';
        $pubkey  = $caConfig->workDir .'/kdc.' . strtolower($krb5realm) . '.crt';
        $prvkey  = $caConfig->workDir .'/kdc.' . strtolower($krb5realm) . '.key';

        $this->_splitCertificate($tmpcert, $pubkey, $prvkey);

        // Move the cert files to the public/private folders.
        $pubkeyloc = $caConfig->publicCerts  . '/kdc.' . strtolower($krb5realm) . '.crt';
        $prvkeyloc = $caConfig->privateCerts . '/kdc.' . strtolower($krb5realm) . '.key';

        $this->_moveFile($pubkey, $pubkeyloc, '0644', 'root', 'ssl-cert');
        $this->_moveFile($prvkey, $prvkeyloc, '0640', 'root', 'ssl-cert');

        Zivios_Log::info('KDC certificate generated successfully.', 'clogger');
        
        return $this;
    }

    protected function _splitCertificate($srcCert,$pubKey,$prvKey)
    {
        if (!$fp = fopen($srcCert, "r")) {
            throw new Zivios_Exception("Error reading certificate file.");
        }

        $pubkeydata = '';
        $prvkeydata = '';

        $store = &$pubkeydata;

        while (!feof($fp)) {
            $store .= fgets($fp, 4096);
            if (substr($store, -26) == "-----END CERTIFICATE-----\n")
                $store = &$prvkeydata;
        }

        fclose($fp);

        if (!$fp = fopen($pubKey, "w")) {
            throw new Zivios_Exception("Error opening public key file: " . $pubKey);
        }

        if (fwrite($fp, $pubkeydata) === FALSE) {
            throw new Zivios_Exception("Error writing public key to file.");
        }

        fclose($fp);

        // Write private key to file.
        if (!$fp = fopen($prvKey, "w")) {
            throw new Zivios_Exception("Error opening private key file.");
        }

        if (fwrite($fp, $prvkeydata) === FALSE) {
            throw new Zivios_Exception("Error writing private key to file.");
        }

        fclose($fp);
        unlink($srcCert);
    }

    public function getCaConfig()
    {
        if (null === $this->_caConfig) {
            $this->_caConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', "ca");
        }

        return $this->_caConfig;
    }
    
    public function getKrb5Config()
    {
        if (null === $this->_krb5Config) {
            $this->_krb5Config = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', "krb5");
        }
        
        return $this->_krb5Config;
    }
}

