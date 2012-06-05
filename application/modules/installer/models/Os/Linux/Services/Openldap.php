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

class Os_Linux_Services_Openldap extends Os_Linux
{
    protected $_ldapConfig;

    public function __construct()
    {
        parent::__construct();

        if (null === $this->_session->_ldapcmds) {
            // Set base targets.
            $ldapConfig = $this->getLdapConfig();
            $ldapcmds = array();
            $ldapcmds['ldapadd']    = $ldapConfig->bin     . '/ldapadd';
            $ldapcmds['ldapmodify'] = $ldapConfig->bin     . '/ldapmodify';
            $ldapcmds['ldapdelete'] = $ldapConfig->bin     . '/ldapdelete';
            $ldapcmds['ldapsearch'] = $ldapConfig->bin     . '/ldapsearch';
            $ldapcmds['ldapwhoami'] = $ldapConfig->bin     . '/ldapwhoami';
            $ldapcmds['ldapmodrdn'] = $ldapConfig->bin     . '/ldapmodrdn';
            $ldapcmds['ldappasswd'] = $ldapConfig->bin     . '/ldappasswd';
            $ldapcmds['ldapexop'  ] = $ldapConfig->bin     . '/ldapexop';
            $ldapcmds['ldapsearch'] = $ldapConfig->bin     . '/ldapsearch';

            // sbin cmds
            $ldapcmds['slapadd']    = $ldapConfig->sbin    . '/slapadd';
            $ldapcmds['slapcat']    = $ldapConfig->sbin    . '/slapcat';
            $ldapcmds['slapindex']  = $ldapConfig->sbin    . '/slapindex';

            // daemons / libs
            $ldapcmds['slapd']      = $ldapConfig->libexec . '/slapd';
            
            // Link command array to session.
            $this->_session->_ldapcmds = $ldapcmds;
        }

        return $this;
    }

    /**
     * Function clears out all LDAP data (configuration, access as well as primary DB)
     *
     * @return void
     */
    public function clearLdapData()
    {
        $ldapConfig = $this->getLdapConfig();
        $dataDir    = $ldapConfig->dataDir;
        $slapdData  = $ldapConfig->confDdir;
        $accessDir  = $ldapConfig->accessDir;
        $runDir     = $ldapConfig->runDir;

        // Ensure the slapd service is not running.
        $cmd = $this->_session->_cmds['sudo'] . ' ' . $this->_session->_cmds['kill'] . 
            ' -9 `' . $this->_session->_cmds['pidof'] . ' slapd`';

        $output = array();
        $return_var = 0;
        $out = exec($cmd, &$output, &$return_var);

        if ($return_var != 0) {
            Zivios_Log::debug("No slapd process found running.");
        } else {
            Zivios_log::debug("Killed (running) slapd process.");
        }

        if (is_dir($dataDir)) {
            $this->_removeRecursive($dataDir);
        }

        if (is_dir($accessDir)) {
            $this->_removeRecursive($accessDir);
        }

        if (is_dir($slapdData)) {
            $this->_removeRecursive($slapdData);
        }

        if (file_exists($ldapConfig->ldapKeytab)) {
            $this->_removeFile($ldapConfig->ldapKeytab);
        }

        // Create the data dir.
        $this->_createFolder($dataDir,   '0700', $ldapConfig->user, $ldapConfig->group);
        $this->_createFolder($accessDir, '0700', $ldapConfig->user, $ldapConfig->group);

        // set ownership of slapd run folder (just in case).
        $this->_setOwnership($runDir, $ldapConfig->user, $ldapConfig->group, true);

        return $this;
    }

