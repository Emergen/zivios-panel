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
 * @package     mod_default
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSLinuxComputer extends EMSComputer
{
    protected $_sshHandler, $_linuxConfig, $_distroConfig, $_lsb_release = null;

    public function __construct($dn=null,$attrs = null,$acls = null)
    {
        if ($attrs == null)
            $attrs = array();

        if ($acls == null)
            $acls = array();

        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();
    }

    public function serverCtrl($request)
    {
        if (!is_array($request) || empty($request)) {
            throw new Zivios_Exception('Invalid call to serverCtrl. Options must be '.
                ' specified as an array');
        }
        
        // Initialize agent communication with linux core module.
        $agtModLinux = $this->getAgent('zvlinuxcomputer');
                
        if (array_key_exists('action', $request)) {
            switch ($request['action']) {
                case 'shutdown':
                    // Request to shutdown server.
                    Zivios_Log::info('Shutting down server: ' . $this->getProperty('cn'));

                    if ($agtModLinux->shutdownSystem()) {
                        return 1;
                    } else {
                        throw new Zivios_Error('Shutdown System call failed.');
                    }

                    break;

                case 'reboot':
                    Zivios_Log::info('Reboot server: ' . $this->getProperty('cn'));
                    if ($agtModLinux->rebootSystem()) {
                        return 1;
                    } else {
                        throw new Zivios_Error('Reboot System call failed.');
                    }
                    break;

                case 'probe':
                    try {
                        $srvDetails = $agtModLinux->probe_hardware();
                        // Update all server details
                        if ($this->updateHardwareDetails($srvDetails)) {
                            return true;
                        } else {
                            return false;
                        }
                    } catch (Exception $e) {
                        Zivios_Log::debug($e->getMessage());
                        throw new Zivios_Exception('System Failure: Error during Agent communication.');
                    }
                    
                    break;
            }
        }
    }

    /**
     * Get the system uptime, or false if exit code isn't found to be
     * zero.
     *
     * @return string | boolean
     */
    public function getSystemUptime()
    {
        $agtModLinux = $this->getAgent('zvlinuxcomputer');
        if (0 !== ($uptime = $agtModLinux->uptime())) {
            return $uptime;
        } else {
            return 0;
        }
    }

    protected function updateHardwareDetails($hardwareDetails)
    {
        if (!is_array($hardwareDetails) || empty($hardwareDetails)) {
            return false;
        }
        
        $cpu      = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[0]));
        $cpumhz   = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[1]));
        $cpucount = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[2]));
        $mem      = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[3]));
        $swap     = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[4]));
        $arch     = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[5]));
        $release  = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[6]));
        $distro   = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[7]));
        $codename = trim(ereg_replace("[ \t]+", " ", $hardwareDetails[8]));

        $codeRelease = ucfirst(strtolower($codename)) . '-' . 
            ucfirst(strtolower($release));

        // format for attribute-level and update details.
        $this->setProperty('emscomputervendormodel', $cpu);
        $this->setProperty('emscomputercpumhz', $cpumhz);
        $this->setProperty('emscomputercpucount', $cpucount);
        $this->setProperty('emscomputerram', Zivios_Util::formatSize($mem * 1024, null, null, false));
        $this->setProperty('emscomputerswap', Zivios_Util::formatSize($swap * 1024, null, null, false));
        $this->setProperty('emscomputerarch', $arch);
        $this->setProperty('emscomputerdistrorelease', $codeRelease);

        return true;
    }

    public function probeDistributionDetails($updateEntry=false)
    {
        $lsb_release = $this->_getLsbRelease();
        
        $cmds = array(
            'distro'   => $lsb_release . ' -i -s', 
            'codename' => $lsb_release . ' -c -s', 
            'release'  => $lsb_release . ' -r -s'
        );

        $distroDetails = array();
        foreach ($cmds as $key => $cmd) {
            $output = $this->execRemoteCmd($cmd, true, 30, '', 0);
            if ($output[2] == 0) {
                if (preg_match("/suse/",strtolower($output[1]))) {
                    $distroDetails[$key] = "suse";
                }
                else {
                    $distroDetails[$key] = str_replace(' ','_',$output[1]);
                }
            } else {
                throw new Zivios_Error('Error probing system. Please check Zivios logs.');
            }
        }

        if ($updateEntry) {
            // Zivios specific distro Id mapping.
            $distroRelease = $distroDetails['codename'] . '-' . $distroDetails['release'];
            $distroDesc    = $distroDetails['distro'] . ' ' . $distroRelease;

            $this->setProperty('emscomputerdistro', $distroDetails['distro']);
            $this->setProperty('emscomputerdistrorelease', $distroRelease);
            $this->setProperty('emscomputersystem', 'Linux');
            $this->setProperty('emsdistrodesc', $distroDesc);
            $this->setProperty('emsdistrocodename', $distroDetails['codename']);
        }

        return $distroDetails;
    }

    public function probeSystemDetails($updateEntry=false)
    {
        $cmds = array();
        $cmds['cpu']      = "cat /proc/cpuinfo | grep 'model name' | awk -F\: '{print $2}'| uniq | sed -e 's/ //'";
        $cmds['cpumhz']   = "cat /proc/cpuinfo | grep 'cpu MHz' | awk -F\: '{print $2}'| uniq | sed -e 's/ //'";
        $cmds['cpucount'] = "cat /proc/cpuinfo | grep ^processor | wc -l";
        $cmds['ram']      = "cat /proc/meminfo | grep MemTotal | awk -F\: '{print $2}' | awk -F\  '{print $1 \" \" $2}'";
        $cmds['swap']     = "cat /proc/meminfo | grep SwapTotal | awk -F\: '{print $2}' | awk -F\  '{print $1 \" \" $2}'";
        $cmds['system']   = "uname -sr";
        $cmds['arch']     = "uname -m";
        $cmds['hostname'] = "hostname -f";
         
        $systemDetails = array();
        foreach ($cmds as $key => $cmd) {
            $output = $this->execRemoteCmd($cmd, true, 30, '', 0);
            if ($output[2] == 0) {
                // replace all extra space/tab formatting with single spaces.
                $output[1] = ereg_replace("[ \t]+", " ", $output[1]);
                $systemDetails[$key] = $output[1];
                Zivios_Log::debug("Valid output from command : ".$cmd);
            } else {
                if ($key == 'hostname') {
                    $cmd = "hostname -A";
                    Zivios_Log::debug("Hostname command failed, Trying alternate hostname -A");
                    $output = $this->execRemoteCmd($cmd, true, 30, '', 0);
                    if ($output[2] == 0) {
                        // replace all extra space/tab formatting with single spaces.
                        $output[1] = ereg_replace("[ \t]+", " ", $output[1]);
                        $systemDetails[$key] = $output[1];
                    }
                    else {               
                        throw new Zivios_Error('Error probing system. Please check Zivios logs.');
                    }
                }
                else {               
                        throw new Zivios_Error('Error probing system. Please check Zivios logs.');
                    }
            }
        }

        Zivios_Log::debug($systemDetails);
        
        $ram  = split(' ', $systemDetails['ram']);
        $swap = split(' ', $systemDetails['swap']);

        $systemDetails['ram']  = Zivios_Util::formatSize(($ram[0]  * 1024), null, null, false);
        $systemDetails['swap'] = Zivios_Util::formatSize(($swap[0] * 1024), null, null, false);

        if ($updateEntry) {
            $this->setProperty('emscomputerarch', $systemDetails['arch']);
        }

        return $systemDetails;
    }
    
    /**
     * Function initializes distribution package management software and 
     * proceeds to probe for required packages. A required package listing
     * can be found (for server add) by distribution in the default module's
     * linux config file.
     *
     * @return boolean
     */
    public function probeRequiredPackages()
    {
        if ('' === ($distro = strtolower($this->getProperty('emscomputerdistro')))) {
            throw new Zivios_Exception('Distribution not set on Computer Object.');
        }

        
        $distroConfig = $this->_iniDistroConfig($distro);
        if (isset($distroConfig->helper)) {
            $distroHelper = $distroConfig->helper;
        } else {
            $distroHelper = ucfirst(strtolower($distro));
        }

        Zivios_Log::debug("Loading Distro Helper :".$distroHelper);
        if (!@require_once dirname(__FILE__) . '/Linux/' . $distroHelper . '.php') {
            throw new Zivios_Exception('Distribution helper model not found. Please check Logs.');
        }

        $distroHelperClass = 'Linux_' . $distroHelper;
        $distroHelper = new $distroHelperClass();
        $pkgData = $distroHelper->H_probeServerAddPackages($this, $distroConfig);

        if (!empty($pkgData['pkgMissing'])) {
            foreach ($pkgData['pkgMissing'] as $pkg) {
                $pkgMissing = $pkg . ',';
            }

            throw new Zivios_Error('Could not find the following required packages: ' . $pkgMissing);
        }
        
        return true;
    }
    
    /**
     * Function checks if distribution and release details specified
     * are compatible with Zivios.
     *
     * @param  array $distroDetails
     * @return boolean
     */
    public function checkCompatibility($distroDetails)
    {
        if (!is_array($distroDetails) || !isset($distroDetails['distro']) || 
            !isset($distroDetails['codename']) || !isset($distroDetails['release'])) {
            throw new Zivios_Exception('Invalid call to function.');
        }

        $linuxConfig   = $this->_iniLinuxConfig();
        $distroSupport = explode(',', $linuxConfig->base->distroSupport);

        $distro   = strtolower($distroDetails['distro']);
        $codename = strtolower($distroDetails['codename']);
        $release  = $distroDetails['release'];

        if (!in_array($distro, $distroSupport)) {
            // unsupported distribution.
            return false;
        }

        // check release for distribution.
        if (false === ($distroConfig = $this->_iniDistroConfig($distro))) {
            return false;
        }
        
        // finally, we ensure the release is compatible with Zivios.
        $releasesSupported = explode(',', $distroConfig->releaseSupport);
        if (!in_array($codename, $releasesSupported)) {
            return false;
        }

        return true;
    }
    
    /**
     * Checks the local installation for a listing of compatible Linux
     * distributions and returns all relevant data (codename / release)
     * for compatible systems.
     *
     * @return array $distrosCompatible
     */
    public function getCompatibleSystems()
    {}


    public function getAvailableCoreServices($parentDn, $serviceFilter=array())
    {
        $serviceData = $this->initializeServiceScan($parentDn);
        return $serviceData['availableServices'];
    }

    protected function _iniLinuxConfig()
    {
        if (!$this->_linuxConfig instanceof Zend_Config_Ini) {
            // initialize linux configuration.
            $appConfig = Zend_Registry::get('appConfig');
            $linuxConfig = $appConfig->modules . '/default/config/Linux/config.ini';
            $this->_linuxConfig = new Zend_Config_Ini($linuxConfig);
        }

        return $this->_linuxConfig;
    }

    protected function _iniDistroConfig($distro)
    {
        if (!$this->_distroConfig instanceof Zend_Config_Ini) {
            // initialize distro config
            try {
                $distro = str_replace(' ','_',$distro);
                $appConfig = Zend_Registry::get('appConfig');
                $linuxConfig = $appConfig->modules . '/default/config/Linux/config.ini';
                Zivios_Log::debug("Loading config for Distro : ".$distro);
                $this->_distroConfig = new Zend_Config_Ini($linuxConfig, $distro);
            } catch (Exception $e) {
                Zivios_Log::error("Distribution incompatible with Zivios.");
                return false;
            }
        }

        return $this->_distroConfig;
    }

    protected function _getLsbRelease()
    {
        if (null === $this->_lsb_release || $this->_lsb_release == '') {
            // before probing for lsb_release, ensure the sshHandler has been
            // initialized.
            if (!$this->_sshHandler instanceof Zivios_Ssh) {
                throw new Zivios_Error('SSH connection not initialized. ' . 
                    'lsb_release probe failed.');
            }

            $cmd = 'which lsb_release';
            $lsb_avail = $this->execRemoteCmd($cmd, true, 30, '', 0);

            if ($lsb_avail[2] != 0) {
                throw new Zivios_Error('lsb_release not found on system.');
            }

            $this->_lsb_release = $lsb_avail[1];
            return $this->_lsb_release;
        }
    }

    public function setSshHandler($usepubkey, $user='', $pass='')
    {
        if (!$this->_sshHandler instanceof Zivios_Ssh) {
            if (!$usepubkey && $user != '' && $pass != '') {
                $this->__iniSshHandler(0, $user, $pass);
            } else
                $this->__iniSshHandler(1);
        }
    }
    
    protected function execRemoteCmd($cmd, $trim_last=false, $timeout=10, $expect="", $cmdlog=0)
    {
        if (!$this->_sshHandler instanceof Zivios_Ssh) {
            throw new Zivios_Exception('Uninitialized SSH handler. execRemoteCmd call failed.');
        }

        return $this->_sshHandler->execShellCmd($cmd, $trim_last, $timeout, $expect, $cmdlog);
    }

    public function shellCmd($cmd, $trim_last=false, $timeout=10, $expect="", $cmdlog=0,$returnExitCode=0)
    {
        if (!$this->_sshHandler instanceof Zivios_Ssh) {
            throw new Zivios_Exception('No SSH instance found. ->setSshHandler() call not made?');
        }

        return $this->_sshHandler->shellCmd($cmd, $trim_last, $timeout, $expect, $cmdlog,$returnExitCode);
    }

    // A public function starting with an _ ?
    // @todo: trace calls and fix method name.
    public function _remoteScp($srcFile, $dstFile, $direction)
    {
        if (!$this->_sshHandler instanceof Zivios_Ssh) {
            throw new Zivios_Exception('No SSH instance found. ->setSshHandler() call not made?');
        }

        return $this->_sshHandler->remoteScp($srcFile, $dstFile, $direction);
    }

    /**
     * Remotely configure the Zivios agent and copy across the
     * configuration file via ssh.
     */
    public function configureZiviosAgent($user, $pass)
    {
        // Initialize SSH connection.
        $this->setSshHandler(0, $user, $pass);

        $ldapConfig = Zend_Registry::get('ldapConfig');
        $appConfig = Zend_Registry::get('appConfig');

        $filename = Zivios_Util::randomString(6);
        $file = $appConfig->tmpdir . '/' . $filename;

        $caplugin = $this->getPlugin('CaComputer');
        if ($fp = fopen($file, "w")) {
            // Write the Zivios agent configuration file.
            $content = "[general]\n";
            $content.= "computerdn=".$this->getdn()."\n";
            $content.= "distro=".$this->getComputerDistroId()."\n";
            $content.= "logfile=ZiviosAgent.log\n";
            $content.= "sslcert=".$caplugin->getPubKeyPath()."\n";
            $content.= "sslprivatekey=".$caplugin->getPrvKeyPath()."\n";
            $content.= "masterldap=".$ldapConfig->host."\n";

            if (!fwrite($fp, $content)) {
                throw new Zivios_Exception('Could not write agent configuration data to file.');
            }

            // scp file to remote system.
            $dstfile = '/opt/zivios/zivios-agent/config/ZiviosAgentManager.ini';
            $this->_sshHandler->openShell();
            $this->_sshHandler->remoteScp($file, $dstfile);
            $this->_sshHandler->closeShell();

        } else {
            throw new Zivios_Exception('Could not create temporary agent configuration file.');
        }

        // close handler & delete the file.
        fclose($fp);
        unlink($file);

        $filename = Zivios_Util::randomString(6);
        $file = $appConfig->tmpdir . '/' . $filename;
        
        if ($fp = fopen($file, "w")) {
            $content = 'START="yes"';

            if (!fwrite($fp, $content)) {
                throw new Zivios_Exception('Could not write agent defaults file for daemon start');
            }
            
            // scp file to remote system
            $dstfile = '/opt/zivios/zivios-agent/config/defaults';
            $this->_sshHandler->openShell();
            $this->_sshHandler->remoteScp($file, $dstfile);
            $this->_sshHandler->closeShell();

        } else {
            throw new Zivios_Exception('Could not create temporary agent configuration file (defaults).');
        }

        // close handler & delete the file.
        fclose($fp);
        unlink($file);        
    }

    public function restartZiviosAgent($user,$pass)
    {
        Zivios_Log::debug("**** Executing restartAgent ****");
        Zivios_Log::debug('Initializing SSH Connection...');
        $this->setSshHandler(0, $user, $pass);
        $this->_sshHandler->shellCmd("/etc/init.d/zvagent restart");
        $this->_sshHandler->closeShell();
        Zivios_Log::debug('Sleeping for 2 seconds');
        sleep(2);
        return;
    }
    
    private function __iniSshHandler($usepubkey, $user='',$pass='')
    {
        $options = array();
        $options['hostname']   = $this->getProperty('cn');
        $options['ip_address'] = $this->getProperty('iphostnumber');
        $options['port'] = 22;

        if ($usepubkey) {
            $this->_sshHandler = new Zivios_Ssh($options, 0);
        } else {
            $options['username'] = $user;
            $options['password'] = $pass;
            $this->_sshHandler = new Zivios_Ssh($options, 1);
        }

        $this->_sshHandler->connect();
        $this->_sshHandler->openShell();
    }
}

