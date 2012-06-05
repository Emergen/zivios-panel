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
 * @version		$Id: AuthParam.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Squid
 **/

class Ecl_Parser_Squid_AuthParam extends Ecl_Parser_Element
{
	private $scheme,$parvalarray;

	public function __construct(Ecl_Parser_DTD $dtd) {
		parent::__construct($dtd);
		$this->name = 'Ecl_Parser_Squid_AuthParam';
	}


	protected function decipher($str)
	{
		$values = explode(' ',$str,4);
		$this->scheme = trim($values[1]);
		$this->parvalarray[trim($values[2])] = trim($values[3]);
		Ecl_Log::debug("Value array is : ");
		Ecl_Log::debug($this->parvalarray);
	}

	public function canParse($string)
	{
		return $this->isAuthParam($string);
	}

	public function isAuthParam($string)
	{
		$match = preg_match("/^auth_param/",$string);
		if ($match) {
			Ecl_Log::debug("$string contains a Squid Auth Param");
			return 1;
		}
		return 0;
	}

	public function render()
	{
		$str="## Courtesy of Ecl_Parser_Squid_AuthParam, distributed as part of EMS ##\n";
		foreach ($this->parvalarray as $par=>$val) {
			$str .= "auth_param ".$this->scheme." $par $val";
			$str .= $this->dtd->parameterterm;
		}
		$str .= "## End Render output for Ecl_Parser_Squid_AuthParam. Have a nice day! \n";
		return $str;
	}
}



