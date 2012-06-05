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
 * @version		$Id: EMSDhcpPool.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSDhcpPool extends EMSObject
{

	public function __construct(Zivios_LdapObject $lobj)
	{
		parent::__construct($lobj);
		$this->addParameter('dhcprange','Dhcp Pool Range',1);


	}


	public function add(EMSObject $parent,Zivios_Transaction_Group $tgroup)
	{
		if (get_class($this) == 'EMSDhcpPool') {
            $this->addObjectClass('emsdhcppool');
            $this->addObjectClass('dhcppool');


		}

		return parent::add($parent,$tgroup);
	}

	public function setRange($start,$stop)
	{
		$this->setProperty('dhcprange',"$start $stop");
		$this->setProperty('cn',"$start-$stop");
	}

}