    /**
     * Updates the nsswitch file to (also) read users/groups from LDAP.
     *
     * @return object $this
     * @todo: switch to nssov. Get rid of libnss dependencies altogether.
     */
    public function updateNis($nsswitch,$ldapconf)
    {
        $ldapConfig = $this->getLdapConfig();

        // backup original nsswitch.conf to system-backups
        $backupFolder = $this->linuxConfig->backupFolder;
        $nsswitchOrig = $backupFolder . '/nsswitch.conf';

        if (!file_exists($nsswitchOrig)) {
            $this->_copyFile($nsswitch, $nsswitchOrig, "0644", "root","root");
        }

        $nsswitchTemplate = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/system/nsswitch.conf';

        $this->_copyFile($nsswitchTemplate, $nsswitch, "0644", "root", "root");
		
		$ldapConfTemplate = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/system/ldap.conf.tmpl';
			
		$vals = array();
		$vals['basedn'] = $this->_session->localSysInfo['basedn'];
		$ldaptemplate   = Zivios_Util::renderTmplToCfg($ldapConfTemplate,$vals);      
        $tmpslapdconf   = $this->linuxConfig->tmpFolder . '/' . 'zivios-sys-ldap.conf';

		if (!$fp = fopen($tmpslapdconf, "w"))
            throw new Zivios_Exception("Could not open Temp Ldap.conf file for writing in tmp folder.");

		if (fwrite($fp, $ldaptemplate) === FALSE)
            throw new Zivios_Exception("Could not write template to file.");

		fclose($fp);
        
        // Copy over the template to openldap conf folder & start slapd , importing
        // to slapd.conf to slapd.d
        $this->_copyFile($tmpslapdconf, $ldapconf, '0644', 'root', 'root');
		
        return $this;
    }

    public function updateSaslConfig($saslconf)
    {
        $saslDefaults = APPLICATION_PATH .
            '/library/Zivios/Install/Templates/openldap/sasl.defaults.tmpl';
        
        $this->_copyFile($saslDefaults, $saslconf, '0644', 'root', 'root');

        return $this;
    }
       
