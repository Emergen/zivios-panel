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
 * @package     mod_installer
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Installer
{
    protected $_session, $_config, $_form, $_os, $_consoleData, $_dbx, $_krb5;

    public function __construct()
    {
        $this->_session = Zend_Registry::get("installSession");
        $this->_config  = Zend_Registry::get("appConfig");
    }

    public function checkInstallSession()
    {
        if (isset($this->_session->initialized) && $this->_session->initialized == 1) {
            // ensure that 1_start.stamp exists -- this is written on initialization.
            if (!file_exists(APPLICATION_PATH . '/status/1_start.stamp')) {
                $filename = '1_start.stamp';
                $message = "Installation initialized. Session Locked.\n";
                $this->_writeStampFile($filename, $message);
            }
            return 1;
        } else {
            // Initialize the session if no status files are found.
            $d = dir(APPLICATION_PATH . "/status");
            $installProgress = array();

            while (false !== ($entry = $d->read())) {
                if (substr($entry, -6) == '.stamp')
                    $installProgress[] = APPLICATION_PATH . '/status/' . $entry;
            }

            $d->close();

            if (empty($installProgress)) {
                Zivios_Log::info("Instantiating new installation session data.");
                // Lock installation for this session.
                $filename = '1_start.stamp';
                $message  = "Installation initialized. Session Locked.\n";
                $this->_writeStampFile($filename, $message);
                $this->_session->initialized = 1;
                return 1;
            }
        }
    }

    /**
     * Calculates the next step in the installation process and returns
     * relevant information (page code & file contents) to caller as an associative
     * array.
     *
     * @return array $stepDetails
     */
    public function getNextStep()
    {
        $stepDetails = array();
        $d = dir($this->_config->status);
        while (false !== ($entry = $d->read())) {
            if ($entry == '.' || $entry == '..')
                continue;

            if (substr($entry, -6) == '.stamp') {
                // Populate array details for relevant step with step
                // history on file.
                $stepDetails[substr($entry, 0, 1)] = file_get_contents($this->_config->status.'/'.$entry, 4096);
            }
        }

        // Close dir handler.
        $d->close();
        
        // Sort the array by keys, calculate next step & return data to caller.
        ksort($stepDetails);
        $keys                        = array_keys($stepDetails);
        $stepCount                   = sizeof ($keys);
        $stepDetails['nextAction']   = $this->_getNextStep($stepCount+1);
        $stepDetails['nextActionId'] = $stepCount + 1;
        return $stepDetails;
    }

    /**
     * Function removes install stamp files and destroys all session data.
     *
     * @return boolean
     * @todo: perform restore of original OS data.
     */
    public function restartInstallation()
    {
        // Remove all installation stamp files and destroy install session.
        $d = dir($this->_config->status);
        while (false !== ($entry = $d->read())) {
            if ($entry == '.' || $entry == '..')
                continue;

            if (substr($entry, -6) == '.stamp') {
                if (!unlink($this->_config->status . '/' . $entry)) {
                    throw new Zivios_Exception('Could not remove install stamp file. Please consult' .
                        ' the install and error log for further details');
                }
            }
        } 

        // Close dir handler.
        $d->close();

        // Destroy session.
        Zend_Session::destroy();
        return true;
    }

    /**
     * Hardcoded linking of steps to ids.
     * 
     * @return string || false;
     */
    protected function _getNextStep($step)
    {
        $stepsMap     = array();
        $stepsMap[3]  = 'initializedb';
        $stepsMap[4]  = 'initializeca';
        $stepsMap[5]  = 'initializewebssl';
        $stepsMap[6]  = 'restartapache';
        $stepsMap[7]  = 'initializeldap';
        $stepsMap[8]  = 'initializekrb5';
        $stepsMap[9]  = 'initializebind';
        $stepsMap[10] = 'initializezv';

        if (array_key_exists($step, $stepsMap)) {
            return $stepsMap[$step];
        } else {
            return false;
        }
    }

    /**
     * Performs a series of tests to ensure the local system can run Zivios
     * services. Step ID: 2.
     *  
     * @return array $report
     */
    public function runLocalSystemTests()
    {
        Zivios_Log::debug('Running local system tests');
        Zivios_Log::debug('Supported operating system(s): ' . $this->_config->osSupport);
        $localOs = strtolower(PHP_OS);
        Zivios_Log::debug('Local OS: ' . $localOs);

        // If the OS is supported, we initialize the OS model which
        // takes over further testing.
        $supportedOs = explode(',', $this->_config->osSupport);
        if (!in_array($localOs, $supportedOs)) {
            Zivios_Log::error('Operating system not supported by Zivios.');
            throw new Zivios_Error('Operating system not supported by Zivios.');
        } else {
            Zivios_Log::info("Compatible OS found: " . $localOs, 'clogger');
            Zivios_Log::debug("Compatible operating system found.");
        }

        // Initialize the operating system model and run local OS tests.
        $os = $this->getOs(ucfirst(strtolower($localOs)));
        $os->runSysTests();

        // Run PHP and Apache Tests.
        $os->runPhpTests();

        // Check web server software and run tests as required.
        if (strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'apache') !== false) {
            $os->runApacheTests();
        } else {
            Zivios_Log::error('Unsupported web server. Currently support exists ' . 
                ' only for Apache.', 'clogger');
            throw new Zivios_Exception("Unsupported web server.");
        }
        
        // Generate report
        $report = $this->_generateSystemTestReport();
        
        if ($this->_session->osDetails['distroSupported'] == true &&
            $this->_session->osDetails['releaseSupported'] == true) {
            //  Write progress stamp file.
            $message = "All system tests (PHP, Apache and OS level) successful.\n";
            $this->_writeStampFile('2_probe.stamp', $message);

            // Return system report.
        }

        return $report;
    }
    
    public function runDbSetup($formData)
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $distroDetails = $os->getDistroConfig();

        // Register required db data to session.
        $formData['socket'] = $distroDetails->mysqlsocket;
        $this->_session->dbInfo = $formData;

        $dbi = $this->_getDbInstance($formData);
        $dbi->iniSetup();

        $message = 'Database setup successful. Database Backend: ' . $formData["dbtype"] . "\n";
        $this->_writeStampFile('3_dbsetup.stamp', $message);
    }

    public function runCaSetup($formData)
    {
        // Get the OS instance and run CA setup specific to the distribution
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniCaSetup($formData);

        $message = "CA Setup successful.\n";
        $this->_writeStampFile('4_casetup.stamp',$message);
    }

    public function initializeWebssl()
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniWebssl();
        
        $message = "Web SSL activated successfully.\n";
        $this->_writeStampFile('5_webssl.stamp', $message);
    }

    public function sslInitialized()
    {
        $message = "System switch to SSL mode confirmed";
        $this->_writeStampFile('6_sslactive.stamp', $message);
    }

    public function runLdapSetup($formData)
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniLdapSetup($formData);

        $message = "OpenLDAP data initialized, Service started.\n";
        $this->_writeStampFile('7_ldapsetup.stamp', $message);
    }

    public function runKerberosSetup($formData)
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniKrb5Setup($formData);

        $message = "Kerberos data initialized, Service started.\n";
        $this->_writeStampFile('8_krb5setup.stamp', $message);
    }

    public function runBindSetup($formData)
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniBindSetup($formData);

        $message = "Bind service configuration complete. Service started.\n";
        $this->_writeStampFile('9_bind5setup.stamp', $message);
    }

    public function runZiviosSetup($formData)
    {
        $os = $this->getOs($this->_session->osDetails['os']);
        $os->getDistroClass()->iniZiviosSetup($formData);

        $message = "Zivios agent and panel configuration complete. Agent service started.\n";
        $this->_writeStampFile('10_zvsetup.stamp', $message);
    }

    public function writeOptimalStamp()
    {
        $message = "Installation complete.\n";
        $this->_writeStampFile('optimal.install.stamp', $message);
    }

    public function getOs($os)
    {
        if (null === $this->_os) {
            require_once dirname(__FILE__) . '/Os/'.$os.'.php';
            $os = 'Os_' . $os;
            $this->_os = new $os;
        }
        return $this->_os;
    }

    public function getOsDetails()
    {
        $os = $this->_session->osDetails;
        return $os;
    }

    public function getForm($form)
    {
        if (null === $this->_form) {
            require_once dirname(__FILE__) . '/Form/'.$form.'.php';
            $form = 'Form_' . $form;
            $this->_form = new $form;
        }
        return $this->_form;
    }

    protected function _getTimestamp()
    {
        return date(DATE_RFC822);
    }

    /**
     * Writes a file with provided message to the application/status
     * folder.
     *
     * @return void
     */
    protected function _writeStampFile($filename, $message)
    {
        $fullPath = $this->_config->status . "/" . $filename;
        $timeStamp = $this->_getTimestamp();
        $message = $timeStamp . ": " . $message;

        Zivios_Log::debug("Creating file: " . $fullPath);

        if ($fp = fopen($fullPath, "w")) {
            Zivios_Log::debug("Adding message: " . $message);
            if (!fwrite($fp, $message)) {
                fclose($fp);
                throw new Zivios_Exception("Could not write message to file: " . $fullPath);
            } else {
                Zivios_Log::debug("file write successful. Closing file handler.");
            }

            fclose($fp);
        } else {
            throw new Zivios_Exception("Could not open file " . $fullPath . " for writing");
        }
    }

    /**
     * Returns formatted output of the last 15 lines.
     */
    public function getConsoleData()
    {
        $cData = $this->getConsoleClass();
        return $cData->getLastLog();
    }

    protected function getConsoleClass()
    {
        if (null === $this->_consoleData) {
            require_once dirname(__FILE__) . '/Console/Data.php';
            $this->_consoleData = new Console_Data();
        }

        return $this->_consoleData;
    }

    protected function _generateSystemTestReport()
    {
        Zivios_Log::info("Generating system probe report.");
        $report = array();
        $report['osDetails']     = $this->_session->osDetails;
        $report['localSysInfo']  = $this->_session->localSysInfo;

        return $report;
    }

    /**
     * Get Database instance.
     *
     * @return resource $this->_dbx
     */
    protected function _getDbInstance($dbinfo)
    {
        if (null === $this->_dbx) {
            $dbtype = ucfirst(strtolower($dbinfo['dbtype']));
            require_once dirname(__FILE__) . '/Db/' . $dbtype . '.php';
            $dbclass = 'Db_' . $dbtype;
            $this->_dbx = new $dbclass($dbinfo);
        }
        return $this->_dbx;
    }

    /**
     * Read information from installer configuration and return
     * data to caller
     * 
     * @return array $info
     */
    public function getZiviosInfo()
    {
        $info = array();
        $info['version'] = $this->_config->version;
        $info['appname'] = $this->_config->appname;
        $info['supportedOsTags'] = explode(',',$this->_config->supportedOsTags);
        return $info;
    }
}

