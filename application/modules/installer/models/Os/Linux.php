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

class Os_Linux
{
    public $linuxConfig, $supportedDistros, $sysDistro, $sysRelease, $sysCodename;
    protected $_distroInfo, $_distroClass, $_session;

    public function __construct()
    {
        // We will be registering various system level details in the installer session. 
        // The information is required later by the installer during final Zivios configuration.
        $this->_session = Zend_Registry::get("installSession");
        
        // Ensure no command paths are registered in session. If none
        // are found, register required commands.
        if (null === $this->_session->_cmds) {
            $this->_session->_cmds = array('lsb_release' => '','sudo' => '','chmod' => '',
                                           'chown' => '','ln' => '','mkdir' => '',
                                           'rm'=>'','hostname' => '', 'uname' => '',
                                           'cat' => '','cp' => '', 'mv' => '', 
                                           'kill' => '', 'pidof' => '', 'c_rehash' => '');
        }

        // Get all compatible Linux distributions and releases.
        $this->linuxConfig = new Zend_Config_Ini(APPLICATION_PATH . 
            '/config/installer.config.ini', "linux");
        $this->supportedDistros = explode(',', $this->linuxConfig->distroSupport);
    }

    public function runSysTests()
    {
        // Ensure all required programs are in place.
        foreach ($this->_session->_cmds as $cmdname => $path) {

            // Set the path (or return the error code) accordingly.
            $this->_session->_cmds[$cmdname] = $this->_setCommandPath($cmdname);

            if ($this->_session->_cmds[$cmdname] == '') {
                Zivios_Log::error('Required program: ' . $cmdname . ' not found.', 'clogger');
                throw new Zivios_Exception('Required program: ' . $cmdname . ' not found.');
            } else {
                Zivios_Log::debug('Required program: ' . $cmdname . ' found: ' .
                    $this->_session->_cmds[$cmdname]);
            }
        }

        // Check distribution and release. Ensure both are compatible.
        $cmd = $this->_session->_cmds['lsb_release'] . " -s -i";
        $this->sysDistro = strtolower(trim(`$cmd`));
        
        $cmd = $this->_session->_cmds['lsb_release'] . " -s -r";
        $this->sysRelease = strtolower(trim(`$cmd`));

        $cmd = $this->_session->_cmds['lsb_release'] . " -s -c";
        $this->sysCodename = strtolower(trim(`$cmd`));

        Zivios_Log::info('Probing Linux system.', 'clogger');
        Zivios_Log::info('Distribution: ' . ucfirst(strtolower($this->sysDistro)), 'clogger');
        Zivios_Log::info('Release: '      . $this->sysRelease, 'clogger');
        Zivios_Log::info('Codename: '     . ucfirst(strtolower($this->sysCodename)), 'clogger');
        
        // Ensure the distribution and release are compatible with Zivios.
        if (!in_array($this->sysDistro, $this->supportedDistros)) {
            Zivios_Log::error('Distribution incompatible with Zivios.', 'clogger');
            $distroSupported  = false;
            $releaseSupported = false;
        } else {
            Zivios_Log::info('Distribution compatible with Zivios.', 'clogger');
        
            // lsb_release reports SLES as "SuSE Linux". Truncate this to simply 'suse'
            if ($this->sysDistro == 'suse linux') {
                $this->sysDistro   = 'suse';
                $this->sysCodename = 'sles' . $this->sysRelease;
            }

            $distroSupported = true;
            $distroInfo = $this->_getDistroDetails();
            $supportedReleases = explode(',', $distroInfo->releaseSupport);


            if (!in_array(ucfirst(strtolower($this->sysCodename)), $supportedReleases)) {
                Zivios_Log::error('Release incompatible with Zivios.', 'clogger');
                $releaseSupported = false;
            } else {
                Zivios_Log::info('Release compatible with Zivios.', 'clogger');
                $releaseSupported = true;
            }
        }

        // Register distribution level details in session. 
        $this->_session->osDetails = array(
            'os'               => 'Linux',
            'distro'           => ucfirst($this->sysDistro),
            'release'          => ucfirst($this->sysRelease),
            'codename'         => ucfirst($this->sysCodename),
            'distroSupported'  => $distroSupported,
            'releaseSupported' => $releaseSupported);
       
        if ($distroSupported) {
            $distroConfig = $this->_getDistroDetails();

            // Create a backup folder to ensure a clean restore of the system is possible.
            $this->_createFolder($this->linuxConfig->tmpFolder, "0700", 
                $distroConfig->webuser, $distroConfig->webgroup);

            // Additionally, create a temp folder for the installer to work in.
            $this->_createBackupFolder();

            // Instantiate the required distribution class to begin probing 
            // package level details and system configuration details.
            $distroClass = $this->getDistroClass();
            $distroClass->runSystemTests();
        }

        // Get all relevant local system details.
        $this->_localSystemProbe();
    }

