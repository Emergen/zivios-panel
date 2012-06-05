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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: Parameter.php 1075 2008-09-10 11:54:14Z fkhan $
 * @subpackage  Core
 **/

class Zivios_Parameter
{
	public  $id, $dispname, $type, $options, $autohandle, $listener, $validate_log, $prepared;
	private $dirty, $value, $newvalue, $disabled, $defaultvalue, $validators, $invalid, $rollbackvalue,
			$wasdirty, $ignoredirty, $multivalued, $multiaddedarray, $multiremovearray, $mustrefresh,
			$allowoverwrite, $hasread,$haswrite,$pm;

	const TYPE_TEXT = 1;
	const TYPE_SELECT = 2;
	const TYPE_MULTISELECT = 3;
	const CHANGE_ADDED = 1;
	const CHANGE_UPDATED = 2;
	const CHANGE_MULTIVALUEDADD = 5;
	const CHANGE_MULTIVALUEDREMOVE = 6;
	const CHANGE_MULTIVALUEDADDREMOVE = 7;

	public function __construct($id,$value,$hasread=null,$haswrite=null)
	{
		$this->invalid=0;

		$this->multivalued=0;
		$this->mustrefresh=0;
		$this->multiaddedarray= array();
		$this->multiremovearray = array();
		$this->id = $id;
        $this->hasread = $hasread;
        $this->haswrite = $haswrite;
        $this->value = $value;

		if (is_array($value) && (sizeof($value) > 1)) {
			$this->multivalued = 1;
		}

        $this->rollbackvalue = $this->mycopy($this->value);
		$this->listener = array();
		$this->ignoredirty=0;
		$this->prepared = false;

        if (is_array($value)) {
            $value = print_r($value,1);
        }

        $config = Zend_Registry::get('ldapConfig');
        $this->allowoverwrite = $config->allowparamovrwrite;
        Zivios_Log::debug("Initialized Parameter: " . $id . " with value: " . $value .
        	" hasread: " . $hasread ." haswrite: " . $haswrite . " allowoverwrite: ".
        	$this->allowoverwrite);
	}

	public function isMultiValued()
	{
		return $this->multivalued;
	}

	public function getMultiValuesAdded()
	{
		return $this->multiaddedarray;
	}

	public function getMultiValuesRemoved()
	{
		return $this->multiremovearray;
	}

	private function mycopy($var)
	{
		return $var;
	}

	public function addValidator($validator, $msg=array())
	{
		if (is_array($msg) && !empty($msg)) {
			foreach ($msg as $context => $message) {
				Zivios_Log::debug("Setting validate message: " . $message . " for context: " . $context);
				$validator->setMessage($message, $context);
			}
		}
		$this->validators[] = $validator;
	}

	private function validate($val)
	{
		if (!empty($this->validators)) {
			foreach ($this->validators as $validator) {
				if (!is_array($val)) {
				 	if (!$validator->isValid($val)) {
						$this->invalid=1;
						$this->validate_log = $validator->getMessages();
						return $this->validate_log[0];
					}
				} else {
					foreach ($val as $valToSet) {
					 	if (!$validator->isValid($valToSet)) {
							$this->invalid=1;
							$this->validate_log = $validator->getMessages();
							return $this->validate_log[0];
						}
					}
				}
			}
		}

		return 0;
	}

    public function forceReplace()
    {
        $this->multivalued=0;
    }

	public function getId()
	{
		return $this->id;
	}

    public function hasRead()
    {
        return $this->hasread;
    }

    public function hasWrite()
    {
        return $this->haswrite;
    }

    private function checkWrite()
    {
        if (!$this->haswrite)
        	throw new Zivios_Exception("No Write Access to Parameter ID: ".$this->id);

        if ($this->prepared)
        	throw new Zivios_Exception("Cannot write to parameter id: ".$this->id." as its already prepared.");
    }

	public function nullify()
    {
        $this->checkWrite();
        if ($this->value != null) {
            $this->newvalue = null;
            $this->dirty =1;
            $this->wasdirty =1;
        }
    }

