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
 * @version		$Id: Parameter.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Cyrus
 **/

class Ecl_Parser_Cyrus_Parameter extends Ecl_Parser_Element
{
	public $command;
	private $parsed;

	public function __construct(Ecl_Parser_DTD $dtd) {
		parent::__construct($dtd);
		$this->parsed=0;
	}


	public function getName()
	{
		return $this->name;
	}

	protected function decipher($str)
	{
		$str = trim($str);
		$values = explode('cmd=',$str,2);

		$this->name = trim($values[0]);
		$this->command = trim($values[1]);
		Ecl_Log::debug("value for ".$this->name." is ".$this->command);
		//$this->parsed = 1;
		//Ecl_Log::debug($this->parvalarray);

	}

	public function canParse($string)
	{
		$match = preg_match("/^\w+\s+cmd/",trim($string));
		if ($match) {
			Ecl_Log::debug("$string contains a Cyrus parameter");
			return 1;
		}
		return 0;
	}

	public function render()
	{
		//$str="## Courtesy of Ecl_Parser_Cyrus_Parameter, distributed as part of EMS ##\n";
		$str = $this->name."\t cmd=".$this->command."\n";
		//$str .= "## End Render output for Ecl_Parser_Cyrus_Parameter. Have a nice day! \n";
		return $str;
	}
}

