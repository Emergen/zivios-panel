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
 * @version		$Id: EMSSudoUnit.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSSudoUnit extends EMSOrganizationalUnit
{
	public function __construct(Zivios_LdapObject $lobj)
	{
		parent::__construct($lobj);
	}

	public function add(EMSObject $parent,Zivios_Transaction_Handler $handler=null)
	{
		if (get_class($this) == 'EMSSudoUnit') {
            $this->addObjectClass('emssudounit');

		}
		return parent::add($parent,$handler);
	}

	public function giveSudoToUser(EMSUser $user,Zivios_Service_Plugin $plugin,EMSComputer $computer)
	{
		$sudoitems = $this->getSudoItemsForService(get_class($plugin),$computer);
		if (sizeof($sudoitems) > 0) {
			if (sizeof($sudoitems) > 1) {
				Zivios_Log::error("More than 1 sudo item returned for plugin and computer search, will not proceed");
			} else {
				// Only one sudoitem returned, go for it
				$sudoitem = $sudoitems[0];
				$sudoitem->addUser($user);
				return $sudoitem->update();
			}
		} else {
			// Create a sudo item for this setup
			$sudoitem = new EMSSudoItem($this->_lobj->respawn(null));
			$sudoitem->linkToService($plugin);
			$sudoitem->setHost($computer);
			$sudoitem->addUser($user);
			return $sudoitem->add($this);
	}
	}

	public function removeSudoFromUser(EMSUser $user,Zivios_Service_Plugin $plugin,EMSComputer $computer)
	{
		$sudoitems = $this->getSudoItemsForUser($user,get_class($plugin),$computer);
		if (sizeof($sudoitems) > 0) {
			$sudoitem = $sudoitems[0];
			$sudoitem->removeUser($user);
			return $sudoitem->update();
		}
	}

	public function getrdn() {
		return "ou";
	}

	public function giveSudoToGroup(EMSGroup $user,Zivios_Service_Plugin $plugin,EMSComputer $computer)
	{
	}

	public function getSudoItemsForUser(EMSUser $user,$pluginname=null,EMSComputer $computer=null)
	{
		$filter = '(&(objectclass=emssudoitem)';
		$filter .= '(sudoUser='.$user->getProperty('uid').')';
		if ($pluginname != null ) {
			$filter .= '(emsplugins='.$pluginname.')';
		}
		if ($computer != null) {
			$filter .= '(sudoHost='.$computer->getProperty('cn').')';
		}
		$filter .= ')';
		Zivios_Log::debug("Sudo item search using filter :$filter");
		$children = $this->_lobj->getAllChildren($filter);

		return $children;


	}

	public function getSudoItemsForService($pluginname,EMSComputer $computer)
	{
		$compname = $computer->getProperty('cn');
		$filter = "(&(objectclass=emsSudoItem)(emsplugins=$pluginname)(sudoHost=$compname))";
		$children = $this->_lobj->getAllChildren($filter);

		return $children;
	}

	public function getSudoItemsForGroup(EMSGroup $group,$plugin=null,$computer=null)
	{
	}


}

