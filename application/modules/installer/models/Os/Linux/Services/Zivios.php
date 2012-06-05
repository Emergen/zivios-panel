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

class Os_Linux_Services_Zivios extends Os_Linux
{
    protected $_zvagentConfig, $_zpanelConfig;

    public function __construct()
    {
        parent::__construct();
        return $this;
    }

    public function iniAgentSetup()
    {
        Zivios_Log::info('Initializing Zivios Agent configuration', 'clogger');
        $zAgentConfig = $this->getAgentConfig();
        $caConfig = $this->getDistroClass()
                         ->getKrb5Handler()
                         ->getCaConfig();


        $hostname = $this->_session->localSysInfo["hostname"];

        $sslcert = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $sslkey  = $caConfig->privateCerts . '/' . $hostname . '.key';

        $distroId = strtolower($this->_session->osDetails['distro']) . '-' . 
            strtolower($this->_session->osDetails['codename']);

        $vals = array();
        $vals['master_computer'] = $this->_session->localSysInfo['hostname'];
        $vals['base_dn']         = $this->_session->localSysInfo['basedn'];
        $vals['pub_cert']        = $sslcert;
        $vals['prv_key']         = $sslkey;
        $vals['distro_id']       = $distroId;

        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/zpanel/ziviosagentmanager.ini.tmpl';

        $dftlSource = APPLICATION_PATH .
                '/library/Zivios/Install/Templates/zpanel/defaults.tmpl';

        if (!file_exists($tmplSource) || !is_readable($tmplSource)) {
            throw new Zivios_Expcetion("Could not find Zivios agent template: " . $tmplSource);
        }

        if (!file_exists($dftlSource) || !is_readable($dftlSource)) {
            throw new Zivios_Expcetion("Could not find Zivios agent defaults file: " . $tmplSource);
        }

        $agenttmpldata  = Zivios_Util::renderTmplToCfg($tmplSource, $vals);
        $tmpagentconf   = $this->linuxConfig->tmpFolder . '/' . 'ZiviosAgentManager.ini';

        if (!$fp = fopen($tmpagentconf, "w"))
            throw new Zivios_Exception('Could not open file: '.$tmpagentconf.' for writing in tmp folder.');

        if (fwrite($fp, $agenttmpldata) === FALSE)
            throw new Zivios_Exception("Could not write agent configuration template to file.");
        fclose($fp);

        $this->_copyFile($tmpagentconf, $zAgentConfig->configDir, '0640', 'root', 'root');
        $this->_copyFile($dftlSource, $zAgentConfig->defaultsFile, '0640', 'root', 'root');

        Zivios_Log::info('Zivios Agent configuration successful', 'clogger');

        return $this;
    }

