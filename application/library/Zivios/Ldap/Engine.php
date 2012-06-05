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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 * @subpackage  Core
 **/

class Zivios_Ldap_Engine
{
    // Ldap Centric Info
    public    $ldapConfig, $uid, $dn,$ppgrace,$ppexpire,$pperror,$ppemsg,$pwexpired;
    protected $conn, $resource;
    private   $eventlisteners, $params, $dirty_params;

    // Zivios Centric Info
    public    $attrs, $makedn, $isnew, $transmode, $cachetransid, $nomodel;
    protected $_addarray, $_delarray, $_modarray;
    private   $emsaclarray, $iacllist;
    private   $aclloaded;
    private   $newparent;
    
    private $unproc_emsaclarray,$unproc_emsacllist;
    

    protected $_regexLib = null;
    
    public function setNoModel()
    {
    	$this->nomodel =1;
    }

    public function isNoModel()
    {
    	return $this->nomodel;
    }
    public function __construct($dn=null,$attrs=null,$acllist=null)
    {
        $this->ldapConfig = Zend_Registry::get('ldapConfig');
        $this->_addarray = array();
        $this->_delarray = array();
        $this->_modarray = array();
        $this->eventlisteners = array();
        $this->resource = 0;
        $this->params = array();
        $this->dirty_params = array();
        $this->nomodel = 0;
        $this->cnconfigbind = 0;
        $this->aclloaded = false;

        if ($attrs == null)
            $attrs = array();

        $attrs[] = "dn";
        $attrs[] = "objectclass";
        $attrs[] = "emsmodelclass";
        $attrs[] = "cn";
        $attrs[] = "emstype";
        $attrs[] = "emsmodulename";
        $attrs[] = "module";
        $attrs[] = "emsdependson";

        $attrs =  array_unique($attrs);
        $attrs = array_values($attrs);

        if ($acllist == null)
            $acllist = array();

        $acllist[] = 'CORE_LOAD_DN';
        $acllist[] = 'CORE';
        
        $this->acllist = $acllist;

        $this->attrs = $attrs;
        $this->dn = strtolower($dn);
        Zivios_Log::debug("Constructed with Dn: " . $dn ." and class type: " . get_class($this) .
            " and attrs: " . implode(",",$attrs));

        if ($dn == null)
            $this->isnew = true;
        else {
            $this->isnew = false;
            if (preg_match("/cn=config$/",$dn)) {
                Zivios_Log::debug("Setting CnConfigMode for DN : ".$dn);
                $this->cnconfigbind = 1;
            }
        }
        //$this->reconnect();
    }
    
    public function __destruct()
    {
        $this->close();
        /*foreach ($this->eventlisteners as $key => $listenerobj) {
            if (method_exists($listenerobj,'__destruct')) {
                $listenerobj->__destruct();
            }
        }*/
        unset($this->eventlisteners);
        
    }


    public function addEventListener($eventname,$listener)
    {
        Zivios_Log::debug("Adding eventlistener on: " . $eventname . " for class: " . get_class($listener) .
            " on dn: " . $this->getdn());

        if (array_key_exists($eventname,$this->eventlisteners))
            $listenerobj = $this->eventlisteners[$eventname];
        else {
            $listenerobj = new StdClass();
            $listenerobj->listeners = array();
            $this->eventlisteners[$eventname] = $listenerobj;
        }

        $listenerobj->listeners[] = $listener;
    }
    
    

    public function fireEvent($eventname,Zivios_Transaction_Group $tgroup)
    {
        if (array_key_exists($eventname,$this->eventlisteners)) {
            $listenerobj = $this->eventlisteners[$eventname];
            foreach ($listenerobj->listeners as $listener) {
                Zivios_Log::debug("Firing Event: $eventname on listener :".get_class($listener));
                $listener->eventAction($eventname,$tgroup);
            }
        } else
            Zivios_Log::info("No event listeners for $eventname on dn :".$this->getdn());
    }

    protected function setMode()
    {
        $_userSession = new Zend_Session_Namespace("userSession");
        if (isset($_userSession->transinprocess) && $_userSession->transinprocess > 0) {
            $this->transmode = 1;
            $this->cachetransid = $_userSession->transinprocess;
            Zivios_Log::info("Cache operative in Transaction mode for id :".$_userSession->transinprocess);
        }
    }

    
    public function inTransaction()
    {
        $this->setMode();
        return ($this->transmode == 1);
    }

    public function getCacheTransId()
    {
        $this->setMode();
        return $this->cachetransid;
    }

    public function sp_query($dn,$property)
    {
        $this->reconnect();
        $filter = "(objectclass=*)";
        $proparray = array();
        $proparray[] = $property;
        $entries = $this->search($filter,$proparray,$dn,"BASE");
        //$this->close();

        if ($entries['count'] < 1) {
            Zivios_Log::error("Special Query failed, DN not found");
            return null;
        }

        if ($entries[0]['count'] < 1) {
            Zivios_Log::info("Special query return no results for attrib : $property");
            return null;
        }

        $retval =  $entries[0][$property];

        if ($retval['count'] == 1)
            return $retval[0];
        else {
            return array_splice($retval,1);
        }
    }

    /**
     * This is a special function as its called during the INIT process
     * we need to know what plugins an object has to intialize its ATTRS
     * Intresting problem indeed
     */
    protected static function getPlugins($dn)
    {
        $obj = new Zivios_Ldap_Engine($dn);
        return $obj->sp_query($dn,'emsplugins');
    }

    protected function getEmsPerms()
    {
         return $this->sp_query($this->dn,'emspermission');
    }

    protected function getLdapAci()
    {
        return $this->sp_query($this->dn,'openldapaci');
    }

    protected function getModelClass()
    {
        return $this->sp_query($this->dn,'emsmodelclass');
    }

    protected function getModifyTime()
    {
        return $this->sp_query($this->dn,'modifytimestamp');
    }

    public function exists()
    {
        $filter = "(objectclass=*)";
        $entries = $this->search($filter,array('dn'), $this->dn, "BASE");
        return ($entries['count'] > 0);
    }