    /**
     * Initializes LDAP setup.
     *
     * @return Object $this
     */
    public function iniLdapConfig($data, $controlScript)
    {
        $ldapConfig = $this->getLdapConfig();

        // Copy across slapd.defaults to openldap conf folder.
        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/openldap/slapd.defaults.tmpl';
        $tmplDestination = $ldapConfig->confDir . '/slapd.defaults';
        $this->_copyFile($tmplSource, $tmplDestination, '0640', 'root', $ldapConfig->group);

        // copy over the DBCONFIG file.
        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/openldap/db_config.tmpl';
        $tmplDestination = $ldapConfig->dataDir . '/DB_CONFIG';
        $this->_copyFile($tmplSource, $tmplDestination, '0600', $ldapConfig->user, $ldapConfig->group);

        // copy over DBCONFIG to accesslog-data folder
        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/openldap/adb_config.tmpl';
        $tmplDestination = $ldapConfig->accessDir . '/DB_CONFIG';
        $this->_copyFile($tmplSource, $tmplDestination, '0600', $ldapConfig->user, $ldapConfig->group);

        // Create slapd.d data directory for LDAP. Initial start of the slapd
        // service will see migration of slapd.conf to slapd.d
        $slapdDataDir = $ldapConfig->confDdir;
        $this->_createFolder($slapdDataDir, '0700', $ldapConfig->user, $ldapConfig->group);

        $caConfig = $this->getDistroClass()
                         ->getKrb5Handler()
                         ->getCaConfig();

        // Initialize Zivios CA service.
        $hostname  = $this->_session->localSysInfo['hostname'];
        $sslcacert = $caConfig->anchors      . '/' . $caConfig->rootPubCert;
        $sslcert   = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $sslkey    = $caConfig->privateCerts . '/' . $hostname . '.key';

        // copy over Zivios schema files to OpenLDAP schema directory
        $zvCoreLdifs = explode(',', $ldapConfig->coreLdifs);
        foreach ($zvCoreLdifs as $coreLdif) {
            $ldifLocation = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/openldap/ldifs/' . $coreLdif;

            if (!file_exists($ldifLocation)) {
                Zivios_Log::error('Could not find core ldif: ' . $ldif);
                throw new Zivios_Exception("Could not find core ldif: " . $ldif);
            }

            // copy the file to Zivios OpenLDAP schema Directory
            $openldapSchemaDir = '/opt/zivios/openldap/etc/openldap/schema';
            $this->_copyFile($ldifLocation, $openldapSchemaDir, "0644", "root", "root");
        }

        // get all ldifs required for cn=config initialization.
        $iniLdifs = array(
            APPLICATION_PATH . '/library/Zivios/Install/Templates/openldap/baseldifs/1_cnbase.ldif',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/openldap/baseldifs/2_cnmodules.ldif',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/openldap/baseldifs/3_primarydb.ldif'
        );

        // make ready template data.
        $vals = array();
        $vals['run_dir']           = $ldapConfig->runDir;
        $vals['conf_dir']          = $ldapConfig->confDdir;
        $vals['data_dir']          = $ldapConfig->dataDir;
        $vals['accesslog_dir']     = $ldapConfig->accessDir;
        $vals['libexec_dir']       = $ldapConfig->libexec . '/openldap';
        $vals['base_dn']           = $this->_session->localSysInfo['basedn'];
        $vals['zadmin_pass']       = "{SHA}".base64_encode(sha1($data['zadminpass'],true));
        $vals['ssl_cacert']        = $sslcacert;
        $vals['ssl_pubcert']       = $sslcert;
        $vals['ssl_prvkey']        = $sslkey;
        $vals['sasl_realm']        = $this->_session->localSysInfo['krb5realm'];
        $vals['sasl_host']         = $this->_session->localSysInfo['hostname'];
        $vals['master_server']     = $this->_session->localSysInfo['hostname'];
        $vals['sasl_regex_domain'] = strtolower($this->_session->localSysInfo['krb5realm']);
        $vals['cnconfig_pass']     = "{SHA}".base64_encode(sha1($data['cnconfigpass'],true));

        foreach ($iniLdifs as $ldif) {
            if (!file_exists($ldif) || !is_readable($ldif)) {
                throw new Zivios_Error('Could not find/read ' . $ldif);
            }
        }
        
        $cnbaseTemplate    = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/openldap/baseldifs/1_cnbase.ldif';

        $cnmodulesTemplate = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/openldap/baseldifs/2_cnmodules.ldif';

        $cnprimaryTemplate = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/openldap/baseldifs/3_primarydb.ldif';

        $cnbaseData    = Zivios_Util::renderTmplToCfg($cnbaseTemplate, $vals);
        $cnmodulesData = Zivios_Util::renderTmplToCfg($cnmodulesTemplate, $vals);
        $cnprimaryData = Zivios_Util::renderTmplToCfg($cnprimaryTemplate, $vals);

        $tmpcnbase    = $this->linuxConfig->tmpFolder . '/' . '1_cnbase.ldif';
        $tmpcnmodules = $this->linuxConfig->tmpFolder . '/' . '2_cnmodules.ldif';
        $tmpcnpridb   = $this->linuxConfig->tmpFolder . '/' . '3_primarydb.ldif';
        
        // write files to tmp folder.
        if (!$fp = fopen($tmpcnbase, 'w')) {
            throw new Zivios_Exception('Could not open file for writing in tmp folder.');
        }

        if (fwrite($fp, $cnbaseData) === FALSE) {
            throw new Zivios_Exception('Could not write cnconfig base template to file.');
        } 
        fclose($fp);

        if (!$fp = fopen($tmpcnmodules, 'w')) {
            throw new Zivios_Exception('Could not open file for writing in tmp folder.');
        }

        if (fwrite($fp, $cnmodulesData) === FALSE) {
            throw new Zivios_Exception('Could not write cnconfig modules template to file.');
        } 
        fclose($fp);

        if (!$fp = fopen($tmpcnpridb, 'w')) {
            throw new Zivios_Exception('Could not open file for writing in tmp folder!');
        }

        if (fwrite($fp, $cnprimaryData) === FALSE) {
            throw new Zivios_Exception('Could not write cnconfig primary db template to file.');
        } 
        fclose($fp);
        
        // ready to slapdadd generated templates to directory server
        $cmd = $this->_session->_ldapcmds['slapadd'] . ' -F ' . $ldapConfig->confDdir . ' -n0 -l ' . $tmpcnbase;
        $rc = $this->_runLinuxCmd($cmd, true);
        
        if ($rc['exitcode'] != 0) {
            Zivios_Log::error('Command failed: ' . $cmd);
            throw new Zivios_Error('Initialization of base cn=config failed. LDIF: ' . $tmpcnbase);
        }

        // inject modules ldif
        $cmd = $this->_session->_ldapcmds['slapadd'] . ' -F ' . $ldapConfig->confDdir . ' -n0 -l ' . $tmpcnmodules;
        $rc = $this->_runLinuxCmd($cmd, true);
        
        if ($rc['exitcode'] != 0) {
            Zivios_Log::error('Command failed: ' . $cmd);
            throw new Zivios_Error('Initialization of base cn=config failed. LDIF: ' . $tmpcnmodules);
        }

        // fix permissions & start slapd
        $this->_setOwnership($ldapConfig->confDdir, $ldapConfig->user, $ldapConfig->group, true);
        $this->serviceAction($controlScript, 'start');

        // generate primary DB data template.
        $cmd = $this->_session->_ldapcmds['ldapadd'] . ' -Y EXTERNAL -H ldapi:/// -f ' . $tmpcnpridb;
        $rc = $this->_runLinuxCmd($cmd, true);
        
        if ($rc['exitcode'] != 0) {
            Zivios_Log::error('Command failed: ' . $cmd);
            throw new Zivios_Error('Initialization of primary db in cn=config failed. LDIF: ' . $tmpcnpridb);
        }

        return $this;
    }

