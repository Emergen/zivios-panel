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
 * @version		$Id: Afs.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/
abstract class Afs extends Ecl_Plugin_Service
{
	const PLUGIN = 'openafs';
	const PLUGINDISPLAYNAME = 'OpenAFS';

	/**
	 * Returns the plugin name.
	 * Enforced by the abstract class Ecl_Service_Plugin
	 *
	 * @return constant plugin name
	 */
	public function returnPluginName()
	{
		return self::PLUGIN;
	}

	/**
	 * Returns the plugin display name.
	 * Enforced by the abstract class Ecl_Service_Plugin
	 *
	 * @return constant plugin display name
	 */
	public function returnDisplayName()
	{
		return self::PLUGINDISPLAYNAME;
	}
}