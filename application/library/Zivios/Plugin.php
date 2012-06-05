<?php
/**
 * Copyright (c) 2008-2010 Zivios, LLC.
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
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @subpackage  Core
 **/

abstract class Zivios_Plugin
{
    protected $_pmobj, $ldapConfig,$_regexLib;

    public function __construct()
    {
        $this->ldapConfig = Zend_Registry::get("ldapConfig");
    }

    /**
     * Method should be declared by ALL plugins and return
     * an array of required ATTRS by the plugin
     */
    public function init(EMSPluginManager $pm)
    {
        $this->_pmobj = $pm;
        Zivios_Log::debug("plugin " . get_class($this) . " instantiated for dn: " . $pm->getdn());
    }

    public function getAttrs() {
        $attr = array();
        $attr[] = 'objectclass';
        return $attr;
    }
    
    public function getNameSpace() {
        return get_class($this);
    }

    public function getAcls()
    {
        return array();
    }

    public function add(Zivios_Transaction_Group $group,$description=null)
    {
    	if ($description == null)
    		$description = "Plugin " . get_class($this) . " added to dn :::" . $this->_pmobj->getdn();

        Zivios_Log::debug("Plugin ADD called for " . get_class($this));

        $this->_pmobj->update($group,$description,get_class($this));
        $titem = $group->newTransactionItem($description . "::Post Addition");
		$titem->addObject('emsplugin',$this);
		$titem->addCommitLine('$this->emsplugin->postAdd();');
		$titem->commit();

        return $group;
    }

    /**
     * Override this function to get postAdd functionality. This function will be executed
     * together with a addPlugin call after the transaction is processed
     *
     */
    public function postAdd()
    {
    }

    public function update(Zivios_Transaction_Group $group,$description=null)
    {
        Zivios_Log::debug("Plugin update called for " . get_class($this));
        if ($description == null)
    		$description = "Updating Plugin ".get_class($this)." on dn:::".$this->_pmobj->getdn();

        $this->_pmobj->update($group,$description,get_class($this));
        $titem = $group->newTransactionItem($description . " ::Post Update");
		$titem->addObject('emsplugin',$this);
		$titem->addCommitLine('$this->emsplugin->postUpdate();');
		$titem->commit();
        return $group;
    }

    /**
     * Override to gain postupdate capability. This function will be executed
     * together with a update call after the transaction is processed. Note that
     * update calls on any dn trigger update calls on ALL plugins automatically
     * *
     *
     */
    public function postUpdate()
    {
    }

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        Zivios_Log::debug("Plugin Delete called for " . get_class($this));
        if ($description == null)
    		$description = "Deleting plugin ".get_class($this)." on dn:::".$this->_pmobj->getdn();

        //$this->update($tgroup,$description,get_class($this));
        $titem = $tgroup->newTransactionItem($description . " :: Post Delete");
		$titem->addObject('emsplugin',$this);
		$titem->addCommitLine('$this->emsplugin->postDelete();');
		$titem->commit();

