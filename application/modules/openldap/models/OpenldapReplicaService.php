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
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class OpenldapReplicaService extends EMSService
{
    protected $_module = 'openldap', $masterService;
    protected $tmpFolderName, $tmpFolderPath, $tmpReplicaFolder, $cnConfigPass;

    public function init()
    {
        parent::init();
    }

    public function __construct($dn=null,$attrs=null,$acl=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emsldapreplicarid';
        $attrs[] = 'emsldapreplicahostname';
        $attrs[] = 'emsldapreplicaconfigpw';

        parent::__construct($dn, $attrs, $acl);
    }
    
    /**
     * Add openldap replica service to Zivios.
     *
     * @return Zivios_Transaction_Group
     */
    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emsldapreplicaservice');
        $this->setProperty('emsdescription', 'Zivios Openldap Replica Service');

        parent::add($parent, $tgroup);

        $this->_deployReplica($tgroup, 'Deploying Openldap Replica Services');
        $this->_initializeReplica($tgroup, 'Start Openldap Replica Service');
        $this->_initializeLdapConfig($tgroup, 'Configuring LDAP Client and NSS Service');
    }

    public function deployReplica()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');

        // Copy over Zivios Core replicas to target replica server
        // Get installer configuration from registry
        $installConfig = Zend_Registry::get('installConfig');
        $coreLdifBase = APPLICATION_PATH . '/library/Zivios/Install/Templates/openldap/ldifs';
        $requiredLdifs = explode(',', $installConfig->openldap->coreLdifs);

        foreach ($requiredLdifs as $ldif) {
            $reqLdif = $coreLdifBase . '/' . $ldif;
            $destDir = '/opt/zivios/openldap/etc/openldap/schema';

            if (!file_exists($reqLdif)) {
                throw new Zivios_Error('Required LDIF: ' . $reqLdif . ' not found.');
            }

            $destLdif = $destDir . '/' . $ldif;
            $this->mastercomp->putFile($reqLdif, $destLdif, 0644, 'root', 'root');
        }

        // Make ready data array for template generation.
        $replicaServerData  = array();
        $caPlugin           = $this->mastercomp->getPlugin('CaComputer');
        $krbPlugin          = $this->mastercomp->getPlugin('KerberosComputer');
        $replicaUserDetails = $this->getReplicaUserDetails();
        $chainUserDetails = $this->getChainUserDetails();

        $replicaServerData['run_dir']       = $installConfig->openldap->runDir;
        $replicaServerData['libexec_dir']   = $installConfig->openldap->libexec;
        $replicaServerData['data_dir']      = $installConfig->openldap->dataDir;
        $replicaServerData['conf_dir']      = $installConfig->openldap->confDdir;
        $replicaServerData['replica_host']  = $this->mastercomp->getProperty('cn');
        $replicaServerData['krb5_realm']    = $krbPlugin->getProperty('krb5realmname');
        $replicaServerData['ca_cert']       = $caPlugin->getCaCertPath();
        $replicaServerData['pub_key']       = $caPlugin->getPubKeyPath();
        $replicaServerData['prv_key']       = $caPlugin->getPrvKeyPath();
        $replicaServerData['cnconfig_pass'] = "{SHA}" . base64_encode(sha1($this->cnConfigPass, TRUE));
        $replicaServerData['master_server'] = $ldapConfig->host;
        $replicaServerData['base_dn']       = $ldapConfig->basedn;
        $replicaServerData['repl_pass']     = $replicaUserDetails['replica_pass'];
        $replicaServerData['replica_userdn']= strtolower($replicaUserDetails['replica_userdn']);
        $replicaServerData['rid']           = $this->getProperty('emsldapreplicarid');
        $replicaServerData['chain_dn']       = $chainUserDetails['chain_dn'];
        $replicaServerData['chain_pass']       = $chainUserDetails['chain_pass'];

        // read base LDIF templates and copy to replica server.
        $replicaBaseLdifs = $this->getReplicaBaseLdifs();
        
        $generatedTemplates = array();
        foreach ($replicaBaseLdifs as $templateName => $replicaLdif) {
            $generatedTemplates[$templateName] = Zivios_Util::renderTmplToCfg($replicaLdif, $replicaServerData);
        }

        // we copy the configuration files to the replica server in the primary
        // openldap configuration directory. The files are injected by the remote
        // Zivios Agent from that point on. File access is restricted to the root
        // user, where the Agent will remove these temporary files after injection.
        $baseDestLdifDir = '/opt/zivios/openldap/etc/openldap';

        foreach ($generatedTemplates as $templateName => $baseTemplateData) {
            $baseDestLdif = $baseDestLdifDir . '/' . $templateName;
            $this->mastercomp->putFileFromString($baseTemplateData, $baseDestLdif, 0600, 'root', 'root');
        }

        // send across slapd.defaults to target replica server
        $ldapTemplateBase = APPLICATION_PATH . '/modules/' . $this->_module . 
            '/library/templates/replica';

        $requiredFiles = array(
            'slapd.defaults' => '/opt/zivios/openldap/etc/openldap'
        );

        foreach ($requiredFiles as $file => $location) {
            $templateFile = $ldapTemplateBase . '/' . $file;
            $tDestination = $location . '/' . $file;
            $this->mastercomp->putFile($templateFile, $tDestination, 0644, 'root', 'root');
        }
    }

    /**
     * Communicate base LDIF injection to replica server via Zivios Agent. Call
     * additionally starts the slapd daemon and updates nss and ldap client configuration.
     * 
     */
    public function initializeReplica()
    {
        $this->_initCommAgent();
        $exitcode = $this->_commAgent->initializeReplicaData();

        if ($exitcode != 0) {
            throw new Zivios_Error('Error initializing LDAP Replica. Please check system logs.');
        }

        return $exitcode;
    }

    public function initializeLdapConfig()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $ldapPlugin = $this->mastercomp->getPlugin('OpenldapComputer');
        $caPlugin   = $this->mastercomp->getPlugin('CaComputer');
        $caPlugin   = $this->mastercomp->getPlugin('CaComputer');

        $nssdata = array(
            'ldap_base_dn' => $ldapConfig->basedn,
            'ldap_host'    => 'localhost',
            'ca_cert'      => $caPlugin->getCaCertPath(),
            'pub_key'      => $caPlugin->getPubKeyPath(),
            'prv_key'      => $caPlugin->getPrvKeyPath()
        );

        $ldapPlugin->updateReplicaLdapConfig($nssdata);
    }

    /**
     * Start directory services on the replica server.
     *
     * @return int $exitcode
     */
    public function startReplicaService()
    {
        $this->_initCommAgent();
        $exitcode = $this->_commAgent->startService();

        return $exitcode;
    }

    /**
     * Get the replication status of the replica server in question by comparing
     * contextcsn values for the basedn
     *
     * Call returns an array with the master server's contextcsn, the replica's contextcsn
     * as well as a syncStatus index (boolean true|false). 
     * 
     * @return array $replicaStatus
     */
    public function getReplicationStatus()
    {
        $bindDetails = $this->getReplicaBindDetails();
        $host = $this->mastercomp->getProperty('cn');
        $port = 389;

        $ldapReplicaConn = new Zivios_Ldap_Engine();

        if (!$ldapReplicaConn->connectToOtherLdap(
            $bindDetails->dn,
            Zivios_Security::decrypt($bindDetails->password),
            $host,
            $port
        )) {
            Zivios_Log::debug('auth failed with login: ' . $bindDetails->dn . ' and pass: ' . Zivios_Security::decrypt($bindDetails->password));
            throw new Zivios_Error('Failed to connect to replica server.');
        }
        
        // load LDAP Config
        $ldapConfig = Zend_Registry::get('ldapConfig');

        // get the contextCsn for the replica directory service
        $replicaContextCsn = $ldapReplicaConn->sp_query($ldapConfig->basedn, 'contextcsn');

        // get the contextCsn for the master directory service
        $masterContextCsn = $this->mastercomp->sp_query($ldapConfig->basedn, 'contextcsn');
        
        if ($masterContextCsn != $replicaContextCsn) {
            $syncStatus = false;
        } else {
            $syncStatus = true;
        }

        $replicaStatus = array(
            'syncStatus' => $syncStatus,
            'masterContextCsn' => $masterContextCsn,
            'replicaContextCsn' => $replicaContextCsn
        );

        return $replicaStatus;
    }

    /**
     * Communicate with LDAP replica and associated services (heimdal & sasl) to determine their
     * current status (running | stopped).
     *
     * @return array
     */
    public function getReplicaServiceStatus()
    {
        $this->_initCommAgent();        
        $serviceStatus = $this->_commAgent->getReplicaServiceStatus();
        
        // index & send array
        return array(
            'slapd' => $serviceStatus[0],
            'krb'   => $serviceStatus[1],
            'sasl'  => $serviceStatus[2],
            'bind'  => $serviceStatus[3]
        );
    }

    public function deployReplicaServices()
    {
        // initialize LDAP Configuration
        $ldapConfig = Zend_Registry::get('ldapConfig');

        if ($this->tmpFolderName == null) {
            Zivios_Log::error('Called deployReplicaServices before initializing tmpFolderPath.');
            throw new Zivios_Error('Temporary folder path not initialized.');
        }

        if (!$this->masterService->initializeSchemaPack($this->tmpFolderName)) {
            Zivios_Log::error('Schema copy failed during Replica initialization. Check master server agent logs.');
            throw new Zivios_Error('Schema copy on master server failed. Please check logs.');
        }

        // prepare replica server data array
        $replicaServerData  = array();
        $caPlugin           = $this->mastercomp->getPlugin('CaComputer');
        $krbPlugin          = $this->mastercomp->getPlugin('KerberosComputer');
        $replicaUserDetails = $this->getReplicaUserDetails();
        Zivios_Log::debug($chainUserDetails);
        $replicaServerData['replica_host']  = $this->mastercomp->getProperty('cn');
        $replicaServerData['krb5_realm']    = $krbPlugin->getProperty('krb5realmname');
        $replicaServerData['pub_key']       = $caPlugin->getPubKeyPath();
        $replicaServerData['prv_key']       = $caPlugin->getPrvKeyPath();
        $replicaServerData['cnconfig_pass'] = "{SHA}" . base64_encode(sha1($this->cnConfigPass, TRUE));
        $replicaServerData['master_server'] = $ldapConfig->host;
        $replicaServerData['base_dn']       = $ldapConfig->basedn;
        $replicaServerData['repl_pass']     = $replicaUserDetails['replica_pass'];
        $replicaServerData['replica_user']  = $replicaUserDetails['replica_user'];
        $replicaServerData['rid']           = $this->getProperty('emsldapreplicarid');
        
        Zivios_Log::debug($replicaServerData);

        
        $templateBase = $this->getReplicaTemplatePath();

        // create olcDatabase folder
        $folder = $this->tmpReplicaFolder . '/slapd.d/cn=config/olcDatabase={2}hdb';

        if (!@mkdir($folder, 0700)) {
            throw new Zivios_Error('Temp folder creation failed. (slapd.d)');
        }

        // copy over cn=config files
        $cnConfigFiles = $this->getCnConfigFiles();

        $updateTemplates = array(
            'slapd.d/cn=config.ldif',
            'slapd.d/cn=config/olcDatabase={0}config.ldif',
            'slapd.d/cn=config/olcDatabase={2}hdb.ldif'
        );

        foreach ($cnConfigFiles as $file) {
            $fullPath = $templateBase . $file;

            if (!file_exists($fullPath)) {
                throw new Zivios_Exception('Required replica template missing: ' . $fullPath);
            } else {
                // destination 
                $destFile = $this->tmpReplicaFolder . '/' . $file;
                
                if (in_array($file, $updateTemplates)) {
                    // read in template and write file as required
                    $cnConfigTmpl = Zivios_Util::renderTmplToCfg($fullPath, $replicaServerData);

                    if (!$fp = fopen($destFile, "w")) {
                        throw new Zivios_Exception("Could not open file (cn=config template) for writing in tmp folder.");
                    }
                    
                    if (fwrite($fp, $cnConfigTmpl) === FALSE) {
                        throw new Zivios_Exception("Could not write template to file.");
                    }

                    fclose($fp);
                } else {
                    // simple copy operation.
                    if (!@copy($fullPath, $destFile)) {
                        throw new Zivios_Error('cn=config file copy failed.');
                    }
                    
                    // ensure config files in tmp are not world readable
                    if (!@chmod($destFile, 0600)) {
                        throw new Zivios_Error('cn=config file set permission failed.');
                    }
                }
            }
        }

        // copy across the slapd.defaults file
        $slapdDefaults = $templateBase . '/slapd.defaults';

        if (!@copy($slapdDefaults, $this->tmpReplicaFolder . '/slapd.defaults')) {
           throw new Zivios_Error('slapd.defaults file copy failed.'); 
        }

        // copy files to replica server
        $slapdConfigFiles   = $this->getCnConfigFiles();
        $slapdConfigSchemas = $this->getCnConfigSchemaFiles();

        $slapdFiles = array_merge($slapdConfigFiles, $slapdConfigSchemas);

        foreach ($slapdFiles as $slapdfile) {
            $fullPath = $this->tmpReplicaFolder . '/' . $slapdfile;

            if (!file_exists($fullPath)) {
                throw new Zivios_Error('file: ' . $fullPath . ' not found.');
            } else {
                // copy across file via Zivios Agent
                $dest = '/opt/zivios/openldap/etc/openldap/' . $slapdfile;
                Zivios_Log::debug('copy file: ' . $fullPath . ' to: ' . $dest);
                $this->mastercomp->putFile($fullPath, $dest, 0600, 'root', 'root');
            }
        }

        // copy across 'ldap.conf' for client lookups to the replica server
        $clientConfTemplate = APPLICATION_PATH . '/modules/' . $this->_module . 
            '/library/templates/replica/ldap.conf.tmpl';
        $dest = '/etc/ldap.conf';

        if (!file_exists($clientConfTemplate)) {
            throw new Zivios_Error('file: ' . $clientConfTemplate . ' not found.');
        }
        
        $ldapClientData = array(
            'ldap_base_dn' => $ldapConfig->basedn,
            'ldap_host' => $this->mastercomp->getProperty('cn')
        );

        $ldapClientTmpl = Zivios_Util::renderTmplToCfg($clientConfTemplate, $ldapClientData);
        $this->mastercomp->putFileFromString($ldapClientTmpl, $dest, 0644, 'root', 'root');
    }
    
    /**
     * Sets the temp folder path
     *
     */
    public function setTempFolderPath($folderPath, $folderName)
    {
        $this->tmpFolderName    = $folderName;
        $this->tmpFolderPath    = $folderPath;
        $this->tmpReplicaFolder = $this->tmpFolderPath . '/' . $this->tmpFolderName;
    }

    /**
     * check the replica server for compatibility and required packages.
     *
     */
    public function testPrerequisites()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');

        if (!$this->mastercomp->pingAgent()) {
            throw new Zivios_Error('Communication failure with zivios agent');
        }
        
        $this->_iniClusterConfig();
        $targetClusterConfig = $this->_getClusterTargetConfig($this->mastercomp->getComputerDistroId());

        // refresh target server's package listing
        $pkgPlugin = $this->mastercomp->getPackagePlugin();
        $pkgPlugin->populatePackages();

        // get required packages on cluster target
        $reqClusterPkgs = explode(',', $targetClusterConfig->pkgsrequired);

        $reqPackages = array();
        foreach ($reqClusterPkgs as $package) {
            $piter = explode('|',$package);
            $reqpackages[$piter[0]] = $piter[1];
        }
        
        // Ensure all required packages exist
        if (true !== ($pkgStatus = $this->mastercomp->hasPackages($reqpackages))) {
            throw new Zivios_Error('packages missing: ' . $pkgStatus);
        }
    }

    /**
     * Setup openldap replica details. Call returns the replica ID, replica server cn
     * as well as the replica server dn for service registration against master zivios
     * directory
     *
     * @return array $replicaData
     */
    public function setServiceProperties($data)
    {
        $this->iniMasterService();
        $replicas = $this->masterService->getReplicas();

        $replicaDns = array();
        if (!empty($replicas)) {
            foreach ($replicas as $replicaData) {
                $replicaDns[] = $replicaData[1];
            }
        }
        
        $calcRid = 0;
        if (!empty($replicas)) {
            foreach ($replicas as $rid => $ridData) {
                $calcRid = $rid + 1;
            }
        }
        
        // load replica computer object
        $this->mastercomp = Zivios_Ldap_Cache::loadDn($data['compatSystems']);
        
        // set replica properties
        $this->setProperty('emsmastercomputerdn', $data['compatSystems']);
        $this->setProperty('emsldapreplicarid', $calcRid);
        $this->setproperty('emsldapreplicahostname', $this->mastercomp->getProperty('cn'));
        $this->setProperty('emsldapreplicaconfigpw', Zivios_Security::encrypt($data['cnpass']));
        $this->setProperty('cn', $data['ldapcn']);

        // store the password for back-ldap
        $this->cnConfigPass = $data['cnpass'];
        
        $replicaData = array();
        $replicaData['cn'] = $this->mastercomp->getProperty('cn');
        $replicaData['dn'] = 'cn='.$data['ldapcn'].','.$data['dn'];
        $replicaData['rid'] = $calcRid;

        return $replicaData;
    }

    public function iniMasterService()
    {
        if ($this->masterService === null) {
            $ldapConfig = Zend_Registry::get('ldapConfig');
            $directoryServiceDn = 'cn=zivios directory,ou=master services,ou=core control,'
                .'ou=zivios,' . $ldapConfig->basedn;
        }

        $this->masterService = Zivios_Ldap_Cache::loadDn($directoryServiceDn);
        return $this->masterService;
    }

    private function getReplicaBaseLdifs()
    {
        $baseLdifPath = APPLICATION_PATH . '/modules/' . $this->_module . 
            '/library/templates/replica/ldifs/';

        return array(
            '1_cnbase.ldif'    => $baseLdifPath . '1_cnbase.ldif',
            '2_cnmodules.ldif' => $baseLdifPath . '2_cnmodules.ldif',
            '3_primarydb.ldif' => $baseLdifPath . '3_primarydb.ldif',
            '4_chain.ldif' => $baseLdifPath . '4_chain.ldif'
        );
    }

    private function getReplicaTemplatePath()
    {
        return APPLICATION_PATH . '/modules/' . $this->_module . '/library/templates/replica/';
    }

    private function getCnConfigFiles()
    {
        return array(
            'slapd.defaults',
            'slapd.d/cn=config.ldif',
            'slapd.d/cn=config/olcDatabase={2}hdb/olcOverlay={0}syncprov.ldif',
            'slapd.d/cn=config/olcDatabase={2}hdb/olcOverlay={1}smbk5pwd.ldif',
            'slapd.d/cn=config/cn=module{0}.ldif',
            'slapd.d/cn=config/olcDatabase={-1}frontend.ldif',
            'slapd.d/cn=config/olcDatabase={0}config.ldif',
            'slapd.d/cn=config/olcDatabase={1}monitor.ldif',
            'slapd.d/cn=config/olcDatabase={2}hdb.ldif'
        );
    }

    private function getCnConfigSchemaFiles()
    {
        return array(
            'slapd.d/cn=config/cn=schema.ldif',
            'slapd.d/cn=config/cn=schema/cn={0}core.ldif',
            'slapd.d/cn=config/cn=schema/cn={1}cosine.ldif',
            'slapd.d/cn=config/cn=schema/cn={2}inetorgperson.ldif',
            'slapd.d/cn=config/cn=schema/cn={3}rfc2307bis.ldif',
            'slapd.d/cn=config/cn=schema/cn={4}rfc2739.ldif',
            'slapd.d/cn=config/cn=schema/cn={5}samba.ldif',
            'slapd.d/cn=config/cn=schema/cn={6}qmail.ldif',
            'slapd.d/cn=config/cn=schema/cn={7}hdb.ldif',
            'slapd.d/cn=config/cn=schema/cn={8}dlz.ldif',
            'slapd.d/cn=config/cn=schema/cn={9}dhcp.ldif',
            'slapd.d/cn=config/cn=schema/cn={10}amavis.ldif',
            'slapd.d/cn=config/cn=schema/cn={11}asterisk.ldif',
            'slapd.d/cn=config/cn=schema/cn={12}zivios-core.ldif',
            'slapd.d/cn=config/cn=schema/cn={13}zivios-mail.ldif',
            'slapd.d/cn=config/cn=schema/cn={14}zivios-squid.ldif',
            'slapd.d/cn=config/cn=schema/cn={15}zivios-asterisk.ldif',
            'slapd.d/cn=config/cn=schema/cn={16}zivios-samba.ldif'
        );
    }

    private function getReplicaUserDetails()
    {
        $replConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/zadmin.ini', 'replicator');
        $replUserDetails = array(
            'replica_user'   => $replConfig->uid,
            'replica_pass'   => Zivios_Security::decrypt($replConfig->password),
            'replica_userdn' => $replConfig->dn
        );

        return $replUserDetails;
    }
    
    private function getChainUserDetails()
    {
        $replConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/zadmin.ini', 'replicator');
        $chainUserDetails = array(
            'chain_dn'   => $replConfig->chainauthzdn,
            'chain_pass'   => Zivios_Security::decrypt($replConfig->chainauthzpass),
        );

        return $chainUserDetails;
    }

    /**
     * Get replicator user details
     * 
     * @return Zend_Config_Ini object
     */
    private function getReplicaBindDetails()
    {
        return new Zend_Config_Ini(APPLICATION_PATH . '/config/zadmin.ini', 'replicator');
    }
}