    public function getDistroClass()
    {
        if (null === $this->_distroClass) {

            if ($this->_session->osDetails['distroSupported'] === false) {
                Zivios_Log::debug('Initializing Unsupported Distribution Class');
                require_once dirname(__FILE__) . '/Linux/Unsupported.php';
                $distroClass = 'Os_Linux_Unsupported';
                $this->_distroClass = new $distroClass();
            } else {
                require_once dirname(__FILE__) . '/Linux/' . $this->_session->osDetails['distro'] . '.php';
                $distroClass = 'Os_Linux_' . ucfirst(strtolower($this->_session->osDetails['distro']));
                $this->_distroClass = new $distroClass();
            }
        }

        return $this->_distroClass;
    }

    /**
     * @return void
     */
    public function runPhpTests()
    {
        /*
        $phpCoreRequiredExtensions = array('xmlrpc','pdo_mysql','mysql','mysqli','mcrypt','imap',
            'curl','openssl','pcre','json','libxml','ldap','memcache','ssh2');
        */
        $phpCoreRequiredExtensions = array('memcache','ssh2');

        $phpExtensions = get_loaded_extensions();
        $phpVersion = phpversion();

        if (version_compare(phpversion(), '5.1', '<')) {
            throw new Zivios_Error("Php Version 5.2 or greater required.");
        }

        foreach ($phpCoreRequiredExtensions as $extension) {
            if (!in_array($extension, $phpExtensions)) {
                throw new Zivios_Error("Could not find PHP extension: " . $extension . 
                    ". If you have installed missing php packages during the System " .
                    " Probe test, please restart the web server.");
            }
        }

        $phpDisplayErrors     = ini_get('display_errors');
        $phpLogErrors         = ini_get('log_errors');
        $phpExecTime          = ini_get('max_execution_time');
        $phpMemoryLimit       = ini_get('memory_limit');

        if (strtolower($phpDisplayErrors) == 1) {
            throw new Zivios_Error("Php configuration error: display errors should be off.");
        }

        if (strtolower($phpLogErrors) == 0) {
            throw new Zivios_Error("Php configuration error: error logging needs to be on.");
        }

        if (strtolower($phpExecTime) < 300) {
            throw new Zivios_Error("Php configuration error: script execution time should be at"
                . " least 300 seconds.");
        }

        if (strtolower($phpMemoryLimit) < 64) {
            throw new Zivios_Error("Php configuration error: memory limit should be at least 64 MB.");
        }
    }

    /**
     * Simple check to see if required apache modules are included.
     *
     * @return void
     */
    public function runApacheTests()
    {
        $requiredApacheMods = array('mod_ssl','mod_rewrite');
        $apacheEnabledMods = apache_get_modules();
        
        foreach ($requiredApacheMods as $module) {
            if (!in_array($module, $apacheEnabledMods)) {
                Zivios_Log::error('Required apache module ' . $module . ' not loaded.','clogger');
                throw new Zivios_Error("Required apache module not found: " . $module);
            }
        }
    }
    
