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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class CaUser extends Zivios_Plugin_User
{
	protected $_module = 'ca';
	private $_caMasterService;

	public function __construct()
	{
		parent::__construct();
    }

    public function getAttrs()
    {
        $attr = parent::getAttrs();
		$attr[] = 'userCertificate';
        return $attr;
	}

	public function readCertsOnFile()
	{
		$usercert = $this->_userConfig->general->usercerts . '/' . $this->getProperty('uid') . '.crt';
		$userkey = $this->_userConfig->general->userprvcerts . '/' . $this->getProperty('uid') . '.key';

		if (!file_exists($usercert) || !is_readable($usercert))
			throw new Zivios_Exception("Could not read user certificate public key");

		if (!file_exists($userkey) || !is_readable($userkey))
			throw new Zivios_Exception("Could not read user certificate public key");

		/**
		 * Read contents of both files & return to caller.
		 */
		if (!$fp = fopen($usercert, "r"))
			throw new Zivios_Exception("Could not open user certificate for reading");

		$pubcert = '';
		while (!feof($fp)) {
			$pubcert .= fgets($fp, 4096);
		}
		fclose($fp);

		if (!$fp = fopen($userkey, "r"))
			throw new Zivios_Exception("Could not open user certificate (key) for reading");

		$prvcert = '';
		while (!feof($fp)) {
			$prvcert .= fgets($fp, 4096);
		}
		fclose($fp);

		return array('pubcert' => $pubcert, 'prvkey' => $prvcert);
	}

	/**
	 * Read the user certificate data and populate dashboard.
	 */
	public function getUserDashboard()
	{
		$usercert = $this->_userConfig->general->usercerts . '/' . $this->getProperty('uid') . '.crt';
		Zivios_Log::debug("looking for: " . $usercert);
		if (!file_exists($usercert) || !is_readable($usercert))
			throw new Zivios_Exception("No certificate on file for User with active CA plugin.");

		/**
		 * Initialize CA service and read user certificate.
		 */
		$this->_initCaService();
		if ($certDetails = $this->_caMasterService->loadCertFromFile($usercert)) {
			return $certDetails;
		} else
			throw new Zivios_Exception("Could not load user certificate.");
	}

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);
    }

    public function genCertificate($ext, $props)
    {
    	$this->_initCaService();
    	if ($this->_caMasterService->genCert($ext, $props,
    		$this->getProperty("cn"), $this->getProperty("uid"))) {
    		return 1;
    	}
    }

	private function _initCaService()
	{
		if (!$this->_caMasterService instanceof CaService) {
        	$group = $this->getGroupPlugin();
        	$this->_caMasterService = $group->getService();
        	Zivios_Log::debug('CA Service Initialized.');
		}
	}

	public function add(Zivios_Transaction_Group $tgroup, $description=null)
	{
		Zivios_Log::debug("called CA add");
        return parent::add($tgroup);
	}

	public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{}

	public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{}

	public function generateContextMenu()
	{}
}