    public function init()
    {
        $dn = $this->dn;
        $attrs = $this->attrs;
        $this->dn = $dn;

        if ($attrs == null)
            throw new Zivios_Ldap_Exception("Cannot instantiate Ldap_Engine with zero attrs");

        $start = microtime(true);
        $this->addAttrs($attrs,1);
        if (!$this->isNoModel() && !$this->cnconfigbind) {
	        $this->addEmsAcls($this->acllist,1);

	        /*
	        if (!$this->isNew())
	            $this->requireAcl('CORE_LOAD_DN');
	        */
        }
        
        $stop = microtime(true);
        Zivios_Log::info("LdapEngine init took :".($stop-$start)."s");

    }

    
    protected function addEmsAcls($acls,$skipmerge=0)
    {
	    $this->acllist = array_merge($this->acllist,$acls);
	    $this->acllist = array_values($this->acllist);
    }
    
    /*
    protected function addEmsAcls($acls,$skipmerge=0)
    {
        if (!$skipmerge) {
            $newacls = array_diff($acls,$this->acllist);
            $this->acllist = array_merge($this->acllist,$newacls);
            $this->acllist = array_values($this->acllist);
        } else
            $newacls = $acls;

        Zivios_Log::debug("Added ACLS: ".implode(",",$newacls));

        if ($this instanceof EMSSecurityObject || $this->cnconfigbind) {
            Zivios_Log::debug("Skipping ACL loading for EMSSecurityObject at dn :$dn");
            $aclreturn = array();
        } else {
            $emssec = new EMSSecurityObject($this->dn);
            $emssec->init();
            $aclreturn = $emssec->getEmsAclArray($newacls);
            $emssec->__destruct();
        }
        $this->emsaclarray = $aclreturn;
    }

    */
    
    public function requireAcl($aclname)
    {
        if ($this->isNew() || $this->isNoModel() || $this->isExpired())
            return true;   // This is WRONG, but necessary at the moment

		if ($this->emsaclarray == null) {
			Zivios_Log::debug("EMS ACL Not Loaded for dn ".$this->getdn()."... Loading the following acls now :".$this->acllist);
			//First time use, Load EMS ACLS
			if ($this instanceof EMSSecurityObject || $this->cnconfigbind) {
            Zivios_Log::debug("Skipping ACL loading for EMSSecurityObject at dn :$dn");
            $aclreturn = array();
			} else {
				$emssec = new EMSSecurityObject($this->dn);
				$emssec->init();
				$aclreturn = $emssec->getEmsAclArray($this->acllist);
				$emssec->__destruct();
			}
			$this->emsaclarray = $aclreturn;
		}
		Zivios_Log::debug($this->emsaclarray);

        if (array_key_exists($aclname,$this->emsaclarray)) {
            $access = $this->emsaclarray[$aclname];
        } else {
            $access = Zivios_Acl::ACCESS_NOANSWER;
        }
        if ($access == Zivios_Acl::ACCESS_GRANTED)
            Zivios_Log::info("Allowed access to ACL :$aclname to dn :".$this->getdn());
        else if ($access == Zivios_Acl::ACCESS_DENIED)
            throw new Zivios_AccessException("Access Denied to ACL : $aclname for dn ".$this->getdn());
        else {
            //See what the parent says
            $aclsplit = explode("_",$aclname);
            if (sizeof($aclsplit) < 2) {
                // complete ambiguity for ACL
                Zivios_Log::error("Acl Calculation Ambiguous! Acl :$aclname has access :$access for dn :".
                    $this->getdn());
                $securityConfig = Zend_Registry::get('securityConfig');
                if ($securityConfig->strictacls == 1) {
                    throw new Zivios_AccessException("Ambiguous ACL. Access Denied to ACL : $aclname for dn " .
                        $this->getdn());
                }
            } else {
                unset($aclsplit[sizeof($aclsplit)-1]);
                Zivios_Log::debug("Unable to decide for ".$aclname." going one level up");
                //If ambiguous for CORE_USER_CANADD, search for CORE_USER
                $this->requireAcl(implode("_",$aclsplit));
            }
        }    
    }
    
    public function rename(Zivios_Transaction_Group $tgroup,$newparent,$newrdn=null)
    {
        $description = "Moving : " . $this->getdn() . " to new parent :" . $newparent;
       

        $this->_doRename($tgroup,$description,$newparent,$newrdn);
        
        Zivios_Log::debug("Rename called");
        return $tgroup;
    }
    
    public function doRename($newparent,$newrdn=null)
    {
        $this->reconnect();
        if ($newrdn == null) {
            $arr = $this->getrdnparentArray();
            $newrdn = $arr[0];
        }
            
        Zivios_Log::debug("Ldap rename function called :".$this->getdn(true).", newrdn :".$newrdn." new parent :".$newparent);
        ldap_rename($this->conn,$this->getdn(true),$newrdn,$newparent,true);
    }

    /**
     * Dynamically generate a transaction item for the given call.
     * if you wish to cause delayed execution of a method call postUpdate() you
     * only need to call _postUpdate($transaction_group,$description,$args)
     *
     * The First two parameters MUST be a transaction handler and a description, followed
     * by an arbitrary number of args
     *
     * @param unknown_type $method - name of the method to call
     * @param unknown_type $args
     * @return unknown
     */
    function __call($method,$args)
    {
        try {
        if (preg_match("/^_/",$method)) {
            if (function_exists($method)) {
                return call_user_func_array($method,$args);
            }
            // Dynamic transaction adding routing
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

            // Remove the first two elements from the array!
            array_splice($args,0,2);
            $argnum=1;
            $thisargs = array();
            foreach ($args as $arg) {
                $titem->addObject("arg" . $argnum,$arg);

                /**
                * add the $this-> string to the beginning of ALL args! easier to implode then
                */
                $thisargs[] = '$this->arg'.$argnum;
                $argnum++;

            }
            $titem->addObject("ownobject",$this);
            $arglist = implode(",",$thisargs);
            $commitline = '$this->ownobject->' . $method . '(' . $arglist . ');';

            Zivios_Log::debug("Magic transaction function generated commitline: " . $commitline);

            $titem->addCommitLine($commitline);
            $titem->commit();

            return $tgroup;
        } else {
            if (function_exists($method)) {
                return call_user_func_array($method,$args);
            } else {
                throw new Zivios_Exception("Function " . $method . " not defined in " . get_class($this));
            }
        }
        } catch (Exception $e) {
            Zivios_Log::exception($e);
        }
    }
    
