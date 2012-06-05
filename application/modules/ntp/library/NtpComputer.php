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
 * @package		mod_ntp
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class NtpComputer extends Zivios_Plugin_Computer
{
	protected $_module = 'ntp';
	private   $_service;

	public function __construct()
	{
		parent::__construct();
    }

    public function getAttrs()
    {

        return parent::getAttrs();
    }

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);
        $servicemap = $this->getProperty('emsservicemap');

        if ($servicemap != NULL) {

        	Zivios_Log::debug("*!* Service map isn't null -- searching...");

            $this->_service = parent::getService();
        }
	}

	public function generateContextMenu()
	{
		return false;
	}

	function add(Zivios_Transaction_Group $tgroup,$description=null)
	{
		/**
		 * Set the servicemap accordingly.
		 */
		$servicemap = $this->getProperty("emsservicemap");
		$this->_service = parent::getService();
		parent::add($tgroup,$description);
		$this->_updateClientConfig($tgroup,'Updating NTP Config on client computer',1);
	}

	public function getDashboardData()
	{
		$this->_initCommAgent();

		$dashboardData = array();

		if ($this->_commAgent->serviceStatus())
			$dashboardData['status'] = 1;
		else
			$dashboardData['status'] = 0;

		$dashboardData['currentTime'] = $this->getServerTime();
		$dashboardData['masterservers'] = $this->_service->listNtpServers();
		return $dashboardData;
	}

	public function getServerTime()
	{
		$this->_initCommAgent();
		return $this->_commAgent->currentTime();
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

	public function updateClientConfig($restart=1)
	{
		/**
		 * Get's all relevant information from the NTP
		 * service and write the client configuration file.
		 */
		$clientSettings = $this->_service->getClientSettings();

		/**
		 * Initialize configuration for computer.
		 */
		$this->_iniCompConfig();

		/**
		 * Generate configuration file for client.
		 * @todo need to switch to file generation via templates.
		 */
		$headerBlock = "#\n# Zivios NTP Client Service Configuration File.\n" .
			"# Do NOT update manually, file will be overwritten.\n#\n\n";

		$mainConfigBlock = "# Drift File Location.\n" .
			"driftfile " . $this->_compConfig->driftfile . "\n\n";

		if (!empty($clientSettings['statSettings'])) {
			$statsConfigBlock = "# Statistics Settings.\n" .
				"statsdir " . $this->_compConfig->statsdir . "\n";

			/**
			 * Loop through all enabled stats & set config params
			 */
			foreach ($clientSettings['statSettings'] as $statToEnable) {
				$statsConfigBlock .= "filegen " . $statToEnable . " file " .
					$statToEnable . " type day enable\n";
			}
		} else
			$statsConfigBlock = "";

		$ntpServerConfigBlock = "\n# Ntp Servers.\n";

		foreach ($clientSettings['ntpServers'] as $ntpServer)
			$ntpServerConfigBlock .= "server " . $ntpServer . "\n";

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
		 * All settings generated, concat.
		 */
		$ntpConfFile = $headerBlock . $mainConfigBlock . $statsConfigBlock .
			$ntpServerConfigBlock . $addConfigBlock;


		/**
		 * Write file to /tmp
		 * @todo: filename should be random and written to application secure
		 * temp folder.
		 */
		$filename = "/tmp/zvntpclient.conf";

		if ($handle = fopen($filename, "w")) {
			if (fwrite($handle, $ntpConfFile) === FALSE) {
        		throw new Zivios_Exception("Writing to tmp file failed.");
    		}

    		fclose($handle);

    		/**
    		 * Send file across.
    		 */
    		$this->_computerobj->putfile($filename, $this->_compConfig->ntpconf);

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
	 * Searches for the master service from the basedn under the Zivios
	 * Master Services container and returns the service to caller.
	 *
	 * @return EMSService $masterService
	 */
	public function getMasterService()
	{
		$ldapConfig = Zend_Registry::get('ldapConfig');
		/**
		 * Core service DNs are hardcoded in the system.
		 */
		$sdn = 'cn=Zivios Time,ou=master services,ou=core control,ou=zivios,'
			. $ldapConfig->basedn;
		Zivios_Log::debug("Loading service dn... " . $sdn);
        $service = Zivios_Ldap_Cache::loadDn($sdn);
		return $service;
	}
}

