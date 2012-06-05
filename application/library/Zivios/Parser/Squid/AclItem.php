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
 * @package		Parser
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: AclItem.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Squid
 **/

class Ecl_Parser_Squid_AclItem
{
	/** Data Structure to hold Squid ACL items
	*/
	public $aclname,$acltype,$parray,$dtd;

	public function __construct($dtd,$name,$type)
	{
		$this->dtd = $dtd;
		$this->aclname = trim($name);
		$this->acltype = trim($type);
		$this->parray = array();
	}


	public function addValue($value)
	{
		$this->parray[] = trim($value);
	}

	public function render()
	{
		$name = $this->aclname;
		$type = $this->acltype;
		$array = $this->parray;
		$str="";
		foreach ($array as $vals) {
			$str.="acl $name $type $vals";
			$str.=$this->dtd->parameterterm;
		}
		return $str;
	}

}



