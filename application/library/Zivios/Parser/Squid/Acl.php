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
 * @version		$Id: Acl.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Squid
 *
 **/

class Ecl_Parser_Squid_Acl extends Ecl_Parser_Element
{
	/** Acl Array is 3 dimensional. [name][acltype][string]
	*/

	public $aclarray;


	public function __construct(Ecl_Parser_DTD $dtd) {
		parent::__construct($dtd);
		$this->name = 'Ecl_Parser_Squid_Acl';
		$this->aclarray=array();
	}


	protected function decipher($str)
	{
		$values = explode(' ',$str,4);
		$aclname = trim($values[1]);
		$acltype = trim($values[2]);
		$aclstring = trim($values[3]);
		$namepointer=0;
		$typepointer=0;
		if (array_key_exists($aclname,$this->aclarray)) {
			$namepointer = $this->aclarray[$aclname];
		} else {
			$namepointer = new Ecl_Parser_Squid_AclItem($this->dtd,$aclname,$acltype);
			$this->aclarray[$aclname] = $namepointer;
		}
		$namepointer->addValue($this->stripInlineComments($aclstring));

		//$this-> = trim($values[1]);

		Ecl_Log::debug("Acl Item Added - Value array is : ");
		Ecl_Log::debug($namepointer->parray);
	}

	public function canParse($string)
	{
		return $this->isAuthParam($string);
	}

	public function isAuthParam($string)
	{
		$match = preg_match("/^acl/",$string);
		if ($match) {
			Ecl_Log::debug("$string contains a Squid Acl");
			return 1;
		}
		return 0;
	}

	public function render()
	{
		$str="## Courtesy of Ecl_Parser_Squid_Acl, distributed as part of EMS ##\n";
		foreach ($this->aclarray as $par=>$val) {
			$str .= $val->render();
			//$str .= $this->dtd->parameterterm;
		}
		$str .= "## End Render output for Ecl_Parser_Squid_Acl. Have a nice day! \n";
		return $str;
	}
}