    /**
     * Get the distribution configuration (same as distroSetup).
     *
     * @return Zend_Config_Ini $config
     */
    public function getDistroConfig()
    {
        $distro = strtolower($this->_session->osDetails['distro']);

        if (null === $this->_distroInfo) {
            $this->_distroInfo = new Zend_Config_Ini(APPLICATION_PATH .
                '/config/installer.config.ini', $distro);
        }

        return $this->_distroInfo;
    }

    /**
     * @return void
     */
    protected function _localSystemProbe()
    {
        $this->_session->localSysInfo = array();
        $this->_session->localSysInfo['hostname']  = $this->_getHostname();
        
        /**
         * Establish the hostname for krb5realm, CA, Bind's primary host
         * and the apache virtual host. We should ensure that the apache
         * virtual host is the primary hostname for the system in question
         * as well. 
         */
        $domainParts = explode('.', $this->_session->localSysInfo['hostname']);
        if (count($domainParts) == 2) {
            $this->_session->localSysInfo['krb5realm'] = strtoupper($this->_session->localSysInfo['hostname']);
            $this->_session->localSysInfo['bindzone']  = strtolower($this->_session->localSysInfo['hostname']);
            $this->_session->localSysInfo['addzone']   = '';
        } elseif (count($domainParts) == 3) {
            $krb5realm = $domainParts[count($domainParts)-2] .'.'.$domainParts[count($domainParts)-1];
            $this->_session->localSysInfo['krb5realm'] = strtoupper($krb5realm);
            $this->_session->localSysInfo['bindzone']  = strtolower($krb5realm);
            $this->_session->localSysInfo['addzone']   = strtolower($domainParts[0]);
        } else {
            // Domain is possibly in the format foo.bar.something.more.com.
            $krb5realm = '';
            for ($c = 0; $c < count($domainParts); $c++) {
                if ($c > 0 && $domainParts[$c] != '') {
                    $krb5realm .= $domainParts[$c] . '.';
                } else {
                    $localzone = $domainParts[0];
                }
            }
            
            // Remove leading dot.
            $krb5realm = rtrim($krb5realm, '.');
            
            // Register data in system session array
            $this->_session->localSysInfo['krb5realm'] = strtoupper($krb5realm);
            $this->_session->localSysInfo['bindzone']  = strtolower($krb5realm);
            $this->_session->localSysInfo['addzone']   = strtolower($localzone);
        }
        
        // Calculate Base DN based on domain.
        $basedn = $this->_makeDnFromDomain($this->_session->localSysInfo['bindzone']);

        // Continue setup of additional required information.
        $this->_session->localSysInfo['kdchost']    = 'kdc.' . $this->_session->localSysInfo['bindzone'];
        $this->_session->localSysInfo['basedn']     = $basedn;
        $this->_session->localSysInfo['ip']         = $_SERVER['SERVER_ADDR'];
        $this->_session->localSysInfo['memory']     = $this->_getSystemMemory();
        $this->_session->localSysInfo['arch']       = $this->_getSystemArch();
        $this->_session->localSysInfo['storage']    = $this->_getSystemStorage();
        $this->_session->localSysInfo['cpudetails'] = $this->_getCpuDetails();
    }

