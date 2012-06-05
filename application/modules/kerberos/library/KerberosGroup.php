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
 * @package		mod_kerberos
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: KerberosGroup.php 913 2008-08-25 11:24:35Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:24:35 +0600 (Mon, 25 Aug 2008) $
 **/

class KerberosGroup extends Zivios_Plugin_Group
{
	protected $_module = 'kerberos';

	public function __construct()
	{
		parent::__construct();
	}

    public function getAttrs()
    {
        return parent::getAttrs();
    }


	public function generateContextMenu()
	{
		return false;
	}

	/**
	 * Searches for the master service from the basedn
	 * under the Zivios Master Services container and
	 * returns the service to caller.
	 *
	 * @return EMSService $masterService
	 */
	public function getMasterService()
	{
		$ldapConfig = Zend_Registry::get('ldapConfig');

		// Core service DNs are hardcoded in the system.
		$sdn = 'cn=Zivios Kerberos,ou=master services,ou=core control,ou=zivios,'
			. $ldapConfig->basedn;

        $service = Zivios_Ldap_Cache::loadDn($sdn);
		return $service;
	}
}