    public function addDataTemplate($data, $pkgPlugin)
    {
        $ldapConfig = $this->getLdapConfig();
        
        /**
         * Based on the hostname detected, the template will be switched
         * between the possible variations of 'domain.name' and 
         * n.(more?).domain.name
         *
         * The templates differ only in the context of how DNS entries
         * are defined.
         */
        $vals = array();
        if ($this->_session->localSysInfo['addzone'] == '') {
            $template = APPLICATION_PATH .
                '/library/Zivios/Install/Templates/openldap/coredata.tmpl';
                $dcparts = explode('.', $this->_session->localSysInfo['bindzone']);
                $vals['dc_part'] = $dcparts[0];
        } else {
            $template = APPLICATION_PATH .
                '/library/Zivios/Install/Templates/openldap/coredata-extended.tmpl';

            // Based on hostname specified, create data structure.
            $dcparts = explode('.', $this->_session->localSysInfo['bindzone']);
            $vals['dc_part'] = $dcparts[0];
        }

		if (!file_exists($template) || !is_readable(($template))) {
            throw new Zivios_Exception("Could not find core data template.");
        }

        $vals['base_dn']            = $this->_session->localSysInfo['basedn'];
        $vals['org']                = $data['scompany'];
        $vals['short_company_name'] = $data['scompany'];
        $vals['master_computer']    = $this->_session->localSysInfo['hostname'];
        $vals['root_zone']          = $this->_session->localSysInfo['bindzone'];
        $vals['master_host']        = $this->_session->localSysInfo['addzone'];
        $vals['master_ip']          = $this->_session->localSysInfo['ip'];
        $vals['krb5realm']          = $this->_session->localSysInfo['krb5realm'];
        $vals['master_ntp_server']  = 'ntp.ubuntu.com';
        $vals['cpumhz']             = $this->_session->localSysInfo['cpudetails']['cpumhz'];
        $vals['code_release']       = $this->_session->osDetails['codename'] . '-' . $this->_session->osDetails['release'];
        $vals['distro_desc']        = $this->_session->osDetails['distro'] . ' ' . $this->_session->osDetails['codename'] . '-' . $this->_session->osDetails['release'];
        $vals['code_name']          = $this->_session->osDetails['codename'];
        $vals['os']                 = $this->_session->osDetails['os'];
        $vals['arch']               = $this->_session->localSysInfo['arch'];
        $vals['cpucount']           = $this->_session->localSysInfo['cpudetails']['cpucount'];
        $vals['cpumodel']           = $this->_session->localSysInfo['cpudetails']['cpumodel'];
        $vals['distro']             = $this->_session->osDetails['distro'];
        $vals['ram']                = $this->_session->localSysInfo['memory']['totalMemory'];
        $vals['swap']               = $this->_session->localSysInfo['memory']['totalSwap'];

        if ($pkgPlugin == 'DEB') {
            $vals['pkg_plugin'] = 'DebComputerPackage';
        } else {
            $vals['pkg_plugin'] = 'RpmComputerPackage';
        }

		$coredatatemplate = Zivios_Util::renderTmplToCfg($template, $vals);      
        $coredatatmpfile  = $this->linuxConfig->tmpFolder . '/' . 'coredata.ldif';

		if (!$fp = fopen($coredatatmpfile, 'w')) {
            throw new Zivios_Exception('Could not open file: '.$coredatatmpfile.' for writing in tmp folder.');
        }

		if (fwrite($fp, $coredatatemplate) === FALSE) {
            throw new Zivios_Exception('Could not write core data template to file.');
        }

		fclose($fp);

        $cmd = $this->_session->_ldapcmds['ldapadd'] . ' -Y EXTERNAL -H ldapi:/// -f ' . $coredatatmpfile;
        $rc = $this->_runLinuxCmd($cmd, true);
        
        if ($rc['exitcode'] != 0) {
            Zivios_Log::error('Command failed: ' . $cmd);
            throw new Zivios_Error('Initialization of content failed. LDIF: ' . $coredatatmpfile);
        }

        return $this;
    }