    protected function addAttrs($attrs,$skipmerge=0,$namespace="CORE")
    {
    	if (!$skipmerge) {
			$newattrs = array_diff($attrs,$this->attrs);
			$this->attrs = array_merge($this->attrs,$newattrs);
			$this->attrs = array_values($this->attrs);
		}
            
        	$newattrs = $attrs;
            
			$entries = $this->loadLdapDn();

            foreach ($newattrs as $attr) {
                $haswrite = $hasread = 1;
                if (is_array($entries) && array_key_exists($attr,$entries)) {
					$value = $entries[$attr];
					if ($value['count'] == 1)
						$value = $value[0];
					else if ($value['count'] > 1)
						$value = array_slice($value,1);
					else
						$value = null;
				} else
					$value = null;
                
				$this->params[$namespace][$attr] = new Zivios_Parameter($attr,$value,$hasread,$haswrite);
        }
    }
    
    /*
    protected function addAttrs($attrs,$skipmerge=0,$namespace="CORE")
    {
        Zivios_Log::debug("Attempting to add ATTRS : ".implode(",",$attrs) . " to namespace : " .$namespace);
        if ($attrs == null)
            return;

        if (!$skipmerge) {
            $newattrs = array_diff($attrs,$this->attrs);
            $this->attrs = array_merge($this->attrs,$newattrs);
            $this->attrs = array_values($this->attrs);
        } 
            
        $newattrs = $attrs;

            Zivios_Log::debug("****Added ATTRS: ".implode(",",$newattrs)." to namespace : " . $namespace);
        if (!$this->isNoModel() && !$this->cnconfigbind) {

	        if ($this instanceof EMSSecurityObject) {
	                Zivios_Log::debug("Skipping ACL loading for EMSSecurityObject at dn :$this->dn");
	                $acls = array();
	        } else {
	            $emssec = new EMSSecurityObject($this->dn);
	            $emssec->init();
	            $acls = $emssec->getAclArray($newattrs);
	            $emssec->__destruct();
                Zivios_Log::debug("Got ACL Array for Object class : " .get_class($this). " at dn :".$this->getdn());
                Zivios_Log::debug($acls);
	        }
        } else {
            Zivios_Log::info("Skipping ACL Loading for non model DN : ".$this->dn);
        }

        $entries = $this->loadLdapDn();

        foreach ($newattrs as $attr) {
            if ($this instanceof EMSSecurityObject || $this->isNew() || $this->isNoModel() || $this->cnconfigbind) {
                $haswrite = $hasread = 1;
            } else {
                $hasread = preg_match("/\+".Zivios_Ldap_Aci::PERM_R."/",$acls[$attr]);
                $haswrite  = preg_match("/\+".Zivios_Ldap_Aci::PERM_W."/",$acls[$attr]);
            }

            if (is_array($entries) && array_key_exists($attr,$entries)) {
                $value = $entries[$attr];
                if ($value['count'] == 1)
                    $value = $value[0];
                else if ($value['count'] > 1)
                    $value = array_slice($value,1);
                else
                    $value = null;
            } else
                $value = null;
            $this->params[$namespace][$attr] = new Zivios_Parameter($attr,$value,$hasread,$haswrite);
        }
    }

    */
    
    public function getObject()
    {
        $dn = $this->dn;
        if ($this->isExpired()) 
            return $this;
        
        $modelclass = $this->getModelClass();

        if ($modelclass != null) {
            $obj = new $modelclass($dn);
            $obj->init();
            return $obj;
        } else
            throw new Zivios_Exception("No modelclass found for dn : ".$dn);
    }

    public function getProperty($name,$forcearray=0,$namespace='CORE')
    {
        $param = $this->parameterSearch($name,$namespace);
        
        if ($param) 
            return $param->getValue($forcearray);
        else {
            $bt = debug_backtrace();
            Zivios_Log::error("Namespace : ". $namespace." :: Parameter: " . $name . " not loaded by dn: " . $this->getdn() .
                " and classtype: " . get_class($this) . ". Called by: " . $bt[2]['class'] .
                " on line: " . $bt[1]['line']);
        }
    }

    public function getSecurityObject()
    {
        $emssec = new EMSSecurityObject($this->dn);
        $emssec->init();
        return $emssec;
    }
    
    public function parameterSearch($name,$first='CORE') 
    {
        //Zivios_Log::debug("Searching for $name in namespace $first)");
        if (array_key_exists ($first,$this->params)) {
            //Zivios_Log::debug("namespace $first found !!!! ");
            if (array_key_exists($name,$this->params[$first])) {
                //Zivios_Log::debug("Key found! returning");
                return $this->params[$first][$name];
            }
        } 
        
        foreach ($this->params as $namespace => $parameterarray) {
            foreach ($parameterarray as $pname => $parameter) {
            //Zivios_Log::debug("Parameter search going through namespace : $namespace , parameter : $pname");
            if ($pname == $name) 
                return $parameter;
            }
        }
        return false;
    }
    
    public function getParameter($name,$namespace='CORE')
    {
        $param = $this->parameterSearch($name,$namespace);
        
        if ($param) 
            return $param;
        else
            Zivios_Log::error("Parameter: " . $name . " not loaded" );
    }

    public function setProperty($name,$value,$namespace='CORE')
    {
        Zivios_Log::debug("Set Property called with namespace : " . $namespace . " : " . $name . "=" . $value);

        $param = $this->parameterSearch($name,$namespace);
        
        if ($param) { 
            $retval = $param->setValue($value);
            if ($retval == "")
                return 0;
            else
                return $retval;
        } else
            throw new Zivios_Exception("Parameter: " . $name . " not loaded.");
    }

    public function addPropertyItem($name,$value,$namespace='CORE')
    {
        $param = $this->getParameter($name,$namespace);
        if ($param != null)
           return $param->addValue($value);
    }

    public function removeProperty($name,$namespace='CORE')
    {
        $param = $this->getParameter($name,$namespace);
        if ($param != null)
            $param->nullify();
    }

    public function removePropertyItem($name,$value,$namespace='CORE')
    {
        Zivios_Log::debug("Removing property Item: " . $name);
        $param = $this->getParameter($name,$namespace);

        if ($param != null)
            return $param->removeValue($value);
    }

    public function addObjectClass($class,$namespace='CORE')
    {
        //Zivios_Log::debug("Add objectclass called adding: " . $class);
        $param = $this->getParameter('objectclass',$namespace);
        if ($param) {
            $param->addValue($class);
        } else 
            throw new Zivios_Exception("Critical Error, Objectclass parameter not found! Check Code!");
    }

