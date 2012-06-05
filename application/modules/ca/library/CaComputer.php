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

class CaComputer extends Zivios_Plugin_Computer
{
	private $_caMasterService;
	protected $_module = 'ca';

	public function generateContextMenu()
	{
		return false;
	}

	public function genServiceCert(Zivios_Transaction_Group $tgroup,$capabilities,$props)
	{
		Zivios_Log::DEBUG("****Adding new transaction item for certificate generation****");
		$titem = $tgroup->newTransactionItem('Generating Certificate For Server Add');
		$titem->addObject('emsservice', $this->getService());
		$titem->addObject('capabilities', $capabilities);
		$titem->addObject('props', $props);
		$titem->addCommitLine('$this->emsservice->gencert($this->capabilities,$this->props,true);');
	}

	public function copyCertToHost(Zivios_Transaction_Group $tgroup, $user, $pass)
	{
		Zivios_Log::DEBUG("****Adding new transaction item for certificate COPY****");
		$titem = $tgroup->newTransactionItem('Securely copying certificates to added server');
		$titem->addObject('cacomp', $this);
		$titem->addObject('user', $user);
		$titem->addObject('pass', $pass);
		$titem->addCommitLine('$this->cacomp->scpCertsToHost($this->user, $this->pass);');
	}

	public function scpCertsToHost($user,$pass)
	{
		Zivios_Log::debug("**** Executing scpCertsToHost ****");
		Zivios_Log::debug('Initializing SSH Connection...');
		$options = array();
		$options['username'] = $user;
		$options['password'] = $pass;
		$options['hostname'] = $this->getProperty('cn');
		$options['ip_address'] = $this->getProperty('iphostnumber');
		$options['port'] = 22;
		$sConn = new Zivios_Ssh($options, 1);
		$sConn->connect();

		// That was just to test ssh -- we now look for further defaults
		Zivios_Log::debug('remote SCP required for CA public key to: '    . $this->_compConfig->cacertsdir);
		Zivios_Log::debug('remote SCP required for host public key to: '  . $this->_compConfig->pubcerts);
		Zivios_Log::debug('remote SCP required for host private key to: ' . $this->_compConfig->prvkeys);

		Zivios_Log::debug("Attempting remote SCP");
		//$sConn->openShell();

		// CA Pub key copy
		$srcfile = $this->_serviceCfgGeneral->cacertsdir . '/' . $this->_serviceCfgGeneral->capubkey;
		$dstfile = $this->_compConfig->cacertsdir . '/' . $this->_serviceCfgGeneral->capubkey;
		Zivios_Log::debug("CA Pub key on master is at: " . $srcfile);
		Zivios_Log::debug("Ca Pub key location on host is at: " . $dstfile);
		$sConn->remoteScp($srcfile, $dstfile, "send");

		// Host Pub key copy
        Zivios_Log::debug('Creating public certificate folder ' . $this->_compConfig->pubcerts);
		$srcfile = $this->_serviceCfgGeneral->pubcerts . '/' . $this->getProperty('cn') . '.crt';
        $sConn->shellCmd('/bin/mkdir -p ' . $this->_compConfig->pubcerts);
        $sConn->closeShell();
		$dstfile = $this->_compConfig->pubcerts . '/' . $this->getProperty('cn') . '.crt';
		Zivios_Log::debug("Copying From: " . $srcfile . " to remote host location: " . $dstfile);
		$sConn->remoteScp($srcfile, $dstfile, "send");

		// Host Prv key copy
		$srcfile = $this->_serviceCfgGeneral->prvkeys . '/' . $this->getProperty('cn') . '.key';
		$dstfile = $this->_compConfig->prvkeys . '/' . $this->getProperty('cn') . '.key';
		Zivios_Log::debug("Copying From: " . $srcfile . " to remote host location: " . $dstfile);
		$sConn->remoteScp($srcfile, $dstfile, "send");

		// Fixing permission and ownership on copied files
		$sConn->shellCmd('/bin/chmod 640 ' . $dstfile,null,30);
		$sConn->shellCmd('/bin/chown root:ssl-cert ' . $dstfile,null,30);

		// Regenrating certificate hashes.
		$sConn->shellCmd($this->_compConfig->certrehash);
		Zivios_Log::debug('Closing SSH shell...');
		$sConn->closeShell();
	}

	public function getPubKeyPath()
	{
		return $this->_compConfig->pubcerts . '/' . $this->getProperty('cn') . '.crt';
	}

	public function getPrvKeyPath()
	{
		return $this->_compConfig->prvkeys . '/' . $this->getProperty('cn') . '.key';
	}

	public function getCaCertPath()
	{
		return $this->_compConfig->cacertsdir . '/' . $this->_serviceCfgGeneral->capubkey;
	}

	public function getDashboardData()
	{
		/**
		 * Read the stored public key for the computer and display
		 * the common params.
		 */
		$certFile = $this->_serviceCfgGeneral->pubcerts . '/' . $this->getProperty('cn') .
			'.crt';

		if (file_exists($certFile) && is_readable($certFile)) {
			$this->_initCaService();
			if ($certDetails = $this->_caMasterService->loadCertFromFile($certFile)) {
				return $certDetails;
			} else
				throw new Zivios_Exception("Could not load system certificate.");
		}

		return 0;
	}

	private function _initCaService()
	{
		$this->_caMasterService = $this->getMasterService();
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

		//Core service DNs are hardcoded in the system.
		$sdn = 'cn=Zivios CA,ou=master services,ou=core control,ou=zivios,'
			. $ldapConfig->basedn;

        $service = Zivios_Ldap_Cache::loadDn($sdn);
		return $service;
	}
}
