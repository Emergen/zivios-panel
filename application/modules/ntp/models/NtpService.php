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
 * @package     mod_ntp
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class NtpService extends EMSService
{
    protected $_module = 'ntp';

    public function __construct($dn=null,$attrs=null)
    {

        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'emsntpserver';
        $attrs[] = 'emssubnetbroadcast';
        $attrs[] = 'emsstatisticsenable';
        $attrs[] = 'emsstatistics';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();

        $param = $this->getParameter('emsntpserver');
        $param = $this->getParameter('emssubnetbroadcast');
        


    }

    /**
     * Function is called to add the NTP service.
     *
     * @param Zivios_Transaction_Handler $handler
     * @return object Zivios_Transaction_Handler
     */
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        /**
         * Instantiate the master computer object
         */
        if (!$this->mastercomp instanceof EMSComputer) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($this->getProperty("emsmastercomputerdn"));
        }

        $this->_iniConfig();
        $this->setProperty('cn', $this->_serviceCfgGeneral->displayname);

        $this->addObjectClass('emsntpservice');
        return parent::add($parent,$tgroup);
    }

    /**
     * At some stage, multiple NTP servers will join a pool
     * which will be available to the clients. Currently however
     * we work with just one with the provision for expansion.
     */
    public function listNtpServers()
    {
        return array($this->mastercomp->getdn() => $this->mastercomp->getProperty("cn"));
    }

    public function getSystemTimezone()
    {
        $this->_initCommAgent();
        if ($timezone = $this->_commAgent->getTimezone()) {
            return $timezone;
        }
        return 0;
    }

    public function update(Zivios_Transaction_Group $tgroup, $description = null, $namespace='CORE')
    {
        parent::update($tgroup);
        $this->_updateMasterConfig($tgroup,'Updating Configuration and restarting Service');
        return $tgroup;
    }
    
    /**
     * Updates the master configuration file (ntp.conf) on the
     * NTP server
     *
     * @param boolean $restart
     * @return boolean
     */
    public function updateMasterConfig($restart=true)
    {
        /**
         * Get all required properties for file generation based on the OS
         * distribution.
         */
        $this->_setMasterComputerConfig();
        $ntpServers = $this->getNtpServers();
        $statSettings = $this->getStatSettings();
        $broadcastSettings = $this->getBroadcastSettings();

        /**
         * Build the configuration file block by block.
         * Initial block
         */
        $headerBlock = "#\n# Zivios NTP Service Configuration File.\n" .
            "# Do NOT update manually, file will be overwritten.\n#\n\n";

        $mainConfigBlock = "# Drift File Location.\n" .
            "driftfile " . $this->_compConfig->driftfile . "\n\n";

        if (!empty($statSettings)) {
            $statsConfigBlock = "# Statistics Settings.\n" .
                "statsdir " . $this->_compConfig->statsdir . "\n";

            /**
             * Loop through all enabled stats & set config params
             */
            foreach ($statSettings as $statToEnable) {
                $statsConfigBlock .= "filegen " . $statToEnable . " file " .
                    $statToEnable . " type day enable\n";
            }
        } else
            $statsConfigBlock = "";

        $ntpServerConfigBlock = "\n# Ntp Servers.\n";

        foreach ($ntpServers as $ntpServer) {
            $ntpServerConfigBlock .= "server " . $ntpServer . "\n";
        }

        /**
         * Additional Default Settings (will be controlled by NTP service
         * at a later stage).
         */
        $addConfigBlock = "\n# Exchange time with everyone by do not allow modification\n" .
            "restrict -4 default kod notrap nomodify nopeer noquery\n" .
            "restrict -6 default kod notrap nomodify nopeer noquery\n" .
            "restrict 127.0.0.1\n" .
            "restrict ::1\n";

        /**
         * Setup Broadcast settings (if enabled)
         */
        if (!empty($broadcastSettings)) {
            $broadcastSubnetBlock = "\n# Broadcast to Subnets.\n";
            foreach ($broadcastSettings as $broadcastSubnet) {
                $broadcastSubnetBlock .= "broadcast " . $broadcastSubnet . "\n";
            }
        } else
            $broadcastSubnetBlock = "";

        /**
         * All settings generated, concat.
         */
        $ntpConfFile = $headerBlock . $mainConfigBlock . $statsConfigBlock .
            $ntpServerConfigBlock . $addConfigBlock . $broadcastSubnetBlock;


        /**
         * Write file to /tmp
         */
        $filename = "/tmp/zvntp.conf";

        if ($handle = fopen($filename, "w")) {
            if (fwrite($handle, $ntpConfFile) === FALSE) {
                throw new Zivios_Exception("Writing to tmp file failed.");
            }

            fclose($handle);

            /**
             * Send file across.
             */
            $this->mastercomp->putfile($filename, $this->_compConfig->ntpconf);

            /**
             * Remove the tmpfile.
             */
            unlink($filename);

            /**
             * Restart the NTP service & check status
             */
            if ($restart) {
                $this->_initCommAgent();
                if (!$this->_commAgent->serviceStatus()) {
                    /**
                     * Service not running -- start it.
                     */
                    Zivios_Log::info("NTP Service not running. Trying to Start...");
                    if ($this->_commAgent->startService()) {
                        Zivios_Log::info("NTP Service started successfully");
                        return true;
                    }
                } else {
                    Zivios_Log::info("NTP Service running. Stopping Service...");
                    if ($this->_commAgent->stopService()) {
                        Zivios_Log::info("NTP Service Stopped successfully");
                        if ($this->_commAgent->startService()) {
                            Zivios_Log::info("NTP Service started Successfully");
                            return true;
                        } else {
                            Zivios_Log::error("Could not start NTP Service.");
                            return false;
                        }
                    } else {
                        Zivios_Log::error("Could not Stop NTP Service.");
                        return false;
                    }
                }
            }
        } else
            throw new Zivios_Exception("Could not create tmp file for ntp.conf");
    }


    /**
     * Stop the NTP service if it's running.
     *
     * @return boolean
     */
    public function stopService()
    {
        $this->_initCommAgent();
        if (!$this->_commAgent->serviceStatus()) {
            Zivios_Log::warn("Stop command sent to NTP Service -- Service not Running!");
            return false;
        } else {
            if ($this->_commAgent->stopService()) {
                Zivios_Log::info("NTP service halted.");
                return true;
            } else {
                Zivios_Log::error("Could not stop NTP Service.");
                return false;
            }
        }
    }

    /**
     * Start the NTP service if it's running.
     *
     * @return boolean
     */
    public function startService()
    {
        $this->_initCommAgent();
        if ($this->_commAgent->serviceStatus()) {
            Zivios_Log::warn("Trying to Start NTP Service... already running.");
            return false;
        } else {
            if ($this->_commAgent->startService()) {
                Zivios_Log::info("NTP service started.");
                return true;
            } else {
                Zivios_Log::error("Could not start NTP Service.");
                return false;
            }
        }
    }

    /**
     * If statistics are enabled, it returns all set options for
     * stats as an array. If stats are not enabled, an empy array
     * is returned.
     *
     * @return array $statSettings
     */
    public function getStatSettings()
    {
        if ($this->getProperty('emsstatisticsenable') != NULL &&
            $this->getProperty('emsstatisticsenable') == 1) {
            if (is_array($stats = $this->getProperty("emsstatistics")))
                return $stats;
            else
                return array($stats);
        }

        return array();
    }

    /**
     * Returns all subnets the NTP server will be broadcasting to.
     *
     * @return array $broadcastSubnets
     */
    public function getBroadcastSettings()
    {
        $bcSubnets = $this->getProperty('emssubnetbroadcast');
        if ($bcSubnets != NULL) {
            if (!is_array($bcSubnets))
                return array($bcSubnets);
            else
                return $bcSubnets;
        }

        return array();
    }

    /**
     * Returns listing of all registered NTP servers for the service
     *
     * @return array $ntpServers
     */
    public function getNtpServers()
    {
        $ntpServers = $this->getProperty("emsntpserver");

        if (!is_array($ntpServers))
            return array($ntpServers);

        return $ntpServers;
    }

    /**
     * Packs all service settings in array and return to caller.
     *
     * @return array $serverSettings
     */
    public function getServiceSettings()
    {
        $serverSettings = array();
        $serverSettings['ntpServers'] = $this->getNtpServers();
        $serverSettings['broadcastSettings'] = $this->getBroadcastSettings();
        $serverSettings['statSettings'] = $this->getStatSettings();
        return $serverSettings;
    }

    /**
     * Packs all service settings for client in array and returns
     * to caller.
     *
     * @return array $clientSettings
     */
    public function getClientSettings()
    {
        $clientSettings = array();
        $ntpservers = array();
        $ntpservers[] = $this->mastercomp->getProperty('cn');
        $clientSettings['ntpServers'] = $ntpservers;
        $clientSettings['statSettings'] = $this->getStatSettings();
        return $clientSettings;
    }

    /**
     * Loads all relevant dashboard data
     *
     * @return array $dashboardData
     */
    public function loadDashboardData()
    {
        $this->_initCommAgent();

        $dashboardData = array();

        if ($this->_commAgent->serviceStatus())
            $dashboardData['status'] = 1;
        else
            $dashboardData['status'] = 0;

        $dashboardData['currentTime'] = $this->getServerTime();

        if ($timezone = $this->getSystemTimezone())
            $dashboardData['timezone'] = $timezone;
        else
            $dashboardData['timezone'] = 'Could not detect';

        return $dashboardData;
    }

    public function getServiceStatus()
    {
        $this->_initCommAgent();
        return $this->_commAgent->serviceStatus();
    }

    public function getSyncSite()
    {
        return "N/A";
    }
    
    public function getSyncStatus()
    {
        $this->_initCommAgent();
        $status =  $this->_commAgent->getsyncstatus();
        $objarray = array();
        if ($status == -1) 
            return -1;
        
        foreach ($status as $stat) {
            $obj = new StdClass();
            $syncstattest = substr($stat[0],0,1);
            if ($syncstattest == '*' || $syncstattest == '+') {
                $name = substr($stat[0],1);
                $syncstat = '<font color="green"> Active Sync </font>';
            } else {
                $name = $stat[0];
                $syncstat = '<font color="red"> Trying.</font>';
            }
            
            $obj->name = $name;
            $obj->syncstatus = $syncstat;
            $obj->refid = $stat[1];
            $obj->stratum = $stat[2];
            $obj->delay = $stat[7];
            $obj->jitter = $stat[9];
            $objarray[] = $obj;
        }
        return $objarray;
    }
    
    public function getServerTime()
    {
        $this->_initCommAgent();
        return $this->_commAgent->currentTime();
    }
    
    
    public function getGmtOffset()
    {
        $this->_initCommAgent();
        return $this->_commAgent->getGmtOffset();
    }
    public function getTimeZone()
    {
        $this->_initCommAgent();
        return $this->_commAgent->getTimezone();
    }
}
