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
 * @package		mod_default
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSSecurityObject.php 1039 2008-09-09 12:19:59Z fkhan $
 **/

class EMSSecurityObject extends Zivios_Ldap_Cache
{

    public $aci_array;
    public $emsacl_array;

	public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'emspermission';
        $attrs[] = 'openldapaci';
        parent::__construct($dn,$attrs);
    }


    /* need a specialized get Parent to make sure ONLY EMSSecurity
    * Part of the object is loaded. This is necessary
    */

    public function getEmsAclArray($emsacllist)
    {
        if ($this->getdn() != null) {
            $aclarray = array();
            foreach ($emsacllist as $emsacl) {
                $aclarray[$emsacl] = "";
            }
            $this->_getEmsAclArrayRecursive($aclarray,$this->getdn());
            return $aclarray;
        }
    }

    private function _getEmsAclArrayRecursive(&$aclarray,$dn)
    {
        $recursemore = 0;
        Zivios_Log::debug("Ems Acl Array Recursive run for dn :".$this->getdn());

        foreach ($aclarray as $acl => $access) {

            if ($access == "") {

                $reply = $this->emsAclProbe($acl,$dn);
                if ($reply == Zivios_Acl::ACCESS_GRANTED) {
                    $aclarray[$acl] = Zivios_Acl::ACCESS_GRANTED;
                } else if ($reply == Zivios_Acl::ACCESS_DENIED) {
                    $aclarray[$acl] = Zivios_Acl::ACCESS_DENIED;
                } else
                    $recursemore = 1;

            }
        }

        if ($recursemore) {
            Zivios_Log::debug("Still have EMS ACL Params that need clarification.... recursing to parent!");
            $emsobj = $this->getParent();
            if ($emsobj != null)
                $emsobj->_getEmsAclArrayRecursive($aclarray,$dn);
            else {
                Zivios_Log::debug("EMS Acls: No more Parents for dn : ".$this->getdn().", going up");
                return;
            }

        } else {
            Zivios_Log::debug("No Recursion necessary, going up one level");
            return;
        }

        return;
    }

    private function emsAclProbe($aclname,$dn)
    {
        $securityConfig = Zend_Registry::get('securityConfig');
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $userdn =Zivios_Ldap_Engine::getUserCreds();
        $userdn = strtolower($userdn["dn"]);
        $admindn = strtolower($ldapConfig->admindnprefix . "," . $ldapConfig->basedn);

        if ($dn == null)
            return null;

        Zivios_Log::debug("Probing for acl : $aclname FOR dn :$dn on dn: ". $this->getdn());
        if ($securityConfig->zadminaclbypass && ($userdn == $admindn) ) {
            Zivios_Log::info("Zadmin Acl Bypass is enabled, returned ACCESS GRANTED");
            return Zivios_Acl::ACCESS_GRANTED;
        }


        foreach ($this->emsacl_array as $emsacl) {
            $reply = $emsacl->isAllowed($aclname,$dn);
            if ($reply == Zivios_Acl::ACCESS_GRANTED)
                return Zivios_Acl::ACCESS_GRANTED;
            else if ($reply == Zivios_Acl::ACCESS_DENIED)
                return Zivios_Acl::ACCESS_DENIED;
        }

        return Zivios_Acl::ACCESS_NOANSWER;


    }


    public function getParent()
	{
        $appConfig = Zend_Registry::get('ldapConfig');
        Zivios_Log::debug("Get Parent Called from EMS SEc, dn is ".$this->getdn());
        if ($this->getdn() == $appConfig->basedn) {
            return null;
        }

		$tokendn = explode(',',$this->getdn());
		$parentdnarray = array_slice($tokendn,1);
		$parentdn = implode(',',$parentdnarray);
		$emssecobj =  new EMSSecurityObject($parentdn);
        $emssecobj->init();
        Zivios_Log::debug("Returning parent with dn $parentdn");
        return $emssecobj;
	}


    public function init()
    {
        $dn = $this->dn;
        if ($dn != null)
            $this->loadPermissions($dn);

        $this->addAttrs($this->attrs,1);
        // DO NOT call the parent, wasteful!
    }


    public function getAclArray($paramlist)
    {
        if ($this->getdn() != null) {
            $aclarray = array();
            foreach ($paramlist as $param) {
                $aclarray[$param] = "";
            }
            $this->_getAclArrayRecursive($aclarray,$this->getdn());
            return $aclarray;
        }
    }

    private function _getAclArrayRecursive(&$aclarray,$dn)
    {
        $recursemore = 1;
       // Zivios_Log::debug("Acl Array Recursive run for dn :".$this->getdn());

        foreach ($aclarray as $id => $access) {

            $alreadyread = preg_match("/".Zivios_Ldap_Aci::PERM_R."/",$access);
            $alreadywrite = preg_match("/".Zivios_Ldap_Aci::PERM_W."/",$access);


            if (!$alreadyread) {

                $readreply = $this->hasRead($dn,$id);
                if ($readreply == Zivios_Ldap_Aci::ACCESS_GRANTED) {
                    $aclarray[$id] .= "+".Zivios_Ldap_Aci::PERM_R;
                } else if ($readreply == Zivios_Ldap_Aci::ACCESS_DENIED) {
                    $aclarray[$id] .= "-".Zivios_Ldap_Aci::PERM_R;
                } else
                    $recursemore = 1;

            }

            if (!$alreadywrite) {
                $writereply = $this->hasWrite($dn,$id);
                if ($writereply == Zivios_Ldap_Aci::ACCESS_GRANTED) {
                    $aclarray[$id] .= "+".Zivios_Ldap_Aci::PERM_W;
                } else if ($writereply == Zivios_Ldap_Aci::ACCESS_DENIED) {
                    $aclarray[$id] .= "-".Zivios_Ldap_Aci::PERM_W;
                } else
                    $recursemore = 1;


            }
        }

        if ($recursemore) {
            //Zivios_Log::debug("Still have ACL Params that need clarification.... recursing to parent!");
            $emsobj = $this->getParent();
            if ($emsobj != null)
                $emsobj->_getAclArrayRecursive($aclarray,$dn);
            else {
                //Zivios_Log::debug("No more Parents for dn : ".$this->getdn().", going up");
                return;
            }

        } else {
            //Zivios_Log::debug("No Recursion necessary, going up one level");
            return;
        }

        return;

    }

    public function hasRead($dn=null,$attr=null,$user=null)
    {
        if ($attr == "openldapaci" || $attr == "emspermission") return true;
        return $this->hasAccess(Zivios_Ldap_Aci::PERM_R,$dn,$attr,$user);
    }

    public function hasWrite($dn=null,$attr=null,$user=null)
    {
        if ($attr == "openldapaci" || $attr == "emspermission") return true;
        return $this->hasAccess(Zivios_Ldap_Aci::PERM_W,$dn,$attr,$user);
    }



	public function hasAccess($access,$dn=null,$attr=null,$user=null)
    {

        if ($dn == null)
            $dn = $this->getdn();

        //Zivios_Log::debug("Checking for $access access on $dn for attr: $attr");

        foreach ($this->aci_array as $aci) {
            $resp = $aci->isAllowed($access,$dn,$attr,$user);
            if ($resp == Zivios_Ldap_Aci::ACCESS_GRANTED)
                return Zivios_Ldap_Aci::ACCESS_GRANTED;
            else if ($resp == Zivios_Ldap_Aci::ACCESS_DENIED)
                return Zivios_Ldap_Aci::ACCESS_DENIED;
        }


        // No choice! return NO ANSWER

        return Zivios_Ldap_Aci::ACCESS_NOANSWER;

    }


    private function loadPermissions()
    {
        $dn = $this->getdn();
        $params = $this->getLdapAci($dn);
        $this->aci_array = array();
        $this->emsacl_array = array();

        if ($params != null) {
            if (!is_array($params)) {
                $paramarray = array();
                $paramarray[] = $params;
            } else
                $paramarray = $params;


            foreach ($paramarray as $paramline) {
                $aciobj = new Zivios_Ldap_Aci($this->getdn(),$paramline);
                $this->aci_array[] = $aciobj;
            }
        }

        $emsperms = $this->getEmsPerms($dn);

        Zivios_Log::debug ("Processing EMSPerms :: ");
        Zivios_Log::debug($emsperms);

        if ($emsperms != null) {
            if (!is_array($emsperms)) {
                $emspermarray = array();
                $emspermarray[] = $emsperms;
            } else
                $emspermarray = $emsperms;

            foreach ($emspermarray as $emsperm) {
                $acls = new Zivios_Acl($this->getdn(),$emsperm);
                $this->emsacl_array[] = $acls;
            }
            usort($this->emsacl_array,array("Zivios_Acl","compare"));

        }

        Zivios_Log::debug("EMS Acls after sorting ");
        Zivios_Log::debug($this->emsacl_array);


    }

    public function getNextLdapAciOid()
    {
    	$oid = -1;
    	foreach ($this->aci_array as $aci) {
    		if ($aci->oid > $oid)
    			$oid = $aci->oid;

    	}
    	return $oid+1;
    }
    public function addLdapAci(Zivios_Ldap_Aci $aci)
    {
    	$aci->oid = $this->getNextLdapAciOid();
    	$this->aci_array[] = $aci;
    	return $this->addPropertyItem('openldapaci',$aci->render());

    }

    public function removeLdapAci($aciorigline)
    {
    	return $this->removePropertyItem('openldapaci',$aciorigline);
    }

    public function addEmsAcl(Zivios_Acl $acl)
    {
    	return $this->addPropertyItem('emspermission',$acl->render());
    }

    public function removeEmsAcl($origline)
    {
    	return $this->removePropertyItem('emspermission',$origline);
    }


}

