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
 * @version		$Id: HttpAccessItem.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Squid
 **/

class Ecl_Parser_Squid_HttpAccessItem
{
	/** Data Structure to hold Squid HTTP Access Items
	*/
	public $rule,$aclarray,$dtd;

	public function __construct($dtd,$rule)
	{
		$this->dtd = $dtd;
		$this->rule = trim($rule);
		$this->aclarray = array();
	}


	public function addValue($value)
	{
		$this->aclarray[] = trim($value);
	}

	public function render()
	{
		$rule = $this->rule;
		$array = $this->aclarray;
		$str="";
		$values = "";
		foreach ($array as $vals) {
			$values .= $vals." ";
		}
		$str="http_access $rule $values";
		$str.=$this->dtd->parameterterm;
		return $str;
	}

}
