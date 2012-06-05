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
 * @subpackage  Security
 **/
/**
 * OpenLDAP ACIs are of the form:
 *
 * OID # SCOPE # RIGHTS # TYPE # SUBJECT
 *
 * where RIGHTS are of the form:
 * ACTION ; PERMISSION ; TARGET
 */
class Zivios_Ldap_Aci
{

   private $own_dn;
   const SCOPE_ENTRY = "entry";
   const SCOPE_SUBTREE = "subtree";
   const SCOPE_CHILDREN = "children";

   const TARGET_ATTR = "attrs";
   const TARGET_ALL = "[all]";
   const TARGET_ENTRY = "[entry]";

   const ACTION_GRANT = "grant";
   const ACTION_DENY = "deny";

   const TYPE_ACCESS_ID = "access-id";
   const TYPE_PUBLIC = "public";
   const TYPE_SELF = "self";
   const TYPE_GROUP = "group";


   const PERM_R = "r";
   const PERM_W = "w";
   const PERM_S = "s";
   const PERM_C = "c";
   const PERM_X = "x";

   const ACCESS_DENIED = -1;
   const ACCESS_NOANSWER = 0;
   const ACCESS_GRANTED = 1;

   public $origline;

   public $perm_array;

   public $oid;
   public $scope;

   public $target;

   public $attr_array;

   public $type;

   public $subject;

   public $action;

   public function __construct($owndn,$origline="")
   {
       $this->own_dn = $owndn;
       $this->origline = $origline;

       if ($origline != "") {
           $origline = strtolower($origline);
           Zivios_Log::debug("Begin Parsing ACI Line: $origline at dn $owndn");
           $parsed = explode("#",$origline);

           if ($parsed < 5) throw new Zivios_Exception("Malformed OpenLdapACI :".$this->origline." at dn: ".$this->own_dn);
           $this->oid = $parsed[0];
           $this->scope = $parsed[1];
           $rights = explode(";",$parsed[2]);

           if (sizeof($rights) > 3) throw new Zivios_Exception("Aci Parser can only handle a single Action ; Permissions ; Target directive");


           $this->action = $rights[0];
           $this->perm_array = explode(",",$rights[1]);

           $target = $rights[2];
           Zivios_Log::debug("Target is : $target");
           if ($target != self::TARGET_ALL && $target != self::TARGET_ENTRY) {
               $this->target = self::TARGET_ATTR;
               $this->attr_array = explode(",",$target);
           } else $this->target = $target;




           $this->type = $parsed[3];
           $this->subject = $parsed[4];
           Zivios_Log::debug("ACL Parsing Complete  Oid :".$this->oid.
               " scope :".$this->scope.
               " target:".$this->target.
               " type:".$this->type.
               " subject: ".$this->subject.
               " action: ".$this->action);

           $permlist = print_r($this->perm_array,1);
           //Zivios_Log::debug("Acl Parsing Perm List : ".$permlist);

       }
   }

   public function setScope($scope)
   {
       $this->scope = $scope;
   }

   public function setAction($action)
   {
       $this->action = $action;
   }

   public function setSubject($subject)
   {
       $this->subject = $subject;
   }

   public function setType($type)
   {
       $this->type = $type;
   }

   public function setAttrs($attrs)
   {
       $this->attr_array = $attrs;
   }

   public function setTarget($target)
   {
       $this->target = $target;
   }

   public function setPerms($perm_array)
   {
       if (!is_array($perm_array)) throw new Zivios_Exception("Please pass an array to setPerms");
       $this->perm_array = $perm_array;
   }


   public function render()
   {
       $renderarr = array();
       $renderarr[0] = $this->oid;
       $renderarr[1] = $this->scope;

       $perms = implode(",",$this->perm_array);

       $rights = array();
       $rights[0] = $this->action;
       $rights[1] = $perms;

       if ($this->target == self::TARGET_ATTR) {
           $rights[2] = implode(",",$this->attr_array);
       } else
           $rights[2] = $this->target;


       $renderarr[2] = implode(";",$rights);

       $renderarr[3] = $this->type;
       $renderarr[4] = $this->subject;

       $toret = implode("#",$renderarr);
       //Zivios_Log::debug("Returning : $toret");
       return $toret;
   }


   /** Return VALUES for this function are peculiar:
    * -1 - DENIED
    *  0 - No Comments or Does not Apply
    *  1 - GRANT
    */

   public function isAllowed($access,$dn,$attr=null,$userdn=null)
   {
       if ($userdn == null) {
           //Get user from session
           $userc = Zivios_Ldap_Util::getUserCreds();
           $userdn = $userc["dn"];
       }

       if (!$this->inScope($dn)) {
           Zivios_Log::debug("$dn not in scope for ACL: ".$this->origline);
           return self::ACCESS_NOANSWER;
       }

       if (!$this->hasTarget($attr)) {
           Zivios_Log::debug("No target match in ACL: ".$this->origline);
           return self::ACCESS_NOANSWER;
       }

       if (!$this->isSubject($userdn)) {
           Zivios_Log::debug("No Subject Match in ACL :".$this->origline." for userdn : ". $userdn);
           return self::ACCESS_NOANSWER;
       }



       //This ACL applies to the mentioned Request

       if (in_array($access,$this->perm_array)) {
           if ($this->action == self::ACTION_GRANT)
               return self::ACCESS_GRANTED;
           else return self::ACCESS_DENIED;

       } else return self::ACCESS_NOANSWER;


   }

    public function inScope($dn)
    {

        if ($this->scope == self::SCOPE_ENTRY) {
            if ($this->own_dn != $dn) return false;
            else return true;
        }
        else if ($this->scope == self::SCOPE_SUBTREE) {
            $own = $this->own_dn;
            if (preg_match("/$own$/",$dn))
                return true;
            else return false;
        }

        throw new Zivios_Exception("Unknown Scope Definition in Acl : ".$this->origline." for dn:".$this->own_dn);
    }


    public function hasTarget($attr)
    {
        if ($this->target == self::TARGET_ALL) {
            return true;
        } else if ($this->target == self::TARGET_ENTRY) {
            if ($attrs == null) return true;
            else return false;
        } else if ($this->target == self::TARGET_ATTR) {
            return in_array($attr,$this->attr_array);
        }

        throw new Zivios_Exception("Unknown Target Definition in Acl : ".$this->origline);
    }

    public function isSubject($userdn)
    {
        if ($this->type == self::TYPE_PUBLIC) {
            return true;
        } else if ($this->type == self::TYPE_ACCESS_ID) {
            return ($this->subject == $userdn);
        } else if ($this->type == self::TYPE_GROUP) {
            /*
            $ds = Zivios_Ldap_Util::doUserBind();
            $lobj = new Zivios_LdapObject($ds,$this->subject);
            $group = $lobj->getObject();
            if ($group->hasImmediateMember($user))
                return true;
            else
                return false;
                */
                Zivios_Log::error(" Group type ACLs NOT implemented");
        } else if ($this->type == self::TYPE_SELF) {
            return ($this->own_dn == $userdn);
        }

        throw new Zivios_Exception("Unknown Subject Definition in Acl : ".$this->origline);

    }







}