    public function fixAcl($data)
    {
        $basedn = $this->_session->localSysInfo['basedn'];
        $server = $this->_session->localSysInfo['hostname'];        

        if (!$conn = ldap_connect("localhost",389)) {
            throw new Zivios_Exception('Connection to Ldap service failed. Operation: fixAcl');
        }

        if (!ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new Zivios_Exception('Error setting Protocol Version 3 on LDAP connection');
        }

        if (!ldap_bind($conn, "cn=config", $data['cnconfigpass'])) {
            throw new Zivios_Exception('Could not bind to Ldap service. Operation: fixAcl');
        }
        
        // frontend acls.
        $frontendDbAcls = array();
        $frontendDbAcls['olcaccess'] = array(
            'to dn.base="" by * read',
            'to dn.base="cn=subschema" by * read'
        );
        
        $frontendDn = 'olcDatabase={-1}frontend,cn=config';

        if (!ldap_mod_replace($conn, $frontendDn, $frontendDbAcls)) {
            throw new Zivios_Error("Updating {-1}frontend ACLs failed.");
        }

        // cn=config acls
        $cnconfigDbAcls = array();
        $cnconfigDbAcls['olcaccess'] = array();
        $cnconfigDn = 'olcDatabase={0}config,cn=config';
        
        if (!ldap_mod_del($conn, $cnconfigDn, $cnconfigDbAcls)) {
            throw new Zivios_Error("Updating cn=config ACLs failed.");
        }

        // primary DB acls
        $primarydbAcls = array();
        $primarydbAcls['olcaccess'] = array(
            'to * by dn.base="uid=zldapreplica,ou=zusers,ou=core control,ou=zivios,dc=zivios,dc=net" read  by * break',
            'to attrs=userPassword,shadowLastChange by sockurl.regex="^ldapi:///$" write by dn="uid=zadmin,ou=zusers,ou=core control,ou=zivios,'.$basedn.'" write by anonymous auth',
            'to attrs=krb5PrincipalName by sockurl.regex="^ldapi:///$" write by dn="uid=zadmin,ou=zusers,ou=core control,ou=zivios,'.$basedn.'" write by anonymous auth',
            'to attrs=krb5KeyVersionNumber,krb5PrincipalRealm,krb5EncryptionType,krb5KDCFlags,krb5Key,krb5MaxLife,krb5MaxRenew,krb5PasswordEnd,krb5ValidEnd,krb5ValidStart,krb5RealmName '.
                'by sockurl.regex="^ldapi:///$" write by dn="uid=zadmin,ou=zusers,ou=core control,ou=zivios,'.$basedn.'" write by * none',
            'to dn.base="" by * read',
            'to * by sockurl.regex="^ldapi:///$" write by dn="uid=zadmin,ou=zusers,ou=core control,ou=zivios,'.$basedn.'" write by dynacl/aci write by * read'
        );

        $primarydbDn = 'olcDatabase={1}hdb,cn=config';

        if (!ldap_mod_replace($conn, $primarydbDn, $primarydbAcls)) {
            throw new Zivios_Error('Updating primary DB ACLs failed.');
        }

        return $this;
    }