	public function setValue($val,$force=0,$skipdirty=0)
	{

        if (!is_array($val))
        	$val = "$val";

		if ($force)
			Zivios_Log::info("WARNING: Parameter force update enabled for ".$this->getId());

		$name = $this->id;

		if ($this->disabled)
			throw new Zivios_Exception("Attempt to edit disabled Parameter $name");

		$this->validate($val);
		if ($this->validate_log != "") {
            Zivios_Log::info("Parameter :".$this->getId()." Validation failed");
            Zivios_Log::debug($this->validate_log);
			return $this->validate_log[0];
		}

		if ($skipdirty || !($this->dirty && !$this->allowoverwrite)) {
			if ((($this->value == null) && ($val != null)) || ($this->value != $val) || $force) {
                $this->checkWrite();
				$this->newvalue = $val;
				$this->dirty = 1;
				$this->wasdirty = 1;
			}
		} else {
			if ($this->ignoredirty)
				Zivios_Log::info("Ignoring multiple dirty writes in rollback mode");
			else
				throw new Zivios_Exception("Value already dirty for $name = $this->newvalue");
		}
	}

	public function addValue($val)
	{
        $this->checkWrite();
		/** add into multivalued array to be sure! */

		$this->multivalued = 1;
		$this->multiaddedarray[] = $val;

		$this->validate($val);
		if ($this->validate_log != "") {
			return $this->validate_log[0];
		}

		/**
		 * Need to hook into validation here as well.
		 */
		// Go lower case!
		//$val = $this->makeLowerCase($val);
		$value = array();
		if (!is_array($val)) {
				//make input into an array for Merging
				$value[] = $val;
			} else $value = $val;

		if ($this->newvalue == null) {
			$this->setValue($val);
		} else {
			if (is_array($this->newvalue)) {
				$value = array_merge($this->newvalue,$value);
				$this->newvalue = $value;
			} else {
				array_push($value,$this->newvalue);
				$this->newvalue = $value;
			}
			$this->dirty = 1;
			$this->wasdirty = 1;

		}

		if ($this->getOldValue() != null) {
			if (is_array($this->getOldValue())) {
				$value = array_merge($value,$this->getOldValue());
			} else {
				array_push($value,$this->getOldValue());
			}

			$this->newvalue = array_unique($value);
			//Zivios_Log::debug("setting newvalue as $newvalarr");
		}
	}

	/**
	 * This is present for LDAP ACL Management compatibility.
	 * To remove an Acl we simply need to send a delete with olcAccess = [line number]
	 *
	 * This function will eventually result in a ldap_mod_del being called with the
	 * value forced here, regardless if it has this value in its array or not
	 *
	 * @param integer $linenum
	 */
	public function removeMultiForce($linenum)
	{
        $this->checkWrite();
		$this->multiremovearray[] = $linenum;
		Zivios_Log::debug("Forced remove called, adding $linenum to delete array");
		$this->dirty =1;
	}

	public function removeValue($value)
	{
        $this->checkWrite();
        $bt = debug_backtrace();
        $class = $bt[2]['class'];
		$ln = $bt[1]['line'];
        $value = trim($value);

		if ($this->multivalued) {
			$arr = Zivios_Util::makeLowerCase($this->getValue());
			$value = Zivios_Util::makeLowerCase($value);
            $printr = print_r($arr,1);
			Zivios_Log::debug($class ." (" . $ln .") :::: Remove Value called on: ".$this->id.". Removing: ".
				$value . " from array: " . $printr);
			$index = array_search($value,$arr);
			if ($index !== FALSE) {
				$topportion = array_splice($arr,$index,1);
				$this->setValue($arr,0,1);
				$this->multiremovearray[] = $value;
				$this->dirty =1;
				$this->mustrefresh=1;
				//Zivios_Log::debug("Scheduled for multivalued remove: $value");
			} else
				Zivios_Log::info("no value mataching $value found to remove");
		}
		else {
			$this->setValue(null);
			Zivios_Log::debug("$class ($ln) :::: Scheduled for single valued removal : $value");
		}
	}

