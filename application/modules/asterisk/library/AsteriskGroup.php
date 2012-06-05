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
 * @version		$Id: AsteriskGroup.php 902 2008-08-25 06:39:02Z gmustafa $
 **/

class AsteriskGroup extends Zivios_Plugin_Group
{

	protected $_module='asterisk';


	public function __construct()
	{
		parent::__construct();
	}

    public function getAttrs()
	{
        $attrs = parent::getAttrs();
        return $attrs;
	}

	public function delete(Zivios_Transaction_Group $tgroup,$description=null)
	{
		return parent::delete($tgroup,$description);
	}


	public function addToGroup(EMSUser $user,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

	public function removeFromGroup(EMSUser $user,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

	public function generateContextMenu()
	{
		return null;
	}
	public function getUserPluginName()
	{
		return 'AsteriskUser';
	}

	public function returnDisplayName()
	{
		return "Asterisk Plugin";
	}

	public function returnModuleName()
	{
		return "asterisk";
	}
}

