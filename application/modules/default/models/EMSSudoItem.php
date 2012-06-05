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
 * @version		$Id: EMSSudoItem.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSSudoItem extends EMSSecurityObject
{
	private $serviceclass,$computer,$userarray,$grouparray;
	public function __construct(Zivios_LdapObject $lobj)
	{
		parent::__construct($lobj);
		$param = $this->addParameter('cn','SudoItemName',1);

		$this->addParameter('sudocommand','Sudo Command',1);
		$this->addParameter('sudohost','Sudo Host',1);
		$this->addParameter('sudouser','Sudo User',1);
		$this->addParameter('emsplugins','Ems Plugin attached',1);
	}
    
	public function setHost(EMSComputer $computer)
	{
		$this->setProperty('sudohost',$computer->getProperty('cn'));

	}

	public function linkToService(Zivios_Service_Plugin $servplug)
	{
		$this->setProperty('emsplugins',get_class($servplug));
		$this->setProperty('sudocommand',$servplug->getAdminCommandArray());

	}

	public function addUser(EMSUser $user,Zivios_Transaction_Handler $handler=null)
	{
		$userparm = $this->getParameter('sudouser');
		$userparm->addValue($user->getProperty('uid'));
	}

	public function addGroup(EMSGroup $group,Zivios_Transaction_Handler $handler=null)
	{
		$groupparm = $this->getProperty('sudouser');
		$groupparm->addValue('%'.$group->getProperty('cn'));
		return parent::update($handler);
	}

	public function removeUser(EMSUser $user)
	{
		$userparm = $this->getParameter("sudouser");
		$userparm->removeValue($user->getProperty('uid'));
	}

	public function removeGroup(EMSGroup $group)
	{
	}

	public function makeDn($parent)
	{
		$class = $this->getProperty('emsplugins');
		$host = $this->getProperty('sudohost');
		$parentdn = $parent->getdn();
		$dn = $this->getrdn()."=".$class."__".$host.",".$parent->getdn();
		Zivios_Log::debug("Making DN for sudo item returned $dn");
		return $dn;
	}

	public function getrdn()
	{
		return 'cn';
	}



	public function add(EMSObject $parent,Zivios_Transaction_Handler $handler=null)
	{
		if ($handler == null) {
			$handler = $this->getTransaction();
		}
		if (get_class($this) == 'EMSSudoItem') {
			$class = $this->getProperty('emsplugins');
			$host = $this->getProperty('sudohost');
			$cn = $class."__".$host;
			$this->setProperty('cn',$cn);
            $this->addObjectClass('emssudoitem');
            $this->addObjectClass('sudorole');
            $this->addObjectClass('emsignore');

		}

		if ($this->getProperty('emsdescription') == null) {
			$this->setProperty('emsdescription','Sudo Entry');
		}

		$this->setProperty('emstype',self::TYPE_SUDOI);
		return parent::add($parent,$handler);

	}
}