	public function isAuto()
	{
		return $this->autohandle;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getChange()
	{
		if ($this->dirty) {
			if ($this->getOldValue() == null) {
				return self::CHANGE_ADDED;
			} else if  ($this->multivalued && (sizeof($this->multiaddedarray) > 0) &&
				(sizeof($this->multiremovearray) > 0)) {
				return self::CHANGE_MULTIVALUEDADDREMOVE;
			} else if  ($this->multivalued && (sizeof($this->multiaddedarray) > 0)) {
				return self::CHANGE_MULTIVALUEDADD;
			} else if ($this->multivalued && (sizeof($this->multiremovearray) > 0)) {
				return self::CHANGE_MULTIVALUEDREMOVE;
			} else
				return self::CHANGE_UPDATED;
		}
		return $this->dirty;
	}

	/**Return the value of the current parameter
	 * *
	 *
	 * @param int $forcearray If set to 1 (default 0), the output will always be an array- useful when dealing with
	 *			forms and multivalued fields
	 * @return unknown
	 */
	public function getValue($forcearray=0)
	{
		if (!$forcearray) {
			if ($this->dirty)
				return $this->newvalue;
			else
				return $this->value;
		} else {
			$retarray = array();
			if ($this->dirty) {
				if (is_array($this->newvalue))
					return $this->newvalue;
				else {
					$retarray[] = $this->newvalue;
					return $retarray;
				}
			}
			else {
				if (is_array($this->value))
					return $this->value;
				else {
                    if ($this->value == null || $this->value == "") 
                        return $retarray;
                    else {
                        $retarray[] = $this->value;
                        return $retarray;
                    }
				}
			}
		}
	}

	public function disable()
	{
		$this->dislabed = 1;
	}

    public function setPrepared()
    {
        $this->prepared = true;
    }

	public function flush()
	{
        if ($this->dirty) {
            $this->value = $this->newvalue;
            $this->newvalue=null;
            $this->multiaddedarray = array();
            $this->multiremovearray = array();
           // $this->rollbackvalue = $this->value;
            $this->dirty = 0;
            $this->prepared = false;
        }
	}

	public function toDeleteRollBackMode()
	{
		/**
		 * delete rollback mode must pick parameters that were added, set their OLDvalues to null
		 * and new values AS Is and dirty to 1 to simulate a new ADD
		 */
        $this->checkWrite();
		$this->ignoredirty=1;
		$this->newvalue = $this->value;
		$this->value = null;
		$this->dirty=1;
		Zivios_Log::debug("Delete Rollback mode for parameter: " . $this->id .
			" -- Setting new value to: ".$this->newvalue." and old value to: ".$this->value);
	}

	public function toUpdateRollBackMode()
	{
		/**
		 * this function reverses the changes that would HAVE been made earlier, putting old values as
		 * new values to simulate a update
		 */



		if (!$this->wasdirty) {
			Zivios_Log::debug("Skipping rollback mode for parameter ".$this->id);
		}
		else {
            $this->ignoredirty=1;
            //$this->value = $this->newvalue;
            $this->newvalue = $this->rollbackvalue;
            $this->dirty=1;
            $this->prepared=0;
            $this->checkWrite();
            if ($this->multivalued) { 
           
                //Correcting the multiaddremove arrays
                $this->multiaddedarray = array_values(array_diff($this->newvalue,$this->value));
                $this->multiremovearray = array_values(array_diff($this->value,$this->newvalue));
                 Zivios_Log::debug("Update Rollback mode for parameter: " .$this->id .
                    " -- Setting new value to array  and old value to array : ");
                Zivios_Log::debug($this->newvalue);
                Zivios_Log::debug($this->value);
                
                Zivios_Log::debug("Setting Multiadd and multiremove arrays according for rollback : ".
                    " addarray :: remove array");
                Zivios_Log::debug($this->multiaddedarray);
                Zivios_Log::debug($this->multiremovearray);
            } else {
                Zivios_Log::debug("Update Rollback mode for parameter: " . $this->id .
                    " -- Setting new value to: ".$this->newvalue." and old value to: ".$this->value);
            }
		}
	}

	public function getOldValue()
	{
		return $this->value;
	}

	public function hasValidValue()
	{
		return !($this->invalid);
	}
}
