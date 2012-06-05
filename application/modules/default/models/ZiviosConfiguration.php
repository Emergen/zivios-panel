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

class ZiviosConfiguration
{
    protected $_regexLib, $_config = null;
    public    $zvConfig;

    public function __construct()
    {}

    protected function setLogForm($data)
    {
        if (!isset($data['productionmode'])) {
            $data['productionmode'] = 0;
        }

        $this->loadConfiguration(true);
        $this->_config->log->productionmode = $data['productionmode'];
        $this->_config->log->loglevel = $data['loglevel'];
        $this->_config->log->childrenof->Zivios_Ldap_Engine = $data['logldapengine'];
        $this->_config->log->childrenof->Zivios_Plugin = $data['logplugin'];
        $this->_config->log->childrenof->Zivios_Controller = $data['logcontroller'];
        $this->_config->log->EMSSecurityObject = $data['logsecurity'];
        $this->_config->log->Zivios_Parameter = $data['logparameters'];

        if ($this->writeConfig()) {
            return true;
        } else {
            return false;
        }
    }

    protected function setDatabaseForm($data)
    {
        Zivios_Log::debug($data);
        // Confirm password.
        if ($data['dbpass'] != $data['cdbpass']) {
            throw new Zivios_Error('Specified passwords for DB user do not match.');
        }

        $this->loadConfiguration(true);

        // Ensure passwords are encrypted.
        $epass = Zivios_Security::encrypt($data['dbpass']);
        $this->_config->database->host     = $data['dbhost'];
        $this->_config->database->name     = $data['dbname'];
        $this->_config->database->username = $data['dbuser'];
        $this->_config->database->password = $data['dbpass'];

        if ($this->writeConfig()) {
            return true;
        } else {
            return false;
        }
    }


    protected function setSecurityForm($data)
    {
        Zivios_Log::debug($data);
        $this->loadConfiguration(true);
        $this->_config->security->inactivity_timeout = $data['sesstimeout'];

        if ($this->writeConfig()) {
            return true;
        } else {
            return false;
        }
    }

    protected function setLdapForm($data)
    {
        Zivios_Log::debug($data);
        
        $this->loadConfiguration(true);
        $this->_config->ldap->host = $data['ldaphost'];
        $this->_config->ldap->port = $data['ldapport'];
        $this->_config->ldap->ldap_uid_min = $data['uidmin'];
        $this->_config->ldap->ldap_uid_max = $data['uidmax'];
        $this->_config->ldap->ldap_gid_min = $data['gidmin'];
        $this->_config->ldap->ldap_gid_max = $data['gidmax'];
        $this->_config->ldap->sizelimit = $data['sizelimit'];
        $this->_config->ldap->timelimit = $data['timelimit'];
        
        if ($this->writeConfig()) {
            return true;
        } else {
            return false;
        }
    }

    protected function setGeneralForm($data)
    {
        if ($data['dojodebug'] == '0') {
            $dojoDebug = false;
        } else {
            $dojoDebug = true;
        }

        if (!isset($data['usedojobuild'])) {
            $data['usedojobuild'] = 'no';
        }

        // Load configuration in write mode.
        $this->loadConfiguration(true);
        
        // Update configuration data.
        $this->_config->general->appname = $data['appname'];
        $this->_config->general->appnameshort = $data['appnameshort'];
        $this->_config->cache->host = $data['memcachehost'];
        $this->_config->cache->port = $data['memcacheport'];
        $this->_config->cache->expiretime = $data['memcacheexpire'];
        $this->_config->view->dojo->isDebug = $dojoDebug;
        $this->_config->view->dojo->useBuild = $data['usedojobuild'];

        // Check if the base theme is being updated; if so, rewrite
        // the active.css file accordingly.
        if ($this->_config->general->ziviosTheme != $data['ziviostheme']) {
            if (!$this->updateTheme($data['ziviostheme'])) {
                return false;
            }
        }

        $this->_config->general->ziviosTheme = $data['ziviostheme'];
        
        if ($this->writeConfig()) {
            return true;
        } else {
            return false;
        }
    }

    protected function updateTheme($newTheme)
    {
        $newTheme = '@import "../../dijit/themes/' . $newTheme . '/'. $newTheme 
            . '.css";' . "\n";
        $fileLocation = $_SERVER['DOCUMENT_ROOT'] . '/' . WEB_ROOT . 
            '/public/scripts/devel/current/zivios/base/active.css';

        if (!$fp = fopen($fileLocation, 'w')) {
            Zivios_Log::error('Could not open active.css for writing on change theme request.');
            return false;
        }

        fwrite($fp, $newTheme);
        fclose($fp);
        return true;
    }

    protected function backupConfig()
    {
        $backupFile = APPLICATION_PATH . '/config/app.config.ini.bak';
        $currentConfig = APPLICATION_PATH . '/config/app.config.ini';

        // Remove existing backup if found.
        if (file_exists($backupFile)) {
            if (is_writable($backupFile)) {
                unlink($backupFile);
            } else {
                Zivios_Log::error('Backup file found but not writable by Zivios. Check permissions.');
                return false;
            }
        } else {
            Zivios_Log::info('No backup file found. Writing backup file.');
        }
        
        if (copy($currentConfig, $backupFile)) {
            Zivios_Log::info('Configuration file backed up successfully.');
            return true;
        } else {
            Zivios_Log::error('Could not backup configuration file. Check permissions.');
            return false;
        }
    }

    protected function writeConfig()
    {
        // Backup existing configuration.
        if (!$this->backupConfig()) {
            throw new Zivios_Exception('Could not backup Zivios configuration. Please ensure file'.
                ' permissions and ownership is correct.');
        }

        try {
            $writer = new Zend_Config_Writer_Ini(array('config'   => $this->_config,
                'filename' => APPLICATION_PATH . '/config/app.config.ini'));
            $writer->write();
            return true;
        } catch (Exception $e) {
            Zivios_Log::error($e->getMessage());
            return false;
        }
    }

    public function updateConfig($configData, $section)
    {
        if (!is_array($configData) || empty($configData)) {
            throw new Zivios_Exception('updateConfig requires non-empty Array.');
        }
        
        $updateCall = 'set' . ucfirst($section) . 'Form';

        if (call_user_func(array(&$this, $updateCall), $configData)) {
            return true;
        } else {
            return false;
        }
    }

    protected function loadConfiguration($write=false)
    {
        if (!$write) {
            $this->_config = new Zend_Config_Ini(APPLICATION_PATH . '/config/app.config.ini');
            return $this->_config;
        } else {
            $this->_config = new Zend_Config_Ini(APPLICATION_PATH . '/config/app.config.ini',
                null, array('skipExtends' => true, 'allowModifications' => true));
            return $this->_config;
        }
    }

    public function getZvConfig()
    {
        $this->zvConfig = $this->loadConfiguration();
        return $this->zvConfig;
    }
}

