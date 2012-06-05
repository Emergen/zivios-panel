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
 * @version		$Id$
 * @subpackage  Security
 **/

class Zivios_Acl
{
    public $own_dn, $scope, $action, $acl_name, $subject_type, $subject_dn, $origline;

    const ACTION_GRANT = "grant";
    const ACTION_DENY = "deny";
    const SUBJECT_ALL="all";
    const SUBJECT_USER = "user";
    const SUBJECT_GROUP = "group";
    const SUBJECT_SELF = "self";
    const SCOPE_SUBTREE = "subtree";
    const SCOPE_ENTRY = "entry";
    const ACCESS_DENIED = -1;
    const ACCESS_NOANSWER = 0;
    const ACCESS_GRANTED = 1;
    const ACL_ALL = "all";

    /** Example ACL Entries include:
    * SCOPE : ACTION : ACLNAME : SUBJECT
    *
    * subtree:grant:CORE_CANCHGPW:user;uid=fkhan
    * entry:grant:CORE_CANRESTARTCOMPUTER:user;uid=fkhan
    * entry:deny:MOD_MAIL_SETQUOTA:all
    *
    * You will notice these are simliar to the OpenLDap ACI definition format
    * This is intentional to maintain atleast SOME form of consistency
    *
    */

    public function setAclName($name)
    {
        $this->acl_name = $name;
    }

    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function setType($type)
    {
    	$this->subject_type = $type;
    }
    public function setSubject($objdn)
    {
        $this->subject_dn = $objdn;
    }

    public function __construct($owndn,$permline="")
    {
        $this->own_dn = $owndn;
        if ($permline != "") {
            Zivios_Log::debug("Begin Parsing ACL Line : " . $permline . " at dn " . $owndn);
            $this->origline = $permline;
            $dirarr = explode(":",$permline);
            if (sizeof($dirarr) < 4)
                throw new Zivios_Exception("Malformed EMS ACL : $permline at dn: $owndn");
            $this->scope = $dirarr[0];
            $this->action = $dirarr[1];
            $this->acl_name = $dirarr[2];
            $this->processSubject($dirarr[3]);
        }
    }

    private function processSubject($subject)
    {
        if ($subject == self::SUBJECT_ALL)
            $this->subject_type = $subject;
        else {
            $ssplit = explode(";",$subject);
            $this->subject_type = $ssplit[0];
            $this->subject_dn = $ssplit[1];
        }
    }

    public function render()
    {
        $rendarr = array();
        $rendarr[0] = $this->scope;
        $rendarr[1] = $this->action;
        $rendarr[2] = $this->acl_name;

        if ($this->subject_type == self::SUBJECT_ALL)
            $rendarr[3] = $this->subject_type;
        else {
            $subjarr =  array();
            $subjarr[0] = $this->subject_type;
            $subjarr[1] = $this->subject_dn;
            $rendarr[3] = implode(";",$subjarr);
        }

        $toret = implode(":",$rendarr);
        Zivios_Log::debug("Ecl ACL Rendered : " . $toret);
        return $toret;
    }

    /**
     *
     *
     * @param unknown_type $aclname
     * Name of the ACL to test for
     * @param unknown_type $dn
     * Dn to test ACL for
     * @param EMSUser $user
     * @return
     * Return VALUES for this function are peculiar:
    * -1 - DENIED
    *  0 - No Comments or Does not Apply
    *  1 - GRANT
    */

    public function isAllowed($aclname,$dn,EMSUser $user=null)
    {
        
        if ($user == null) {
            $userdn =Zivios_Ldap_Engine::getUserCreds();
            $userdn = $userdn["dn"];

        } else
            $userdn = $user->getdn();

            Zivios_Log::debug("Processing ACL : $aclname, owndn: ".$this->own_dn." for object dn : $dn " .
                "on ACL : $aclname for logged in user : $userdn");
        /**
         * first check if the ACL name matches:
         */

        if ($this->acl_name != $aclname && $this->acl_name != self::ACL_ALL)  {
            Zivios_Log::debug("$aclname does not match ACL in " . $this->origline);
            return self::ACCESS_NOANSWER;
        } else
            Zivios_Log::debug("$aclname Match for ACL in  ".$this->origline);
            

        /**
         * Then check if the SCOPE fits
         */

        if (!$this->inScope($dn)) {
            Zivios_Log::debug("$dn is NOT in scope for ACL " . $this->origline);
            return self::ACCESS_NOANSWER;
        } else 
            Zivios_Log::debug("$dn Scope Match for ACL : ".$this->origline);

        //Since Scope obviously fits we process the SUBJECT

        if (!$this->isSubject($userdn,$dn)) {
            Zivios_Log::debug("Subject mismatch for " . $userdn . " in ACL : " . $this->origline);
            return self::ACCESS_NOANSWER;
        }

        /**
         * Everything fits. This ACL applies!!! Finally we can do something constructive
         */

        Zivios_Log::debug("Ems Acl :" . $aclname . " applies on dn: " . $dn . " with action :" . $this->action);
        if ($this->action == self::ACTION_DENY)
            return self::ACCESS_DENIED;
        else if ($this->action == self::ACTION_GRANT)
            return self::ACCESS_GRANTED;
    }

    public function isSubject($userdn,$objdn)
    {
        $userdn = strtolower($userdn);
        if ($this->subject_type == self::SUBJECT_ALL)
            return true;
        else if ($this->subject_type == self::SUBJECT_USER)
            return (strtolower($this->subject_dn) == $userdn);
        else if ($this->subject_type == self::SUBJECT_GROUP)
            Zivios_Log::error("Group Type EMS ACls not supported");
        else if ($this->subject_type == self::SUBJECT_SELF) {
            Zivios_Log::debug("Comparing owndn : ".strtolower($objdn)." against $userdn");
            return (strtolower($objdn) == $userdn);
        }
    }

    public function inScope($dn)
    {
        if ($this->scope == self::SCOPE_ENTRY) {
            if ($this->own_dn != $dn)
            	return false;
            else
            	return true;
        }
        else if ($this->scope == self::SCOPE_SUBTREE) {
            $own = $this->own_dn;
            if (preg_match("/$own$/",$dn))
                return true;
            else
            	return false;
        }
    }

    /**
     * Compare two acls to set priority! Designed to work with the usort function
     */

    public static function compare($a,$b)
    {
        /**
         * First compare by Scope! Entry has priority over subtree:
         */

        if ($a->scope != $b->scope)
        {
            if ($a->scope == self::SCOPE_SUBTREE)
                return 1;  // obviously the others scope is ENTRY then
            else
                return -1; // obviously the others sope is subtree and this entry
        }

        /**
         * Then compare by Type. Order or priorty: self,user,group,all
         */

        switch ($a->subject_type) {
            case self::SUBJECT_SELF:
                $atype = 0; break;
            case self::SUBJECT_USER:
                $atype = 1; break;
            case self::SUBJECT_GROUP:
                $atype = 2; break;
            case self::SUBJECT_ALL:
                $atype = 3; break;
        }

        switch ($b->subject_type) {
            case self::SUBJECT_SELF:
                $btype = 0; break;
            case self::SUBJECT_USER:
                $btype = 1; break;
            case self::SUBJECT_GROUP:
                $btype = 2; break;
            case self::SUBJECT_ALL:
                $btype = 3; break;
        }

        if ($atype != $btype) {
            if ($atype < $btype)
                return -1;
            else
                return 1;
        }

        return  0;
    }
}