    public function removeObjectClass($class,$namespace='CORE')
    {
        $param = $this->getParameter('objectclass',$namespace);
        $param->removeValue($class);
    }

    public static function loadDn($dn,$classname=null)
    {
        if ($dn == null) {
            $bt = debug_backtrace();
            $classcalling = $bt[2]['class'];
            $line = $bt[1]['line'];
            throw new Zivios_Exception("Attempt to load Null DN by: " . $classcalling . "::" . $line);
        }
        $obj = new Zivios_Ldap_Cache($dn);
        $obj->reconnect();

        if (!$obj->isExpired()) {
            if (!$obj->exists())
                throw new Zivios_Exception("DN " . $dn . " does not exist in Ldap");
        }
        

        if ($classname == null) {
        	Zivios_Log::debug("Doing regular object construction for dn: " . $dn);
            //$obj = new Zivios_Ldap_Engine($dn);
            return $obj->getObject();
        } else if ($classname == 'NOMODEL') {
        	Zivios_Log::debug("Doing NO MODEL Construction for dn :".$dn);
            //$obj = new Zivios_Ldap_Cache($dn);
            $obj->setNoModel();
            $obj->init();
            return $obj;
        } else {
            $obj = new $classname($dn);
            $obj->init();
            return $obj;
        }
    }

    public function isExpired()
    {
        $creds = self::getUserCreds();
        return $creds['pwexpired'];
    }
   
    private function loadLdapDn()
    {
        $dn = $this->dn;

        if ($dn != null &&  !$this->isExpired()) {
            $filter = "(objectclass=*)";
            $this->reconnect();
            $entries = $this->search($filter,$this->attrs,$dn,"BASE");
           // $this->close();

            if ($entries['count'] < 1)
                throw new Zivios_Exception("dn: $dn does not exist in LDAP");

            return $entries[0];
        } else
            return null;
    }

    protected function close()
    {
    	if (isset($this->conn)) {
        ldap_close($this->conn);
        unset($this->conn);
        }
        if (isset($this->resource))
        	unset($this->resource);
    }
    
    protected function reconnect($force=0)
    {
        if (!isset($this->conn) || $force) {
            $start = microtime(true);
            Zivios_Log::debug("Reconnecting to Ldap..... ");
            $this->connect();
         
            Zivios_Log::debug("Trying to bind..");
            if ($this->cnconfigbind) {
                $this->bindToCnConfig();
            } else {               
                Zivios_Log::debug("Rebinding to Ldap..... ");
                $usercreds = self::getUserCreds();
    
                if (isset($usercreds['auth']) && $usercreds['auth'] == 1) {
                    if (!$this->bind($usercreds['dn'],$usercreds['password']))
                       throw new Zivios_Ldap_Exception("Reconnection as user :".$usercreds['dn']." Failed! ");
                } else {
                    Zivios_Log::info("Not Auth credentials, running anonymous for dn " . $this->dn);
                    Zivios_Log::info($usercreds);
                }
            }
            $stop = microtime(true);
            Zivios_Log::info("Ldap Reconnect took :".($stop-$start)."s");
        }
    }

    public function search($filter,$attrs=null,$base=null,$scope=null,$sizelimit=null)
    {
        if ($sizelimit == null) 
            $sizelimit = $this->ldapConfig->sizelimit;
        
        if ($base == null) {
            if ($this->cnconfigbind)
                $base = 'cn=config';
            else
                $base = $this->ldapConfig->basedn;
        }

        if ($scope == null)
            $scope = "SUB";

        if ($scope == "SUB")
            $call = 'ldap_search';
        else if ($scope == "ONE")
            $call = 'ldap_list';
        else
            $call = 'ldap_read';

	if ($attrs == null) {
		$attrs = array();
	}
	if ($attrs != null && !is_array($attrs)) {
		$newattr = array();
		$newattr[] = $attrs;
		$attrs = $newattr;
	}
	Zivios_Log::debug("Attrs is :");
	Zivios_Log::debug($attrs);

        $attr_disp = implode(",",$attrs);
        Zivios_Log::debug("Executing search with filter : $filter, base $base and attrs $attr_disp sizelimit $sizelimit scope *$scope*");
        $this->reconnect();
 	//       $results = $call($this->conn, $base, $filter, $attrs, 0, $sizelimit,
        //    $this->ldapConfig->timelimit, $this->ldapConfig->deref);
        
        if (!$this->pwexpired) {
            $results = $call($this->conn,$base,$filter,$attrs);            
            Zivios_Log::debug("Search returned raw results :");
            $entries = ldap_get_entries($this->conn,$results);
            Zivios_Log::debug($entries);
            return $entries;
        } else {
            Zivios_Log::error("Password Expired, Only limited operation possible for dn :".$this->getdn());
        }
    }

    public function mod_add($data)
    {
        if (sizeof($data) > 0) {
            $this->reconnect();
            Zivios_Log::info("Attempting Ldap mod_add with dn: ".$this->dn);
            $debdata = print_r($data,1);
            Zivios_log::info("mod add data : $debdata");
            $ret = ldap_mod_add($this->conn,$this->dn,$data);

            if (!$ret)
                $this->getError();
            else
                Zivios_Log::info("Ldap mod_add successfull with dn: " . $this->dn);
        }
    }

    private function mod_del($data)
    {
        if (sizeof($data)>0) {
            $this->reconnect();
            $dataarr = print_r($data,1);
            Zivios_log::info("Attempting mod del data with dn: ".$this->dn."  data : $dataarr");
            $ret = ldap_mod_del($this->conn,$this->dn,$data);
            if (!$ret)
                $this->getError();
            else
                Zivios_Log::info("Ldap mod_del successfull with dn: " . $this->dn);
        }
    }

    private function mod_replace($data)
    {
        if (sizeof($data) > 0) {
            $this->reconnect();
            Zivios_Log::info("Attempting Ldap mod_replace with dn: ".$this->dn);
            $debdata = print_r($data,1);
            Zivios_log::info("mod replace data : $debdata");
            $ret = ldap_mod_replace($this->conn,$this->dn,$data);
            if (!$ret)
                $this->getError();
            else
                Zivios_Log::info("Ldap mod_replace successfull with dn: " . $this->dn);
        }
    }

    private function getError()
    {
        $error = ldap_error($this->conn);
        $errcode = ldap_errno($this->conn);
        throw new Zivios_Ldap_Exception("Ldap Caused Error",$error,$errcode);
    }

