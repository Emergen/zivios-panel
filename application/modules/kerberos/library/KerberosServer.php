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
 * @package		mod_kerberos
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class KerberosServer extends Zivios_Plugin_Service
{
	const RDN = 'o=kerberos,';
	const PLUGIN = 'kerberos';
	const PLUGINDISPLAYNAME = 'Kerberos';

	private $_sshHandler, $_cfg = 0;

	/**
	 * Links the computer entry in LDAP and instantiates the plugin
	 * config
	 *
	 * @param EMSComputer $comp
	 */
	public function __construct(EMSComputer $computerobj)
	{
		parent::__construct($computerobj);
		$this->_iniConfig();
	}

	/**
	 * Provisions for generating a context menu for the plugin. If no context
	 * menu is desired, the plugin should simply return false.
	 *
	 * @return object $menuCfg || false
	 */
	public function generateContextMenu()
	{
		return false;
	}

	/**
	 * Get user credentials on remote server
	 *
	 * @param Zivios_Ssh sshHandler (optional)
	 */
	public function getUserCreds($sshHandler="")
	{
		if ($sshHandler instanceof Zivios_Ssh)
			$this->_sshHandler = &$sshHandler;
		else
			$this->_iniSshHandler();

		/**
		 * Establish Kerberos credentials on client system.
		 */
		$userCred = Zivios_Ldap_Util::getUserCreds();
		Zivios_Log::debug("UserCred array is: ");
		Zivios_Log::debug($userCred);

		$expect = array('Password for ' . $userCred['krb5principal'] .':',
			$userCred['krb5principal'].'\'s Password:');

		$cmd = $this->_cfg->kinit . ' ' . $userCred['krb5principal'];
		$cout = $this->_shellCmd($cmd,false,10,$expect);
		$cout = $this->_shellCmd($userCred['password'], true, 10, "", 1);

		if (trim($cout) != "") {
			Zivios_Log::debug("Invalid kerberos credentials for principal: ".
				$userCred['krb5principal']);

			throw new Exception("Kerberos ticket acquisition failed.");
		}

		$cmd = $this->_cfg->klist;
		$cout = $this->_shellCmd($cmd,true);
		$this->validateKrb5Ticket($cout);
	}

	public function destroyKrb5Creds()
	{
		$this->_shellCmd($this->_cfg->kdestroy, true);
	}

	/**
	 * Validate a krb5 ticket
	 *
	 * @param string $ticket
	 */
	public function validateKrb5Ticket($ticket)
	{
		/**
		echo "<pre>";
		print_r($ticket);
		echo "</pre>";
		**/
	}

	/**
	 * Returns the plugin name.
	 * Enforced by the abstract class Zivios_Service_Plugin
	 *
	 * @return constant plugin name
	 */
	public function returnModuleName()
	{
		return 'kerberos';
	}

	/**
	 * Returns the plugin display name.
	 * Enforced by the abstract class Zivios_Service_Plugin
	 *
	 * @return constant plugin display name
	 */
	public function returnDisplayName()
	{
		return self::PLUGINDISPLAYNAME;
	}

	public function changePassword()
	{
		$this->_iniConfig();
	}

	private function _iniConfig()
	{
		if (!$this->_cfg instanceof Zend_Config_Ini) {
			$cfg_section = $this->_computerobj->getproperty("emscomputerdistro")
				. '_' . $this->_computerobj->getproperty("emscomputerdistrorelease");

			$appConfig = Zend_Registry::get('appConfig');

			$this->_cfg = new Zend_Config_Ini($appConfig->bootstrap->modules .
				self::PLUGIN . '/config/plugin.ini', $cfg_section);
		}
	}
}
