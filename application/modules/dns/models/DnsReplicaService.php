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

class DnsReplicaService extends EMSService
{
    protected $_module = 'dns', $masterService = null;
    protected $tmpFolderName, $tmpFolderPath, $tmpReplicaFolder;

    public function init()
    {
        parent::init();
    }

    public function __construct($dn=null,$attrs=null,$acl=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emsdnsreplicahostname';
        parent::__construct($dn,$attrs,$acl);
    }
    
    /**
     * Add dns replica service to Zivios.
     *
     * @return Zivios_Transaction_Group
     */
    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emsdnsreplicaservice');
        $this->setProperty('emsdescription', 'Zivios DNS Replica Service');

        parent::add($parent, $tgroup);

        $this->_deployReplicaServices($tgroup, 'Deploying DNS Replica Services');
        $this->_startReplicaService($tgroup, 'Start DNS Replica Service');
    }

    /**
     * Start dns service on the replica server.
     *
     * @return int $exitcode
     */
    public function startReplicaService()
    {
        $this->_initCommAgent();
        $exitcode = $this->_commAgent->iniReplica();
        return $exitcode;
    }    
    
    public function deployReplicaServices()
    {
        $this->iniMasterService();
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $krbPlugin  = $this->mastercomp->getPlugin('KerberosComputer');

        $replicaServerData = array();
        $replicaServerData['search_domain'] = strtolower($krbPlugin->getProperty('krb5realmname'));
        $replicaServerData['local_domain']  = strtolower($krbPlugin->getProperty('krb5realmname'));
        
        if ($this->tmpFolderName == null) {
            Zivios_Log::error('Called deployReplicaServices before initializing tmpFolderPath.');
            throw new Zivios_Error('Temporary folder path not initialized.');
        }

        $templateBase = $this->getReplicaTemplatePath();
        $tmpDnsFolder = $this->tmpReplicaFolder . '/dns';

        if (!@mkdir($tmpDnsFolder, 0700)) {
            throw new Zivios_Error('Temp folder creation failed (dns)');
        }

        // Get dns config files.
        $dnsConfigFiles = $this->getDnsConfigFiles();

        // copy across static config files
        foreach ($dnsConfigFiles['copyFiles'] as $cfgFile) {
            $dest = $tmpDnsFolder . '/' . basename($cfgFile);
            if (!copy($cfgFile, $dest)) {
                throw new Zivios_Error('Dns config file copy failed. ('.$cfgFile.')'); 
            }
        }
        
        // generate RNDC data
        $dnsWorkFolder = $tmpDnsFolder . '/work';

        if (!@mkdir($dnsWorkFolder, 0700)) {
            throw new Zivios_Error('Temp folder creation failed (dns/work)');
        }
        
        $cwd = getcwd();

        if (!chdir($dnsWorkFolder)) {
            Zivios_Log::error('Error changing directory. Could not chdir to: ' . $dnsWorkFolder);
            throw new Zivios_Error('Error changing directory during DNS config generation.');
        }
        
        $cmd = '/opt/zivios/bind/sbin/dnssec-keygen -a hmac-md5 -b 256 -n HOST rndckeydata';
        exec($cmd);

        $d = dir($dnsWorkFolder);
        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..') {
                if (substr($entry, strrpos($entry, '.') + 1) == 'private') {
                    $fh = fopen($dnsWorkFolder.'/'.$entry, "r");
                    $contents = fread($fh, filesize($dnsWorkFolder.'/'.$entry));
                    fclose($fh);
                }
            }
        }

        $allC = split("\n",$contents);
        foreach ($allC as $c) {
            $len = (strlen($c) - 4) * -1;

            if (substr($c, 0, $len) == 'Key:') {
                $rndcKeyString = substr($c, 4);
                break;
            }
        }

        if (!chdir($cwd)) {
            throw new Zivios_Error('Error changing directory. Could not chdir to: ' . $cwd);
        }

        // read updateTemplates and write generated config file to 
        // dns temp folder.
        $replicaUser = $this->getBindReplicaUserDetails();

        $vals = array();
        $vals['base_dn']   = $ldapConfig->basedn;
        $vals['bind_user'] = $replicaUser['replica_user'];
        $vals['bind_pass'] = $replicaUser['replica_pass'];
        $vals['master_ip'] = $this->mastercomp->getip();
        
        // generate and write named.conf.local to dns tmp folder
        $namedlocaldata = Zivios_Util::renderTmplToCfg($dnsConfigFiles['updateFiles'][0], $vals);
        $namedlocaltmpl = $tmpDnsFolder . '/named.conf.local';
        
        // master_ip updated to master service IP for all additional templates barring the named.conf.local
        // one. The local DNS service should query the local LDAP server.
        $vals['master_ip'] = $this->masterService->mastercomp->getip();

        if (!$fp = fopen($namedlocaltmpl, 'w')) {
            throw new Zivios_Error('Could not open file: ' . $namedlocaltmpl . ' for writing.');
        }

        if (fwrite($fp, $namedlocaldata) === FALSE) {
            throw new Zivios_Error('Could not write data to file: ' . $namedlocaltmpl);
        }
        fclose($fp);
        
        // generate and write named.conf.options to dns tmp folder
        // note: forwarder is set to master server by default. 
        $forwarderLine = "\t\t" . $this->masterService->mastercomp->getip() . ";\n";
        $vals = array('forwarders' => $forwarderLine);

        $namedoptionsdata = Zivios_Util::renderTmplToCfg($dnsConfigFiles['updateFiles'][1], $vals);
        $namedoptionstmpl = $tmpDnsFolder . '/named.conf.options';
        
        if (!$fp = fopen($namedoptionstmpl, 'w')) {
            throw new Zivios_Error('Could not open file: ' . $namedoptionstmpl . ' for writing.');
        }

        if (fwrite($fp, $namedoptionsdata) === FALSE) {
            throw new Zivios_Error('Could not write data to file: ' . $namedoptionstmpl);
        }
        fclose($fp);

        // generate and write rndc.key to dns tmp folder
        $vals = array('rndc_key' => ltrim($rndcKeyString));
        $rndckeydata = Zivios_Util::renderTmplToCfg($dnsConfigFiles['updateFiles'][2], $vals);
        $rndckeytmpl = $tmpDnsFolder . '/rndc.key';
                
        if (!$fp = fopen($rndckeytmpl, 'w')) {
            throw new Zivios_Error('Could not open file: ' . $rndckeytmpl . ' for writing.');
        }

        if (fwrite($fp, $rndckeydata) === FALSE) {
            throw new Zivios_Error('Could not write data to file: ' . $rndckeytmpl);
        }
        fclose($fp);

        // generate and write defaults to dns tmp folder
        $vals = array('bind_user' => $replicaUser['replica_user']);
        $binddefaultsdata = Zivios_Util::renderTmplToCfg($dnsConfigFiles['updateFiles'][3], $vals);
        $defaultstmpl     = $tmpDnsFolder . '/defaults';

        if (!$fp = fopen($defaultstmpl, 'w')) {
            throw new Zivios_Error('Could not open file: ' . $defaultstmpl . ' for writing');
        }

        if (fwrite($fp, $binddefaultsdata) === FALSE) {
            throw new Zivios_Error('Could not write data to file: ' . $defaultstmpl);
        }
        fclose($fp);
        
        // generate and write resolv.conf to dns tmp folder
        $vals = array(
                    'search_domain' => $replicaServerData['search_domain'],
                    'local_domain'  => $replicaServerData['local_domain'] 
                );
        
        $resolvconfdata = Zivios_Util::renderTmplToCfg($dnsConfigFiles['updateFiles'][4], $vals);
        $resolvconftmpl = $tmpDnsFolder . '/resolv.conf';

        if (!$fp = fopen($resolvconftmpl, 'w')) {
            throw new Zivios_Error('Could not open file: ' . $defaultstmpl . ' for writing');
        }

        if (fwrite($fp, $resolvconfdata) === FALSE) {
            throw new Zivios_Error('Could not write data to file: ' . $resolvconftmpl);
        }
        fclose($fp);

        // send across generated files to replica server
        foreach ($dnsConfigFiles['copyFiles'] as $file) {
            $srcFile = $this->tmpReplicaFolder . '/dns/' . basename($file); 
            if (!file_exists($srcFile)) {
                Zivios_Log::error('DNS file: ' . $srcFile . ' not found during replica server initialization');
                throw new Zivios_Error('DNS file ' . $srcFile . ' not found');
            } else {
                // copy file to replicaServer
                $dest = '/opt/zivios/bind/etc/' . basename($file);
                // note: file permissions are being set to root with world-readable permissions
                // for static files.
                $this->mastercomp->putFile($srcFile, $dest, 0644, 'root', 'root');
            }
        }

        foreach ($dnsConfigFiles['updateFiles'] as $file) {
            // copy across generated DNS configuration data
            $srcFile = $this->tmpReplicaFolder . '/dns/' . substr(basename($file), 0, -5);

            if (!file_exists($srcFile)) {
                Zivios_Log::error('DNS file: ' . $srcFile . ' not found during replica server initialization');
                throw new Zivios_Error('DNS file: ' . $srcFile . ' not found during replica server initialization');
            } else {
                $fileToCopy = substr(basename($file), 0, -5);
                $dest = '/opt/zivios/bind/etc/' . $fileToCopy;
                $this->mastercomp->putFile($srcFile, $dest, 0660, 'root', 'root');
            }
        }
    }

    public function setServiceProperties($data)
    {
        // load replica computer object
        $this->mastercomp = Zivios_Ldap_Cache::loadDn($data['compatSystems']);
        
        // set replica properties
        $this->setProperty('emsmastercomputerdn', $data['compatSystems']);
        $this->setproperty('emsdnsreplicahostname', $this->mastercomp->getProperty('cn'));
        $this->setProperty('cn', $data['dnscn']);
    }

    public function iniMasterService()
    {
        if ($this->masterService === null) {
            $ldapConfig = Zend_Registry::get('ldapConfig');
            $dnsServiceDn = 'cn=zivios dns,ou=master services,ou=core control,'
                .'ou=zivios,' . $ldapConfig->basedn;

            $this->masterService = Zivios_Ldap_Cache::loadDn($dnsServiceDn);
        }

        return $this->masterService;
    }

    public function getPrimaryRootZone()
    {
        $this->iniMasterService();
        Zivios_Log::debug('calling get on emsdnsrootzone');
        $zone = $this->masterService->getProperty('emsdnsrootzone');
        Zivios_Log::debug('root zone is: ' . $zone);
        return $zone;
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

    private function getReplicaTemplatePath()
    {
        return APPLICATION_PATH . '/modules/' . $this->_module . '/library/templates/replica/';
    }

    private function getDnsConfigFiles()
    {
        $bindConfig  = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', 'bind');
        $configFiles = explode(',', $bindConfig->etcConfigData);
        
        $cfgFiles = array();
        foreach ($configFiles as $cfgFile) {
            $cfgFiles[] = APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/' . $cfgFile;
        }

        $updateTemplates   = array(
            APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/named.conf.local.tmpl',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/named.conf.options.tmpl',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/rndc.key.tmpl',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/defaults.tmpl',
            APPLICATION_PATH . '/library/Zivios/Install/Templates/bind/resolv.conf.tmpl'
        );
        
        $bindConfigFiles = array();
        $bindConfigFiles['copyFiles'] = $cfgFiles;
        $bindConfigFiles['updateFiles'] = $updateTemplates;
        return $bindConfigFiles;
    }

    private function getBindReplicaUserDetails()
    {
        $replConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/zadmin.ini', 'bindreplica');
        $replUserDetails = array(
            'replica_user'   => $replConfig->uid,
            'replica_pass'   => Zivios_Security::decrypt($replConfig->password),
            'replica_userdn' => $replConfig->dn
        );

        return $replUserDetails;
    }
}