    protected function _getCpuDetails()
    {
        $cmds = array();
        $cmds['cpumodel'] = $this->_session->_cmds['cat'] . " /proc/cpuinfo | grep 'model name' | awk -F\: '{print $2}'| uniq | sed -e 's/ //'";
        $cmds['cpumhz']   = $this->_session->_cmds['cat'] . " /proc/cpuinfo | grep 'cpu MHz' | awk -F\: '{print $2}'| uniq | sed -e 's/ //'";
        $cmds['cpucount'] = $this->_session->_cmds['cat'] . " /proc/cpuinfo | grep ^processor | wc -l";
                    
        $cpuDetails = array();
        foreach ($cmds as $key => $cmd) {
            $cdata = $this->_runLinuxCmd($cmd);

            if ($cdata['exitcode'] != 0) {
                throw new Zivios_Error('Error probing CPU details. Please check Zivios logs.');
            } else {
                // replace all extra space formatting with single spaces.
                $cdclean = ereg_replace("[ \t]+", " ", $cdata['output'][0]);
                $cpuDetails[$key] = $cdclean;
            }
        }

        return $cpuDetails;
    }

    protected function _getDistroDetails()
    {
        if (null === $this->_distroInfo) {
            $this->_distroInfo = new Zend_Config_Ini(APPLICATION_PATH .
                '/config/installer.config.ini', $this->sysDistro);
        }

        return $this->_distroInfo;
    }
    
    protected function _setCommandPath($program,$sudo=false)
    {
        if ($sudo) {
            $injectSudo = $sudo . ' ';
        } else {
            $injectSudo = '';
        }

        // Assuming 'which' is in the web server path...
        $commpath = shell_exec($injectSudo . 'which ' . escapeshellarg($program));

        if (trim($commpath) == '') {
            return '';
        } else {
            return trim($commpath);
        }
    }

    /**
     * Check the system memory and set in session.
     *
     * @return array $memDetails
     */
    protected function _getSystemMemory()
    {
        $cmd_1 = $this->_session->_cmds['cat'] . " /proc/meminfo | grep MemTotal | awk '{print $2}'";
        $cmd_2 = $this->_session->_cmds['cat'] . " /proc/meminfo | grep MemFree | awk '{print $2}'";
        $cmd_3 = $this->_session->_cmds['cat'] . " /proc/meminfo | grep SwapTotal | awk '{print $2}'";
        $cmd_4 = $this->_session->_cmds['cat'] . " /proc/meminfo | grep SwapFree | awk '{print $2}'";

        $memDetails = array();

        $totalMem = $this->_runLinuxCmd($cmd_1);
        if ($totalMem['exitcode'] != 0) {
            Zivios_Log::error("_CONSOLEDATA_Total memory check failed.");
            throw new Zivios_Exception("Could not check system memory.");
        } else {
            $memDetails['totalMemory'] = $this->_formatSize(trim($totalMem['output'][0]));
        }

        $freeMem = $this->_runLinuxCmd($cmd_2);
        if ($freeMem['exitcode'] != 0) {
            Zivios_Log::error("_CONSOLEDATA_Free memory check failed.");
            throw new Zivios_Exception("Could not check free memory.");
        } else {
            $memDetails['freeMemory'] = $this->_formatSize(trim($freeMem['output'][0]));
        }

        $totalSwap = $this->_runLinuxCmd($cmd_3);
        if ($totalSwap['exitcode'] != 0) {
            Zivios_Log::error("_CONSOLEDATA_Swap memory check failed.");
            throw new Zivios_Exception("Could not check total swap memory size.");
        } else {
            $memDetails['totalSwap'] = $this->_formatSize(trim($totalSwap['output'][0]));
        }

        $freeSwap = $this->_runLinuxCmd($cmd_4);
        if ($freeSwap['exitcode'] != 0) {
            Zivios_Log::error("_CONSOLEDATA_Free swap memory check failed.");
            throw new Zivios_Exception("Could not check available swap memory.");
        } else {
            $memDetails['freeSwap'] = $this->_formatSize(trim($freeSwap['output'][0]));
        }

        return $memDetails;
    }

    /**
     * Check system architecture and set in session.
     * 
     * @return string
     */
    protected function _getSystemArch()
    {
        $cmd = $this->_session->_cmds['uname'] . ' -m';
        $arch = $this->_runLinuxCmd($cmd);
        
        if ($arch['exitcode'] != 0) {
            Zivios_Log::error('Could not check system architecture.', 'clogger');
            throw new Zivios_Exception('Could not check system architecture.');
        } else {
            return trim($arch['output'][0]);
        }
    }