    public static function hasAuth()
    {
        $creds = self::getUserCreds();
        return (isset($creds['auth']) && $creds['auth'] == 1);
    }


    /**
     * Authenticate a UID
     *
     * @return boolean
     */
    public function authenticate($dn,$password)
    {
        //$engine = new Zivios_Ldap_Engine();

        if ($this->bind($dn,$password)) {
            //$engine->close();
            return true;
        }

        return false;
    }

    /**
     * Connect to a remote LDAP server.
     *
     * @return boolean
     */
    public function connectToOtherLdap($dn, $password, $host, $port=389)
    {
        if (!$this->conn = ldap_connect($host, $port)) {
            throw new Zivios_Ldap_Exception('Connection to server ' . $host . ' failed');
        }

        if (!ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, $this->ldapConfig->protocol))
            throw new Zivios_Ldap_Exception("LDAP Protocol version failed.");
        
        if ($this->bind($dn, $password)) {
            return true;
        } else {
            return false;
        }
    }
    
    public function __wakeup()
    {
        unset($this->conn);
        //Zivios_Log::debug("Force reconnect on Wakeup for dn : " . $this->dn);
        //$this->reconnect(1);
    }

    public function wakeup()
    {}

    protected function setControls()
    {
        
        $ppolicy = array( array('oid' => '1.3.6.1.4.1.42.2.27.8.5.1',
                            'iscritical' => 0
                            ) );
        ldap_set_option($this->conn, LDAP_OPT_SERVER_CONTROLS,$ppolicy);

    }
    
    private function connect()
    {
        $start = microtime(true);
        if (!$this->conn = ldap_connect($this->ldapConfig->host, $this->ldapConfig->port))
            throw new Zivios_Ldap_Exception("Connection to server ".$this->ldapConfig->host." failed");

        if (!ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, $this->ldapConfig->protocol))
            throw new Zivios_Ldap_Exception("LDAP Protocol version failed.");

        if (!ldap_set_option($this->conn, LDAP_OPT_DEREF, $this->ldapConfig->deref))
            throw new Zivios_Ldap_Exception("Could not set DEREF option.");

        $stop = microtime(true);
        Zivios_Log::info("Ldap Connect took :".($stop-$start)."s");
        return true;
    }

    /**
     * Search for a user's DN based on 'uid' as the rdn.
     *
     * @params string $uid
     * @return boolean
     * @return string $dn
     */
    public function searchDn($uid)
    {
       if (isset($uid)) {
            /**
             * Search for user ID
             */
            $filter = '(uid='.$uid.')';
            Zivios_Log::debug("LDAP Uid searching for uid = $filter");

            $return = array('dn');
            if ($this->cnconfigbind)
                $base = 'cn=config';
            else
                $base = $this->ldapConfig->basedn;
                
            $this->reconnect();
            $rst = ldap_search($this->conn, $base,$filter,$return);
            $uidInfo = ldap_get_entries($this->conn, $rst);

            if ($uidInfo["count"] > 1) {
                 // More than 1 ID returned, here we are forced to throw an exception.
                throw new Zivios_Ldap_Exception("FATAL::More than one unique ID returned.");
            } elseif ($uidInfo["count"] == 0) {
                Zivios_Log::info("Could not find specified UID in system.");
                return false;
            } else {
                $dn = $uidInfo[0]["dn"];
                return strtolower($dn);
            }
        }
    }
    
    protected function bindToCnConfig()
    {
        $admConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/zadmin.ini', 'cnconfig');
        $bindUid   = $admConfig->uid;
        $bindPass  = Zivios_Security::decrypt($admConfig->password);

        if ($this->bind('cn=config', $bindPass)) {
            $this->cnconfigbind = 1;
        } else {
            throw new Zivios_Exception('Could not bind to cn=config.');
        }
    }

    protected function oldBindToCnConfig()
    {
        $cnconfpass = $this->ldapConfig->cnconfpass;
        if ($this->bind('cn=config',$cnconfpass)) {
            Zivios_Log::debug("Successfully bound to cn=config");
            $this->cnconfigbind = 1;
        } else 
            throw new Zivios_Exception("CN Config Authentication Failed!");
    }
    
    protected function bind($dn=null,$password=null)
    {
        $start = microtime(true);
        $this->reconnect();
        
        if (isset($dn)) {
            
            $this->setControls();
            $ldapbind = ldap_bind_ext($this->conn, $dn, $password);
            $err = ldap_errno($this->conn);
            ldap_ctrl_ppolicy_resp($this->conn,$ldapbind,$expire,$grace,$error,$errormsg);
            $this->ppgrace = $grace;
            $this->ppexpire = $expire;
            if ($error != 65535) {
                $this->pperror = $error;
                $this->ppemsg = $errormsg;
            }
            
            if ($error == 2) {
                $this->pwexpired = true;
            }
            
            if ($grace > 0 ) {
                $this->pwexpired = true;
            }
            
            Zivios_Log::info("Grace :".$this->ppgrace." | Expire : ".$this->ppexpire." | error : ".$this->pperror." | Emsg: ".$this->ppemsg);

            if ($err > 0) {
                Zivios_Log::error("LDAP: Authentication Failed as: " . $dn . " Error code : ".$err);
                return false;
            } else {
                $pwdreset = $this->sp_query($dn,'pwdreset');
                if (strtolower($pwdreset) == 'true') {
                    $this->pwexpired = true;
                }
                Zivios_Log::debug("LDAP: Authentication Successful as: " . $dn);
                //fix session
                $this->setExpired();
               return true;
            }
        } else {
             if (ldap_bind($this->conn)) {
                Zivios_Log::debug("LDAP: Anonymous Auth Successful");
                return true;
             } else {
                Zivios_Log::error("LDAP: Anonymous Auth disallowed");
                return false;
             }
        }
        $stop = microtime(true);
        Zivios_Log::info("Ldap Bind took :".($stop-$start)."s");

    }

    private function smartupdate(&$array,$key,$val)
    {
        $value = array();

        if (!is_array($val))
            $value[] = $val;
        else
            $value = $val;

        if (array_key_exists($key,$array)) {
            if (is_array($array[$key]))
                $array[$key] = array_merge($array[$key],$value);
            else {
                $currvalue = $array[$key];
                array_push($value,$currvalue);
                array_unique($value);
                $array[$key] = $value;
            }
        } else
            $array[$key] = $val;

        return $array;
    }

    public function addItem($name,$value,$itemid=0)
    {
        if (!array_key_exists($itemid,$this->_addarray)) {
            Zivios_Log::debug("Creating Ldap Add Array for Group $itemid");
            Zivios_Log::debug($this->_addarray);
            $this->_addarray[$itemid] = array();
        }
        
        $this->smartupdate($this->_addarray[$itemid],$name,$value);
    }

    public function updateItem($name,$value,$itemid=0)
    {
        if (!array_key_exists($itemid,$this->_modarray)) {
            Zivios_Log::debug("Creating Ldap Mod Array for group $itemid");
            Zivios_Log::debug($this->_modarray);
            $this->_modarray[$itemid] = array();
        }
        
        $this->smartupdate($this->_modarray[$itemid],$name,$value);

    }

    public function removeItem($name,$value,$itemid=0)
    {
        if (!array_key_exists($itemid,$this->_delarray)) {
            Zivios_Log::debug("Creating Ldap Del Array for group $itemid");
            Zivios_Log::debug($this->_addarray);
            $this->_delarray[$itemid] = array();
        }
        
        $this->smartupdate($this->_delarray[$itemid],$name,$value);
    }

    public function deleteItem($name,$value,$itemid=0)
    {
        if (!array_key_exists($itemid,$this->_delarray))
            $this->_delarray[$itemid] = array();
        
        $this->smartupdate($this->_delarray[$itemid],$name,$value);
    }

    private function prepare($namespace='CORE',$itemid=0)
    {
        if ($namespace == null || trim($namespace) == "")
            $namespace = 'CORE';
        
        if (!array_key_exists ($namespace,$this->params)) {
            Zivios_Log::info("No parameters to prepare for namespace :  " . $namespace);
            return;
        }
            
            
        $paramarray = $this->params[$namespace];

        foreach ($paramarray as $param) {

            Zivios_Log::debug("Namespace: $namespace :: Iterating for: " . $param->getId() . " and getchange is: " . $param->getChange());
            Zivios_Log::debug("Current Value: ");
            Zivios_Log::debug($param->getValue());
            

            if (!$param->hasValidValue())
                throw new Zivios_Exception("Invalid value in parameter: " . $param->getId());

            if ($param->getChange() == Zivios_Parameter::CHANGE_ADDED) {
                    $this->addItem($param->getId(),$param->getValue(),$itemid);
                    $param->setPrepared();
                    $this->dirty_params[] = $param;
            } else if ($param->getChange() == Zivios_Parameter::CHANGE_UPDATED && $param->getValue() != null) {
                $this->updateItem($param->getId(),$param->getValue(),$itemid);
                $param->setPrepared();
                $this->dirty_params[] = $param;
            } else if (($param->getChange() == Zivios_Parameter::CHANGE_UPDATED) && $param->getValue() == null) {
                $this->deleteItem($param->getId(),$param->getOldValue(),$itemid);
                $param->setPrepared();
                $this->dirty_params[] = $param;
            } else if ($param->getChange() == Zivios_Parameter::CHANGE_MULTIVALUEDADD) {
                Zivios_Log::debug("Values ADDED :");
                Zivios_Log::debug($param->getMultiValuesAdded());
                $this->addItem($param->getId(),$param->getMultiValuesAdded(),$itemid);
                $param->setPrepared();
                $this->dirty_params[] = $param;
            } else if ($param->getChange() == Zivios_Parameter::CHANGE_MULTIVALUEDREMOVE ) {
                Zivios_Log::debug("Values Removed :");
                Zivios_Log::debug($param->getMultiValuesRemoved());
                $this->removeItem($param->getId(),$param->getMultiValuesRemoved(),$itemid);
                $param->setPrepared();
                $this->dirty_params[] = $param;
            } else if ($param->getChange() == Zivios_Parameter::CHANGE_MULTIVALUEDADDREMOVE ) {
                Zivios_Log::debug("Values ADDED :");
                Zivios_Log::debug($param->getMultiValuesAdded());
                Zivios_Log::debug("Values Removed :");
                Zivios_Log::debug($param->getMultiValuesRemoved());
                $this->removeItem($param->getId(),$param->getMultiValuesRemoved(),$itemid);
                $this->addItem($param->getId(),$param->getMultiValuesAdded(),$itemid);
                $param->setPrepared();
                $this->dirty_params[] = $param;
            }
            Zivios_Log::debug("End parameter debug");
        }
    }

    public function move(Zivios_Ldap_Engine $newparent,Zivios_Transaction_Group $tgroup,$description=null,$parentmove=false) 
    {
        if ($description == null)
            $description = "Moving dn :".$this->getdn()." to new parent :".$newparent->getdn();
        
        $this->newparent = $newparent;
        if (!$parentmove)
            $this->_doMove($tgroup,$description,$newparent);
        
        $this->_postMove($tgroup,$description,$newparent,$this->getdn());
    }
    
    public function postMove(Zivios_Ldap_Engine $newparent,$olddn) 
    {
        // nothing here
    }
    
    public function doMove($newparent)
    {
        $this->doRename($newparent->getdn());
    }
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $group,$description=null)
    {
        $this->requireAcl("CORE_CANADD");

        $modelclass = get_class($this);
        $this->setProperty('emsmodelclass',$modelclass);

        $this->parent = $parent;
        /**
        * Enforced lower case dn on creation. This is VERY important. cannot allow
        * lower case dn's to cause comparison mismatches
        */

        $this->makedn = strtolower($this->makeDn($parent));

        if ($description == null)
            $description = "Adding a: " . get_class($this) . " to LDAP with dn: " . $this->getdn();

        $titem = $group->newTransactionItem($description);

        $this->prepare(null,$titem->getId());
        
        $titem->addObject('ldapobject',$this);
        $titem->addObject('parent',$parent);
        $titem->addCommitLine('$this->ldapobject->postAdd($this->parent,\''.$titem->getId().'\');');
       // $titem->addCommitLine('$this->ldapobject->fireParameterEvents();');
        $titem->addRollbackLine('$this->ldapobject->rollback_add();');
        $titem->commit();

        $this->fireParameterEvents($group);
        $this->flushParams();
        return $group;
    }

    public function fireParameterEvents(Zivios_Transaction_Group $tgroup)
    {
        foreach ($this->dirty_params as $key => $param) {
            $param->flush();
            unset($this->dirty_params[$key]);
            $this->fireEvent("CORE_PCHANGE_".strtoupper($param->getId()),$tgroup);
        }

        $this->flushEvents();
    }

    public function flushLdap($itemid=0)
    {
        $this->_addarray[$itemid] = array();
        $this->_delarray[$itemid] = array();
        $this->_modarray[$itemid] = array();
    }

    public function flushEvents()
    {
        $this->flushParams();
    }

    public function flush($itemid=0)
    {
        $this->flushLdap($itemid);
        $this->flushParams();
        $this->flushEvents();
    }
    
    public function flushParams()
    {
        foreach ($this->dirty_params as $param) {
            $param->flush();
        }
        
        $this->dirty_params = array();
    }

    public function postAdd(Zivios_Ldap_Engine $parent,$itemid=0)
    {
        $this->reconnect();
        Zivios_Log::debug("TItem id is : $itemid");
        $printadd = print_r($this->_addarray,1);

        Zivios_Log::info("Adding: " . $printadd);

        $ret = ldap_add($this->conn,$this->makedn,$this->_addarray[$itemid]);


        if (!$ret)
            $this->getError();
        else {
            Zivios_Log::info("Ldap add successfull with dn:".$this->makedn);
            $this->dn = $this->makedn;
            unset($this->makedn);
        }
        
        $this->close();
        $this->flushLdap($itemid);
    }

    public function update(Zivios_Transaction_Group $group,$description=null,$namespace='CORE')
    {
       // $this->requireAcl("CORE_CANUPDATE");
        
        if ($description == null)
            $description = "Updating: ".get_class($this)."  in LDAP with dn::".$this->getdn();

        Zivios_Log::debug("Object type : ".get_class($this)." Update called for namespace : $namespace");
        $titem = $group->newTransactionItem($description);
        $this->prepare($namespace,$titem->getId());
        //$this->flushParams();
        
        
        $titem->addObject('ldapobject',$this);
        $titem->addCommitLine('$this->ldapobject->postUpdate(\''.$namespace.'\','.$titem->getId().');');
       // $titem->addCommitLine('$this->ldapobject->fireParameterEvents();');
        $titem->addRollbackLine('$this->ldapobject->rollback_update(\''.$namespace.'\','.$titem->getId().');');
        $titem->commit();
        
        $this->fireParameterEvents($group);
        return $group;
    }

    /*public function fireEventLater($eventname,Zivios_Transaction_Group $group,$description=null)
    {
        if ($description == null)
            $description = "Event: " . $eventname . " fired on " . get_class($this) . " dn: ". $this->getdn();

        $titem = $group->newTransactionItem($description);
        $titem->addObject('ldapobject',$this);
        $titem->addCommitLine('$this->ldapobject->fireEvent("'.$eventname.'");');

        return $group;
    }*/

    public function rollback_add()
    {
        return $this->postDelete();
    }

    public function rollback_update($namespace='CORE')
    {
        foreach ($this->params[$namespace] as $param) {
            $param->toUpdateRollbackMode();
        }

        return $this->postUpdate($namespace);
    }

    protected function groupPolicyCheck($paramarray)
    {
        // Check and return 1 if group policies are okay. This is
        // a generic GP check
    }

    public function isNew()
    {
        return $this->isnew;
    }

    public function delete(Zivios_Transaction_Group $group,$description=null)
    {
        $this->requireAcl("CORE_CANDELETE");

        if ($this->hasDependents()) {
            throw new Zivios_Error("Cannot delete dn ".$this->getdn()." as another object is dependent on it");  
        }
	Zivios_Log::debug("no dependents: Proceeding with Delete");
        
        if ($description == null) {
            $description = "Deleting: " . get_class($this) . " from Ldap with dn: " . $this->getdn();
        }

        Zivios_Log::info("Deleting directly :".$this->getdn());
        $titem = $group->newTransactionItem($description);
        $titem->addObject('ldapobject',$this);
        $titem->addObject('emsparent',$this->getParent());
        $titem->addCommitLine('$this->ldapobject->postDelete();');
        $titem->addRollbackLine('$this->ldapobject->rdelete($this->emsparent);');
        $titem->commit();

        return $group;
    }
    
    public function getdn()
    {
        
        $dn = $this->dn;

        if ($dn == '') {
            return $this->makedn;
        } else {
            return $dn;
        }
    }

    public function getParent()
    {
        $appConfig = Zend_Registry::get('ldapConfig');

        if ($this->cnconfigbind)
                $base = 'cn=config';
            else
                $base = $appConfig->basedn;
                
        if ($this->getdn() == $base) {
            return null;
        }

        $tokendn = explode(',',$this->getdn());
        $parentdnarray = array_slice($tokendn,1);
        $parentdn = implode(',',$parentdnarray);
        return Zivios_Ldap_Cache::loadDn($parentdn);

    }
    
    protected function getrdnparentArray()
    {
        return explode(',',$this->getdn(),2);
    }

    protected function getrdn()
    {
        return 'o';
    }


    protected function makeMoveDn($parent=null)
    {
        if ($parent == null) {
            if ($this->newparent != null)
                $parent = $this->newparent;
            else 
                throw new Zivios_Exception("Unable to make Move DN since no parent supplied and Move not called on this object");
        }
        if (isset($parent->newparent)) {
            $pdn = $parent->makeMoveDn();
        } else {
            $pdn = $parent->getdn();
        }
        
        return strtolower($this->getrdn().'='.$this->getProperty($this->getrdn()).','.$pdn);
    }

    
    protected function makeDn($parent)
    {
        return strtolower($this->getrdn().'='.$this->params['CORE'][$this->getrdn()]->getValue().','.$parent->getdn());
    }

    public function postUpdate($namespace='CORE',$itemid=0)
    {
        /**
        * We need to intelligently build a list using the add,mod and del
        * arrays for a successful ldap mod_add operation.
        * Note that update and add will function differently with the
        * _addarray and _modarray when called. This is intentional
        * You must use the functions properly. With great power comes
        * great responsibility
        */

        /**
        * It is assumed that the user object has correctly filled
        * the arrays. using add item means the user wants the items
        * ADDED. This applies even to multi valued arrays. To add
        * a new multi valued array item in ldap- the user object
        * MUST use additem function. to modify existing entry, use
        * the mod item function
        */
        Zivios_Log::debug("Post Update called for namespace ::  ".$namespace);
        //$this->prepare($namespace);
        $this->reconnect();
        $this->mod_del($this->_delarray[$itemid],$this->dn);
        $this->mod_add($this->_addarray[$itemid],$this->dn);
        $this->mod_replace($this->_modarray[$itemid],$this->dn);
        
        $this->close();
        $this->flush($itemid);
    }

    public function getImmediateChildren($filter=null,$emsIgnore=false,$model=null,$sizelimit=null)
    {
        return $this->getAllChildren($filter,'ONE',$emsIgnore,null,$model,$sizelimit);
    }

    public function getAll($filter,$model=null)
    {
        $basedn = null;
        $scope = 'SUB';
        Zivios_Log::debug("Running getAll with Filter : ".$filter);
        $entries = $this->search($filter,array('dn'),$basedn,$scope,$sizelimit);
        Zivios_Log::debug("Returned results");
        Zivios_Log::debug($entries);

        /**
         * If a manual sort is run on the returned result, please ignore
         * keys 0 and 1 as they (may) house EMSControl or ServiceContainer
         * object types which should be listed before other entries appear.
         *
         * This is simply for a more consistent view of the tree.
         * 
         * @todo : the sorting here needs to be removed. A sort function is now
         *         being run during by the LDAP RPC Service.
         */
        $result = array();
        $result_merge = array();

        $c = 0;
        $z = 1;
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            if ($objdn != $this->dn) {
                try {
                    $objiter = Zivios_Ldap_Cache::loadDn($objdn,$model);

                    $tmp_get = $objiter;

                    if ($tmp_get->getProperty('emstype') == 'ZiviosContainer') {
                        $result_merge[0] = $tmp_get;
                    } else if ($tmp_get->getProperty('emstype') == 'ServiceContainer') {
                        $result_merge[$z] = $tmp_get;
                        $z++;
                    } else {
                        $result[$c] = $tmp_get;
                        $c++;
                    }
                } catch (Zivios_AccessException $e) {
                    Zivios_Log::info("Loading " .$objdn. " threw Exception : " .
                            $e->getTraceAsString() . " ::: Ignoring");
                }
            }
        }
        return $result;
        
    }
    
    public function getAllChildren($filter=null,$scope='SUB',$emsIgnore=false,$basedn=null,$model=null,$sizelimit=null)
    {
        /**
         * By ignoring OBJECTCLASS emsIgnore, we ensure there is no wastage
         * in generating tree objects which are not required for display (like
         * plugin entries housed in ldap)
         *
         * @note: the filter below needs the action plugin object to ignore.
         */
        if ($basedn == null) {
            $basedn = $this->dn;
        }
        
        if ($filter == null) {
            isset($emsIgnore) ? 
                $filter = '(objectclass=emsobject)' : 
                $filter = '(&(!(objectclass=emsIgnore))(objectclass=emsobject))';
        }

        Zivios_Log::debug("Calling getAllChildren with filter " .
            $filter . " and model : ".$model);

        // Generate immediate children objects.
        $entries = $this->search($filter,array('dn'),$basedn,$scope,$sizelimit);


        /**
         * If a manual sort is run on the returned result, please ignore
         * keys 0 and 1 as they (may) house EMSControl or ServiceContainer
         * object types which should be listed before other entries appear.
         *
         * This is simply for a more consistent view of the tree.
         * 
         * @todo : the sorting here needs to be removed. A sort function is now
         *         being run during by the LDAP RPC Service.
         */
        $result = array();
        $result_merge = array();

        $c = 0;
        $z = 1;
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            if ($objdn != $this->dn) {
                try {
                    $objiter = Zivios_Ldap_Cache::loadDn($objdn,$model);

                    $tmp_get = $objiter;

                    if ($tmp_get->getProperty('emstype') == 'ZiviosContainer') {
                        $result_merge[0] = $tmp_get;
                    } else if ($tmp_get->getProperty('emstype') == 'ServiceContainer') {
                        $result_merge[$z] = $tmp_get;
                        $z++;
                    } else {
                        $result[$c] = $tmp_get;
                        $c++;
                    }
                } catch (Zivios_AccessException $e) {
                    Zivios_Log::info("Loading " .$objdn. " threw Exception : " .
                            $e->getTraceAsString() . " ::: Ignoring");
                }
            }
        }

        ksort($result_merge);
        return array_merge($result_merge, $result);
    }

    public function postDelete()
    {
        $this->reconnect();
        Zivios_Log::info("Ldap Deleting dn: " . $this->dn);
        $ret = ldap_delete($this->conn,$this->dn);
        if (!$ret) {
            $this->getError();
        }
        
        $this->close();
    }

    
    public function setExpired()
    {
        $userSession = new Zend_Session_Namespace("userSession");
        $userSession->pwexpired = $this->pwexpired;
    }
    
    public static function getUserCreds()
    {
        /**
         * @return array (with user credentials)
         */
        $userSession = new Zend_Session_Namespace("userSession");
        return array(
            'password' => Zivios_Security::decrypt($userSession->password),
            'dn' => $userSession->user_dn,
            'uid' => $userSession->uid,
            'auth' => $userSession->auth,
            'pwexpired' => $userSession->pwexpired);
    }
    
    protected function _getRegexLibrary()
    {
        if (null === $this->_regexLib) {
            $this->_regexLib = Zivios_Regex::loadLibrary();
        }

        return $this->_regexLib;
    }

    public function deleteRecursive(Zivios_Transaction_Group $group, $description=null)
    {
        Zivios_Log::info("Recursively deleting : ".$this->getdn());
        $children = $this->getImmediateChildren(null,true);
        $description = "Recursively Deleting " . $this->getdn();

        if (sizeof($children) > 0) {
            foreach ($children as $object) {
                $start = microtime(true);
                $object->deleteRecursive($group, $description);
                $stop = microtime(true);
                Zivios_Log::info("Delete recursive call on ".$object->getdn()." took : ".($stop-$start)."s");
            }
        }
        
        $this->delete($group, $description);
        return $group;
    }
    
    public function addDependence($dn)
    {
        $this->addPropertyItem('emsdependson',$dn);
    }
    
    public function hasDependents()
    {
        $filter = "(emsdependson=".$this->getdn().")";
        $entries = $this->search($filter);
        if ($entries['count'] > 0) {
            return true;
        }
        return false;
        
    }

    public static function compare($a,$b)
    {
        return strcasecmp($a->getProperty('cn'), $b->getProperty('cn'));
    }
}