    public function iniPanelSetup($data)
    {
        Zivios_Log::info('Initializing Zivios Panel configuration', 'clogger');
        $zPanelConfig = $this->getPanelConfig();
        $caConfig     = $this->getDistroClass()
                             ->getKrb5Handler()
                             ->getCaConfig();

        $hostname = $this->_session->localSysInfo["hostname"];

        $sslcert      = $caConfig->publicCerts  . '/' . $hostname . '.crt';
        $sslkey       = $caConfig->privateCerts . '/' . $hostname . '.key';
        $cacert       = $caConfig->anchors      . '/' . $caConfig->rootPubCert;
        $securityKey  = $zPanelConfig->securityKey;
        $appConfig    = $zPanelConfig->appConfigIni;
        $zadminConfig = $zPanelConfig->zadminConfigIni;

        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/zpanel/app.config.ini.tmpl';

        if (!file_exists($tmplSource) || !is_readable($tmplSource)) {
            throw new Zivios_Expcetion("Could not find Zivios agent template: " . $tmplSource);
        }

        $vals = array();
        $vals['company_name']      = $this->_session->companyName;
        $vals['db_host']           = $this->_session->dbInfo['dbhost'];
        $vals['db_name']           = $this->_session->dbInfo['dbname'];
        $vals['db_user']           = $this->_session->dbInfo['zdbuser'];
        $vals['db_pass']           = $this->_session->dbInfo['zdbpass'];
        $vals['db_socket']         = $this->_session->dbInfo['socket'];
        $vals['master_computer']   = $this->_session->localSysInfo['hostname'];
        $vals['base_dn']           = $this->_session->localSysInfo['basedn'];
        $vals['krb5_realm']        = $this->_session->localSysInfo['krb5realm'];
        $vals['session_save_path'] = $zPanelConfig->sessionSavePath;
        $vals['security_key']      = $securityKey;
        $vals['ca_pubcert']        = $cacert;
        $vals['host_pubcert']      = $sslcert;
        $vals['host_prvkey']       = $sslkey;
        $vals['app_log']           = $zPanelConfig->zpanelLog;
        $vals['trans_log']         = $zPanelConfig->transactionLog;
        $vals['notify_log']        = $zPanelConfig->notificationLog;
        $vals['zivios_version']    = $zPanelConfig->version;
        $vals['base_path']         = $zPanelConfig->base;

		$appconfigdata  = Zivios_Util::renderTmplToCfg($tmplSource, $vals);

		if (!$fp = fopen($appConfig, "w")) {
            throw new Zivios_Exception('Error: could not open Zivios configuration file: ' .
                $appConfig . ' for writing');
        }

		if (fwrite($fp, $appconfigdata) === FALSE) {
            throw new Zivios_Exception('Error: could not write data to Zivios configuration file: ' .
                $appConfig . '.');
        }

		fclose($fp);
    
        // Generate and write a security key.
        $randData = Zivios_Util::randomString(23);

        if (!$fp = fopen($securityKey, "w")) {
            throw new Zivios_Exception('Error: could not open security key: ' . 
                $securityKey . ' for writing.');
        }

        if (fwrite($fp, $randData."\n") === FALSE) {
            throw new Zivios_Exception('Error: could not write data to security key: ' .
                $securityKey .'.');
        }
        fclose($fp);

        if (!chmod($securityKey, 0600)) {
            throw new Zivios_Exception('Could not set permissions on security key: ' . 
                $securityKey . '.');
        }

        $tmplSource = APPLICATION_PATH . 
                '/library/Zivios/Install/Templates/zpanel/zadmin.ini.tmpl';

        if (!file_exists($tmplSource) || !is_readable($tmplSource)) {
            throw new Zivios_Expcetion("Could not find Zivios admin template: " . $tmplSource);
        }

        $vals                    = array();
        $vals['zadmin_pass']     = Zivios_Security::encrypt($this->_session->zadminPass, $securityKey);
        $vals['cnconfig_pass']   = Zivios_Security::encrypt($this->_session->cnconfigPass, $securityKey);
        $vals['replicator_pass'] = Zivios_Security::encrypt($this->_session->replicatorPass, $securityKey);
        $vals['binduser_pass']   = Zivios_Security::encrypt($this->_session->bindPass, $securityKey);
        $vals['bindreplica_pass']= Zivios_Security::encrypt($this->_session->bindReplicaPass, $securityKey);
        $vals['base_dn']         = $this->_session->localSysInfo['basedn'];

        $zadminconfigdata = Zivios_Util::renderTmplToCfg($tmplSource, $vals);
        $zadminconfigfile = $zadminConfig;

        if (!$fp = fopen($zadminConfig, "w")) {
            throw new Zivios_Exception('Error: could not open zadmin config file: ' .
                $zadminConfig . ' for writing.');
        }
        
        if (fwrite($fp, $zadminconfigdata) === FALSE) {
            throw new Zivios_Exception('Error: could not write zadmin config data to file: ' .
                $zadminConfig . '.');
        }
        fclose($fp);

        Zivios_Log::info('Zivios Panel configuration successful', 'clogger');

        return $this;
    }

    public function serviceAction($script, $action)
    {
        $cmd = escapeshellcmd($script) . ' ' . escapeshellcmd($action);
        $this->_runLinuxCmd($cmd, true);
        sleep(2);
        return $this;
    }


    public function getAgentConfig()
    {
        if (null === $this->_zvagentConfig) {
            $this->_zvagentConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', "zvagent");
        }
        
        return $this->_zvagentConfig;
    }

    public function getPanelConfig()
    {
        if (null === $this->_zpanelConfig) {
            $this->_zpanelConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini', "zpanel");
        }
        
        return $this->_zpanelConfig;
    }
}