    /**
     * Check system storage and set in session.
     */
    protected function _getSystemStorage()
    {}
    
    /**
     * Check the system hostname and set in session.
     * @return string
     */
    protected function _getHostname()
    {
        $cmd = $this->_session->_cmds['hostname'];
        $hostnameData = $this->_runLinuxCmd($cmd);

        if ($hostnameData['exitcode'] != 0) {
            throw new Zivios_Exception("System hostname set incorrectly.");
        } else {
            Zivios_Log::debug('Hostname: ' . $hostnameData['output'][0]);
        }
        
        // Validate the hostname. Local DNS names are permitted, 
        // as are fqdns and idns.
        $validator = new Zend_Validate_Hostname(Zend_Validate_Hostname::ALLOW_DNS |
            Zend_Validate_Hostname::ALLOW_LOCAL);

        if (!$validator->isValid($hostnameData['output'][0])) {
            Zivios_Log::error("Invalid hostname on system.");

            foreach ($validator->getMessages() as $message) {
                Zivios_Log::error($message);
            }
            
            throw new Zivios_Exception("Invalid hostname set on system.");
        }

        /**
         * Furthermore, we do not allow hostnames like "localhost" or "somehost".
         * The full hostname (which Zivios requests) *MUST* be of the format: 
         * localhost.localdomain, or; foo.bar.com (or foo.bar.more.com), etc.
         */
        $hostnameParts = explode (".", $hostnameData['output'][0]);
        $cHostnameParts = count($hostnameParts);
        $hostnameInfo = array();

        if ($cHostnameParts < 2) {
            throw new Zivios_Error("Invalid hostname set on system.");
        }
        
        return $hostnameData['output'][0];
    }

    /**
     * Formats a given number in b,kb,mb, etc.
     * 
     * @return string
     */
    protected function _formatSize($size)
    {
        $sizeIn = array("kb", "mb", "gb", "tb", "pb", "eb", "zb", "yb");
        
        $i=0;
        while (($size / 1024) > 1) {
            $size = $size / 1024;
            $i++;
        }

        return round(substr($size,0,strpos($size,'.')+4)).' '. $sizeIn[$i];
    }

    protected function _runLinuxCmd($cmd,$sudo=false)
    {
        if ($sudo) {
            $cmd = $this->_session->_cmds['sudo'] . ' ' . $cmd;
        } 

        Zivios_Log::debug("Running command: " . $cmd);

        // Execute command and return all relevant output.
        $output = array();
        $return_var = 0;
        $out = exec($cmd, $output, $return_var);
        return array('output' => $output, 'exitcode' => $return_var);
    }

    /**
     * Remove a system folder as well as all subfolders found.
     *
     * @params string $folder
     * @return void
     */
    protected function _removeRecursive($folder)
    {
        if (is_dir($folder)) {
            $cmd = $this->_session->_cmds['rm'] . ' -rf ' . escapeshellcmd($folder);
            Zivios_Log::debug($cmd);
            $rc = $this->_runLinuxCmd($cmd,true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Error removing folder: ' . $folder);
            }
        } else {
            throw new Zivios_Exception('Remove recursive failed. Folder not found: ' . $folder);
        }
    }