        return $tgroup;
    }

    /**
     * OVerride to gain postDelete capability. This function will be executed
     * together with a delete call or a removePlugin call after the transaction is processed. Note that
     * delete calls on any dn trigger delete calls on ALL plugins automatically
     * *
     *
     */
    public function postDelete()
    {}

    public function getTransaction()
    {
        return $this->_pmobj->getTransaction();
    }

    public function getProperty($name,$forcearray=0)
    {
        return $this->_pmobj->getProperty($name,$forcearray,get_class($this));
    }

    public function setProperty($name,$value)
    {
        return $this->_pmobj->setProperty($name,$value,get_class($this));
    }

    public function removeProperty($name)
    {
        return $this->_pmobj->removeProperty($name,get_class($this));
    }

    public function getParameter($name)
    {
        return $this->_pmobj->getParameter($name,get_class($this));
    }

    public function addObjectClass($objclass)
    {
        return $this->_pmobj->addObjectClass($objclass,get_class($this));
    }

    public function removeObjectClass($objclass)
    {
        return $this->_pmobj->removeObjectClass($objclass,get_class($this));
    }

    public function removePropertyItem($name,$value)
    {
        return $this->_pmobj->removePropertyItem($name,$value,get_class($this));
    }

    public function addPropertyItem($name,$value)
    {
        return $this->_pmobj->addPropertyItem($name,$value,get_class($this));
    }

    public function wakeup()
    {
    	/**
    	 * Override this function to gain WAKUP functionality. Wakeup is called whenever this object is
    	 * woken up after being serialized
    	 */
    }

    /**
     * Dynamically generate a transaction item for the given function.
     * if you wish to cause delayed execution of a method call postUpdate() you
     * only need to call _postUpdate($transaction_group,$description,$args)
     *
     * The First two parameters MUST be a transaction handler and a description, followed
     * by an arbitrary number of args
     *
     * @param unknown_type $method - name of the function to call
     * @param unknown_type $args
     * @return unknown
     */
    function __call($method,$args)
    {
        if (preg_match("/^_/",$method)) {
            if (function_exists($method)) {
                return call_user_func_array($method,$args);
            }
            /**
             * Dynamic transaction adding routing
             */
            $method = ltrim($method,"_");

            Zivios_Log::debug("Dynamically generating transaction item for method " . $method);

            /**
             * Check if the first two parameters are correct!
             */
            $tgroup = $args[0];
            $description = $args[1];

            if (!($tgroup instanceof Zivios_Transaction_Group))
                    throw new Zivios_Exception('First argument to _ functions MUST be a Transaction Group Object!');

            if ($description == null || $description == "") {
                throw new Zivios_Exception("A Description MUST be provided for the transaction being created as the
                                        second argument");
            } else if (!is_string($description))
                throw new Zivios_Exception("The second argument MUST be a string description of the transaction");

            $titem = $tgroup->newTransactionItem($description);

            /**
             * Remove the first two elements from the array!
             */

            array_splice($args,0,2);
            $argnum=1;
            $thisargs = array();
            foreach ($args as $arg) {
            	if ($arg == "__TITEMID__") {
            		$thisargs[] = $titem->getId();
            	} else {
	                $titem->addObject("arg" . $argnum,$arg);

	                /**
	                * add the $this-> string to the beginning of ALL args! easier to implode then
	                */
	                $thisargs[] = '$this->arg'.$argnum;
	                $argnum++;
            	}

            }
            $titem->addObject("ownobject",$this);
            $arglist = implode(",",$thisargs);
            $commitline = '$this->ownobject->' . $method . '(' . $arglist . ');';

            Zivios_Log::debug("Magic transaction function generated commitline: " . $commitline);

            $titem->addCommitLine($commitline);
            $titem->commit();

            return $tgroup;
        } else {
            if (function_exists($method))
                return call_user_func_array($method,$args);
            else
                throw new Zivios_Exception("Function " . $method . " not defined in " . get_class($this));
        }
    }
    
     protected function _getRegexLibrary()
    {
        if (null === $this->_regexLib) {
            $this->_regexLib = Zivios_Regex::loadLibrary();
        }

        return $this->_regexLib;
    }
    
    /**
     * Helper function to update EMS type objects from validated
     * form data. Do note that the form field "id" must match the
     * property being updated. An optional array 'ignore' can be
     * specified to ignore certain form fields (like dns!).
     *
     * @params Object $form
     * @params Array $vals, $ignoreFields
     * @return void
     */
    public function updateViaForm(Zend_Form $form, $vals, $ignoreFields=array())
    {
        if (!is_array($ignoreFields)) {
            throw new Zivios_Exception('Incorrect parameter type detected for ignoreFields');
        }

        if ($form->isValid($vals)) {
            foreach ($vals as $key => $val) {
                if (!in_array($key, $ignoreFields)) {
                    $this->setProperty($key,$val);
                }
            }
        } else {
            Zivios_Log::error("Form errors follow: ");
            Zivios_Log::error($form->getErrors());
            throw new Zivios_Error("Invalid form submitted. Check logs for errors");
        }
    }
    
    public function getdn()
    {
        return $this->_pmobj->getdn();
    }
    
}