    public function reinjectGroupMembers($data)
    {
        // get base dn
        $basedn = $this->_session->localSysInfo['basedn'];
        $server = $this->_session->localSysInfo['hostname'];
        
        // initialize required arrays
        $resetMembers     = array();
        $ziviosDnsMembers =  array();
        $ziviosAdmMembers = array();
        $ziviosGroups     = array();

        $resetMembers['member'] = array('cn=placeholder,'.$basedn);

        $ziviosDnsMembers['member'] = 
            array('cn=placeholder,'.$basedn,
                  'uid=zdnsuser,ou=zusers,ou=core control,ou=zivios,'.$basedn,
                  'uid=zdnsreplica,ou=zusers,ou=core control,ou=zivios,'.$basedn);

        $ziviosAdmMembers['member'] = 
            array('cn=placeholder,'.$basedn,
                  'uid=zadmin,ou=zusers,ou=core control,ou=zivios,'.$basedn,
                  'uid=zldapreplica,ou=zusers,ou=core control,ou=zivios,'.$basedn);

        $ziviosGroups = 
            array('cn=zdns,ou=zgroups,ou=core control,ou=zivios,'.$basedn,
                  'cn=zadmin,ou=zgroups,ou=core control,ou=zivios,'.$basedn);

        // bind to ldap as rootdn (kerberos has not been initialized as yet, hence binding as zadmin
        // will not work.
        $binddn = 'cn=admin,'.$basedn;
        $bindpw = $data['zadminpass'];

        if (!$conn = ldap_connect("localhost",389)) {
            throw new Zivios_Exception('Connection to Ldap service failed. Operation: reinject members');
        }

        if (!ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw new Zivios_Exception('Error setting Protocol Version 3 on LDAP connection');
        }

        if (!ldap_bind($conn, $binddn, $bindpw)) {
            throw new Zivios_Exception('Could not bind to Ldap service. Operation: reinject members. Bind: ' . $binddn . ' pass: ' . $bindpw);
        }
        
        // reset members
        foreach ($ziviosGroups as $group) {
            if (!ldap_mod_replace($conn, $group, $resetMembers)) {
                throw new Zivios_Error('LDAP error while resetting group membership.');
            }
        }

        // reinject members
        if (!ldap_mod_replace($conn, $ziviosGroups[0], $ziviosDnsMembers)) {
            throw new Zivios_Error('Error injecting DNS group members.');
        }

        if (!ldap_mod_replace($conn, $ziviosGroups[1], $ziviosAdmMembers)) {
            throw new Zivios_Error('Error injecting ZADMIN group members.');
        }

        return $this;
    }

    /**
     * Issues commands to the control script for the LDAP service.
     *
     * @return object $this
     */
    public function serviceAction($script, $action, $service='slapd')
    {
        $cmd = escapeshellcmd($script) . ' ' . escapeshellcmd($action);
        $rc = $this->_runLinuxCmd($cmd, true);

        sleep(2);

        if ($rc['exitcode'] != 0) {
            Zivios_Log::error('Could not '.$action. ' ' . $service . ' service.');
            throw new Zivios_Error('Could not '.$action.' '.$service . ' service. Please check logs.');
        }

        return $this;
    }

    public function getLdapConfig()
    {
        if (null === $this->_ldapConfig) {
            $this->_ldapConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', "openldap");
        }
        
        return $this->_ldapConfig;
    }
}

