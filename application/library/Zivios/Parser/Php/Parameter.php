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
 **/

class Ecl_Parser_Php_Parameter extends Ecl_Parser_Element
{
	public $valuearray;
	public $value;
	private $multivalued;
	//private $parsed;

	public function __construct(Ecl_Parser_DTD $dtd) {
		parent::__construct($dtd);
		//$this->parsed=0;
		$this->valuearray = array();
		$this->value = null;
		$this->multivalued=-1;
	}


	public function getName()
	{
		return $this->name;
	}

	protected function decipher($str)
	{
		$str = trim($str);
		$values = explode('=',$str,2);

		$name = trim($values[0]);
		$value = trim($values[1]);
		// Check whether there is a DOT
		$match = preg_match('/\./',$name);
		if ($match) {
			Ecl_Log::debug("Value $name contains a sub value");
			$this->multivalued = 1;
			$namearray = explode(".",$name);
			$famname = $namearray[0];
			$this->name = $namearray[0];

			$subparam = $namearray[1];
			$this->valuearray[$subparam] = $value;
			Ecl_Log::debug("Family $famname subproperty $subparam has value $value");
		} else {
			$this->multivalued = 0;
			$this->value = $value;
			$this->name = $name;
			Ecl_Log::debug("Single value for $name is $value");
		}
	}


	public function canParse($string)
	{
		$match = preg_match("/=/",trim($string));
		if ($match) {
			if ($this->name == null) {
				Ecl_Log::debug("$string contains a Php Config parameter");
				return 1;
			}
			else {
				$namearray = explode(".",$string);
				if (trim($namearray[0]) == $this->name) {
					Ecl_Log::debug("line $string matches family name ".$this->name);
					return 1;
				} else {
					return 0;
				}

			}
		}
		return 0;
	}
	public function setValue($value,$sub=null) {
		if (($sub == null) && !$this->multivalued) {
			$this->value = $value;

		} else {
			$this->valuearray[$sub] = $value;
			Ecl_Log::debug("Setting ".$this->name." sub $sub value to $value");
		}
	}

	public function getValue($name="") {
		if ($name == "") {
			if (!$this->multivalued) {
				return $this->value;
			} else {
				return $this->valuearray;
			}
		} else {
			return $this->valuearray[$name];
		}
	}




	public function render()
	{
		//$str="## Courtesy of Ecl_Parser_Php_Parameter, distributed as part of EMS ##\n";
		$str = "";
		if ($this->multivalued) {
			foreach ($this->valuearray as $subprop=>$subvalue) {
				$str .= $this->name.".$subprop = $subvalue \n";
			}
		}
		else {
			$str = $this->name."=".$this->value."\n";
		}
		//$str .= "## End Render output for Ecl_Parser_Cyrus_Parameter. Have a nice day! \n";
		return $str;
	}
}
