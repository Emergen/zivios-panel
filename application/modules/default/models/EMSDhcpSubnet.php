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
 * @version		$Id: EMSDhcpSubnet.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSDhcpSubnet extends EMSSecurityObject
{

	public function __construct(Zivios_LdapObject $lobj)
	{
		parent::__construct($lobj);
		$this->addParameter('dhcpoption','Dhcp Options',1);
		$this->addParameter('dhcpnetmask','Dhcp Network Mask',1);
	}

	public function add(EMSObject $parent,Zivios_Transaction_Group $tgroup)
	{
		if (get_class($this) == 'EMSDhcpSubnet') {
            $this->addObjectClass('emsdhcpsubnet');
            $this->addObjectClass('dhcpsubnet');
            $this->addObjectClass('dhcpoptions');


		}

		return parent::add($parent,$tgroup);
	}

	public function setDhcpOptions($dnsservers,$router,$subnetmask,$broadcast)
	{
        
		$param = $this->getParameter('dhcpoption');
		$param->addValue('domain-name-servers '.$dnsservers);
		$param->addValue('routers '.$router);
		$param->addValue('subnet-mask '.$subnetmask);
		$param->addValue('broadcast-address '.$broadcast);

		$netmaskarray = explode($subnetmask,'.');
		$x=0;
		foreach ($netmaskarray as $nm) {
			/**
			2^x = $nm
			x.ln 2 = ln $nm
			x = ln $nm / ln 2
			x = log $nm base 2
			*/

			$x += log ($nm,2);
		}
		Zivios_Log::debug("dhcp Netmask Log outputs Network mask (x) as $x");
		$this->setProperty('dhcpnetmask',$x);

	}

	public function setNetwork($network)
	{
		$this->setProperty('cn',$network);
	}
}
