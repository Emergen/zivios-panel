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
 * @package		mod_default
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSOlcSchemaConfig.php 911 2008-08-25 11:06:13Z fkhan $
 **/


/**
 * Example usage on how to add a new schema:
 *
 * 		$config = EMSOlcSchemaConfig::getOlcSchemaConfig();
		$emsolc = new EMSOlcSchema($config->newLdapObject(null));
		$emsolc->setProperty('cn','{14}TestSchema');
		$emsolc->setProperty('emsdescription','Test Schema');
		$handler = $emsolc->add($config);
		$handler->process();
 *
 */

class EMSOlcSchemaConfig extends EMSObject
{

	public static function getOlcSchemaConfig()
	{
		return new EMSOlcSchemaConfig(Zivios_Ldap_Util::getCnConfig("cn=schema,cn=config"));
	}



}