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
 * @version		$Id: HttpAccess.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Squid
 **/

class Ecl_Parser_Squid_HttpAccess extends Ecl_Parser_Element
{
	private $httpaccessitemarray;

	public function __construct(Ecl_Parser_DTD $dtd)
	{
		parent::__construct($dtd);
		$this->httpaccessitemarray = array();
	}

	protected function decipher($str)
	{
		Ecl_Log::debug("Parsing HttpAccess value");
		$exploded = explode(' ',$str,3);
		$rule = trim($exploded[1]);
		$acls = trim($exploded[2]);
		$htaccess = new Ecl_Parser_Squid_HttpAccessItem($this->dtd,$rule);
		$aclitems = explode(' ',$acls);
		foreach ($aclitems as $item) {
			$htaccess->addValue($item);
		}
		$this->httpaccessitemarray[] = $htaccess;
		Ecl_Log::debug("Parsing complete, array is now at ");
		Ecl_Log::debug($this->httpaccessitemarray);
	}

	public function render()
	{
		$str="## Courtesy of Ecl_Parser_Squid_HttpAccess, distributed as part of EMS ##\n";
		foreach ($this->httpaccessitemarray as $par=>$val) {
			$str .= $val->render();
			//$str .= $this->dtd->parameterterm;
		}

		$str .= "## End Render output for Ecl_Parser_Squid_HttpAccess. Have a nice day! \n";
		return $str;
	}



}


