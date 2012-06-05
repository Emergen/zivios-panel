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
 * @package		mod_openafs
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: AfsClient.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/

class AfsClient extends Ecl_Service_Plugin
{
	const PLUGIN = 'openafs';
	const PLUGINDISPLAYNAME = 'OpenAFS';
	const RDN = 'o=openafs,';

	private $_sshHandler, $_cfg = 0, $_krb5realm;
	public $log;

	public function __construct(EMSComputer $computerobj, $sshResource="")
	{
		parent::__construct($computerobj);

		/**
		 * Ensure this is an AFS Client
		 */
		$role = $this->_computerobj->getProperty('emsafsrole');
		if (!in_array("CL", $role))
			throw new Ecl_Exception("Computer is not an AFS Client");

		/**
		 * If a valid ssh resource is supplied, link it to the internal
		 * sshHandler resource.
		 */
		if ($sshResource instanceof Ecl_Ssh) {
			Ecl_Log::debug("Valid SSH Handler passed to Client");
			$this->_iniConfig();
			$this->_sshHandler = &$sshResource;
			$this->_acquireTokens();
		} else {
			/**
			 * Initialize the resource and get AFS required credentials.
			 */
			$this->_iniConfig();
			$this->_iniSshHandler();
			$this->_acquireTokens();
		}
	}

	/**
	 * Provisions for generating a context menu for the plugin. If no context
	 * menu is desired, the plugin should simply return false.
	 *
	 * @return object $menuCfg || false
	 */
	public function generateContextMenu()
	{
		/**
		 * Load menu configuration file for AFS and simply return.
		 *
		 * @todo: take care of possible failures here -- however, if an
		 * exception is thrown, it's because the developer is miconfiguring
		 * his/her plugin.
		 */
		$this->_iniConfig();

		return new Zend_Config_Ini($this->appConfig->bootstrap->modules .
			self::PLUGIN . '/config/menu.ini', 'DesktopEntry');
	}


	public function returnPluginName()
	{
		return self::PLUGIN;
	}

	public function returnDisplayName()
	{
		return self::PLUGINDISPLAYNAME;
	}


	/**
	 * Acquires Kerberos tickets as well as AFS tokens.
	 */
	private function _acquireTokens()
	{
		$this->_initKrb5Crds();
		$this->_getAfsTokens();
	}

	/**
	 * Call the Kerberos module and acquire kerberos credentials on
	 * client system
	 */
	private function _initKrb5Crds()
	{
		$krbServer = new KerberosServer($this->_computerobj);
		$krbServer->getUserCreds($this->_sshHandler);
	}

	/**
	 * Call to aklog as defined by the computer object's plugin
	 * setting.
	 */
	private function _getAfsTokens()
	{
		$userCred = Ecl_Ldap_Util::getUserCreds();
		$cout = $this->_shellcmd($this->_cfg->aklog, true);

		if ($cout != "") {
			/**
			 * The 'aklog' command does not send back any output by default.
			 * If output is received, something went wrong. We simply throw
			 * an Exception.
			 */
			throw new Ecl_Exception("Could not get AFS tokens." .
			" Please see system logs.");
		}
	}

	/**
	 * Destroy AFS tokens
	 */
	private function _destroyAfsSTokens()
	{
		$this->_shellCmd($this->_cfg->unlog, true);
	}

	private function _iniConfig()
	{
		if (!$this->_cfg instanceof Zend_Config_Ini) {
			$cfg_section = $this->_computerobj->getproperty("emscomputerdistro")
				. '_' . $this->_computerobj->getproperty("emscomputerdistrorelease");

			/**
			 * Get application configuration object
			 */
			$appConfig = Zend_Registry::get('appConfig');

			/**
			 * Instantiate cfg object for plugin
			 */
			$this->_cfg = new Zend_Config_Ini($appConfig->bootstrap->modules .
				self::PLUGIN . '/config/plugin.ini', $cfg_section);
		}
	}
}