    /**
     * Create a system backup folder if one does not exist.
     * 
     * @return void
     */
    protected function _createBackupFolder()
    {
        if (!is_dir($this->linuxConfig->backupFolder)) {
            // Create required backup folder.
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $this->linuxConfig->backupFolder;
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not create backup folder: " . 
                    $this->linuxConfig->backupFolder);
            }
        }
    }

    /**
     * Creates a system folder using SUDO. If any command in the chain fails, 
     * an exception is raised by the system.
     * 
     * @return void
     */
    protected function _createFolder($folder, $perms, $owner, $group)
    {
        $cmds   = array();
        $cmds[] = $this->_session->_cmds['mkdir'] . ' -p ' . $folder;
        $cmds[] = $this->_session->_cmds['chmod'] . ' ' . $perms . ' ' . $folder;
        $cmds[] = $this->_session->_cmds['chown'] . ' ' . $owner . ':' . $group . ' ' . $folder;

        foreach ($cmds as $cmd) {
            $rc = $this->_runLinuxCmd($cmd, true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Folder creation failed. Command: " . $cmd);
            }
        }
    }

    /**
     * Copies a given file to another location and sets permissions and ownership
     * as specified.
     *
     * @return void
     */
    protected function _copyFile($src,$destination,$perms,$owner,$group)
    {
        $cmds   = array();
        $cmds[] = $this->_session->_cmds['cp']    . ' ' . $src   . ' ' . $destination;
        $cmds[] = $this->_session->_cmds['chmod'] . ' ' . $perms . ' ' . $destination;
        $cmds[] = $this->_session->_cmds['chown'] . ' ' . $owner . ':' . $group . ' ' . $destination;

        foreach ($cmds as $cmd) {
            $rc = $this->_runLinuxCmd($cmd,true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Error during copy operation. Failed command: " . $cmd);
            }
        }
    }

    /**
     * Set ownership on a given directory or file. Command runs with SUDO privileges to root.
     *
     * @return void
     */
    protected function _setOwnership($destination, $owner, $group, $recursive=false)
    {
        $recurs = '';
        if ($recursive == true) {
            $recurs = ' -R';
        }

        $cmds   = array();
        $cmds[] = $this->_session->_cmds['chown'] . $recurs . ' ' . $owner . ':' . $group . ' ' . 
                  $destination;

        foreach ($cmds as $cmd) {
            $rc = $this->_runLinuxCmd($cmd, true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception('Error during setOwnership call. Failed command: ' . $cmd);
            }
        }
    }    

    /**
     * Moves a file using sudo to a new location, changing the ownership and permissions
     * as requested. 
     *
     * @return void
     */
    protected function _moveFile($olocation, $nlocation, $perms, $owner, $group)
    {
        $cmds   = array();
        $cmds[] = $this->_session->_cmds['mv']    . ' ' . $olocation . ' ' . $nlocation;
        $cmds[] = $this->_session->_cmds['chmod'] . ' ' . $perms     . ' ' . $nlocation;
        $cmds[] = $this->_session->_cmds['chown'] . ' ' . $owner     . ':' . $group . ' ' . $nlocation;

        foreach ($cmds as $cmd) {
            $rc = $this->_runLinuxCmd($cmd,true);
            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Error moving file. Command: " . $cmd);
            }
        }
    }

    /**
     * Remove a file (uses sudo by default)
     * @return void
     */
    protected function _removeFile($file)
    {
        $cmd = $this->_session->_cmds['rm'] . ' ' . $file;
        Zivios_Log::debug('Removing file: ' . $file);
        $rc = $this->_runLinuxCmd($cmd,true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception('Error removing file ('.$file.'). Check Logs');
        }
    }

    protected function _softLink($file,$target)
    {
        $cmd  = $this->_session->_cmds['ln'] . ' -s ' . $file    . ' ' . $target;
        Zivios_Log::debug('Soft Linking '.$file.' to target :'.$target);
        $rc = $this->_runLinuxCmd($cmd,true);
        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception("Error Linking File Command: " . $cmd);
        }
    }
    
    /**
     * Simply break the passed domain into domain components
     */
    protected function _makeDnFromDomain($domain)
    {
        $dcs = explode(".", $domain);
        $basedn = '';
        foreach ($dcs as $dc) {
            $basedn .= 'dc='.$dc.',';
        }

        return rtrim($basedn,',');
    }
}

