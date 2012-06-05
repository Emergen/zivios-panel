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
 * @deprecated
 **/

class Zivios_Parser_Parameter extends Zivios_Parser_Element
{
	public $valuearray;
	public $name;

	public function __construct(Ecl_Parser_DTD $dtd)
	{
		parent::__construct($dtd);
		$this->valuearray = array();
	}
	/** The item must self handle the entire parameter, includes peeking into
	* next line to make sure the parameter def has ended. A wrong peek would
	* make it return the peeked string */


	public function setId($name) {
		$this->name = $name;
	}
	public function setValue($value) {
		if (is_array($value))
			$this->valuearray = $value;
			else $this->valuearray[0] = $value;

	}

	public function getValue() {
		if (sizeof($this->valuearray) == 1) {
			return $this->valuearray[0];
		}
		else {
			return $this->valuearray;
		}
	}

	public function addValue($value)
	{
		if (!in_array($value,$this->valuearray)) {
			$this->valuearray[] = $value;
		}

	}
	public function reconcile($valuearray)
    {
        $removed = array_diff($this->valuearray,$valuearray);
        $added = array_diff($valuearray,$this->valuearray);
        foreach ($removed as $remove) {
            $this->removeValue($remove);
        }
        foreach ($added as $add) {
            $this->addValue($add);
        }

    }

	public function trim_value(&$value,$key)
	{
		$value = trim($value);
	}

	public function removeValue($value) {
		$keyindex = array_search($value,$this->valuearray);
		array_splice($this->valuearray,$keyindex,1);
	}


	public function parse(&$filepointer,$lastseentoken)
	{
		$str = $lastseentoken;
		Ecl_Log::debug("Parsing line : $str");
		$nvpairs = explode($this->dtd->equalsign,$str,2);
		$this->name = trim($nvpairs[0]);
        Zivios_Log::debug("NVPairs found :");
        Zivios_Log::debug($nvpairs);

		if (preg_match("/".$this->dtd->parametervsep."/",trim($nvpairs[1]))) {
            $array = explode($this->dtd->parametervsep,trim($nvpairs[1]));
            Zivios_Log::debug("did an explode!");
        } else {
            $array = array();
            $array[] = trim($nvpairs[1]);
        }

        Zivios_Log::debug("processed array is:");
        Zivios_Log::debug($array);
		$this->cleanarray($array);

		if (sizeof($array) >0) {
			$this->valuearray = $array;
			Ecl_Log::debug("adding array :");
			Ecl_Log::debug($this->valuearray);
		}


		Ecl_Log::debug("Parsing complete, name : ".$this->name);
		//

		// Okay handling the initial string is done, now peek
		$paramend=0;
		$feof=0;
		if ($this->dtd->loosetermination) {
			while (!$paramend && !feof($filepointer)) {
				$str = $this->peekLine($filepointer);
				if ($str !==  FALSE) {
					$inheritance = $this->dtd->implicitinheritance;
					$match = preg_match("/^$inheritance/",$str);
					$str = trim($str);
					if ($match) {
						Ecl_Log::debug("Inheritance found for parameter : ".$this->name." on String : $str");
						$params = explode($this->dtd->parametervsep,$str);
						$this->cleanarray($params);
						$this->valuearray=array_merge($this->valuearray,$params);
						Ecl_Log::debug("Merged Array is :");
						Ecl_Log::debug($this->valuearray);



					} else {
						Ecl_Log::debug("Parameter ended for ".$this->name." , no more lines");
						$paramend = 1;
						return $str;
					}
				}
			}
		}

		return 1;
	}

	public function cleanarray(&$params)
	{
		$lastelement = $params[sizeof($params)-1];
		if (trim($lastelement) == "") {
			array_pop($params);
			Ecl_Log::debug("element popped off");
		}
		for ($i=0;$i<sizeof($params);$i++) {
			$params[$i] = trim($params[$i]);
		}
	}
/*	private function peekLine(&$filepointer)
	{
		if (!feof($filepointer)) {
			$str = fgets($filepointer);
			return $str;
		}
		return FALSE;
	}
	*/


	public function render()
	{
		$outstring="";
		$outstring .= $this->name . $this->dtd->equalsign;
		for($i=0;$i<sizeof($this->valuearray);$i++) {
			$values = $this->valuearray[$i];
			if ($i < (sizeof($this->valuearray) - 1)) {
				$outstring .= $values.$this->dtd->parametervsep;
			}
			else {
				$outstring .= $values;
			}
		}

		$pterm = $this->dtd->parameterterm;
		$outstring .= "$pterm";
		return $outstring;
	}
}
