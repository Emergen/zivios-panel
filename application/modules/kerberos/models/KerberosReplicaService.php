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

class KerberosReplicaService extends EMSService
{
    protected $_module = 'kerberos', $masterService = null;
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

        $attrs[] = 'emskrbreplicahostname';
        parent::__construct($dn,$attrs,$acl);
    }
    
    /**
     * Add openldap replica service to Zivios.
     *
     * @return Zivios_Transaction_Group
     */
    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emskrbreplicaservice');
        $this->setProperty('emsdescription', 'Zivios Kerberos Replica Service');

        parent::add($parent, $tgroup);

        $this->_deployReplicaServices($tgroup, 'Deploying Kerberos Replica Services');
        $this->_startReplicaService($tgroup, 'Start Kerberos Replica Service');
    }

    public function deployReplicaServices()
    {
        // initialize LDAP Configuration
        $ldapConfig = Zend_Registry::get('ldapConfig');

        if ($this->tmpFolderName == null) {
            Zivios_Log::error('Called deployReplicaServices before initializing tmpFolderPath.');
            throw new Zivios_Error('Temporary folder path not initialized.');
        }

        $krbPlugin = $this->mastercomp->getPlugin('KerberosComputer');
        
        $replicaServerData = array();
        $replicaServerData['krb5_realm']    = $krbPlugin->getProperty('krb5realmname');
        $replicaServerData['kdc_host']      = $this->mastercomp->getProperty('cn');
        $replicaServerData['kadmin_host']   = $ldapConfig->host;
        $replicaServerData['lc_krb5_realm'] = strtolower($krbPlugin->getProperty('krb5realmname'));
        $replicaServerData['base_dn']       = $ldapConfig->basedn;
        
        $templateBase = $this->getReplicaTemplatePath();
        $tmpHeimdalFolder = $this->tmpReplicaFolder . '/heimdal';

        if (!@mkdir($tmpHeimdalFolder, 0700)) {
            throw new Zivios_Error('Temp folder creation failed (heimdal).');
        }

        $heimdalDefault = $templateBase . '/heimdal/heimdal.defaults';
        $heimdalkrb5    = $templateBase . '/heimdal/krb5.conf';

        if (!@copy($heimdalDefault, $tmpHeimdalFolder . '/heimdal.defaults')) {
           throw new Zivios_Error('heimdal.defaults file copy failed.'); 
        }

        $krb5Tmpl = Zivios_Util::renderTmplToCfg($heimdalkrb5, $replicaServerData);
        $destFile = $tmpHeimdalFolder . '/krb5.conf';

        if (!$fp = fopen($destFile, "w")) {
            throw new Zivios_Exception("Could not open file (heimdal template: krb5.conf) for writing in tmp folder.");
        }
                    
        if (fwrite($fp, $krb5Tmpl) === FALSE) {
            throw new Zivios_Exception("Could not write template to file.");
        }

        fclose($fp);

        // kerberos data generation complete
        // generate cyrus-sasl configuration
        $tmpCyrusFolder = $this->tmpReplicaFolder . '/cyrus-sasl';

        if (!@mkdir($tmpCyrusFolder, 0700)) {
            throw new Zivios_Error('Temp folder creation failed (cyrus).');
        }

        $cyrusDefaults = $templateBase . '/cyrus-sasl/defaults';

        if (!@copy($cyrusDefaults, $tmpCyrusFolder . '/defaults')) {
           throw new Zivios_Error('cyrus defaults file copy failed.'); 
        }

        // send across generated files to replica server
        $heimdalFiles = $this->getHeimdalFiles();
        foreach ($heimdalFiles as $heimdalfile) {
            $fullPath = $this->tmpReplicaFolder . '/heimdal/' . $heimdalfile;

            if (!file_exists($fullPath)) {
                throw new Zivios_Error('file: ' . $fullPath . ' not found');
            } else {
                $dest = '/opt/zivios/heimdal/etc/' . $heimdalfile;
                Zivios_Log::debug('copy file: ' . $fullPath . ' to: ' . $dest);
                $this->mastercomp->putFile($fullPath, $dest, 0600, 'root', 'root');
            }
        }

        $cyrusFiles = $this->getCyrusSaslFiles();
        foreach ($cyrusFiles as $cyrusfile) {
            $fullPath = $this->tmpReplicaFolder . '/cyrus-sasl/' . $cyrusfile;

            if (!file_exists($fullPath)) {
                throw new Zivios_Error('file: ' . $fullPath . ' not found');
            } else {
                $dest = '/opt/zivios/cyrus-sasl/etc/' . $cyrusfile;
                Zivios_Log::debug('copy file: ' . $fullPath . ' to: ' . $dest);
                $this->mastercomp->putFile($fullPath, $dest, 0600, 'root', 'root');
            }
        }
    }

    /**
     * Start Kerberos "replica" services. The agent call encapsulates
     * ensuring symlink testing as well as cyrus-sasl service start. 
     *
     * @return int $exitcode
     */
    public function startReplicaService()
    {
        $this->_initCommAgent();
        $exitcode = $this->_commAgent->startReplicaService();
        return $exitcode;
    }

    public function setServiceProperties($data)
    {
        // load replica computer object
        $this->mastercomp = Zivios_Ldap_Cache::loadDn($data['compatSystems']);
        
        // set replica properties
        $this->setProperty('emsmastercomputerdn', $data['compatSystems']);
        $this->setproperty('emskrbreplicahostname', $this->mastercomp->getProperty('cn'));
        $this->setProperty('cn', $data['krbcn']);
    }

    public function iniMasterService()
    {
        if ($this->masterService === null) {
            $ldapConfig = Zend_Registry::get('ldapConfig');
            $krbServiceDn = 'cn=zivios kerberos,ou=master services,ou=core control,'
                .'ou=zivios,' . $ldapConfig->basedn;
            $this->masterService = Zivios_Ldap_Cache::loadDn($krbServiceDn);
        }

        return $this->masterService;
    }

    public function extractKeytab($principal)
    {
        $this->iniMasterService();
        return $this->masterService->extractKeytab($principal);
    }

    public function setRandPw($host)
    {
        $krbConfig = Zend_Registry::get('krbMaster');
        $this->iniMasterService();
        return $this->masterService->setrandpw($host, $krbConfig->realm);
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

    private function getHeimdalFiles()
    {
        return array(
            'krb5.conf',
            'heimdal.defaults'
        );
    }

    private function getCyrusSaslFiles()
    {
        return array(
            'defaults'
        );
    }
}

