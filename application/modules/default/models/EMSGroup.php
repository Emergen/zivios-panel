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
 * @package     mod_default
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSGroup extends EMSPluginManager
{
    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'cn';
        $attrs[] = 'member';

        parent::__construct($dn,$attrs);
        //$this->initPlugins();
    }

    public function init()
    {
        parent::init();
        $param = $this->getParameter('cn');
  //      $param->addValidator(new Zend_Validate_Alnum(1),
   //             Zivios_Validate::errorCode2Array('alnum',"Group Name"));
    }

    protected function getrdn()
    {
        return 'cn';
    }

    public function getGroupByGidNumber($gid)
    {
        $filter = "(&(objectclass=emsgroup)(gidnumber=$gid))";
        $results = $this->search($filter,array('dn'));
        if ($results['count'] > 1 || $results['count'] == 0) 
            return null;
        
        Zivios_Log::debug($results);
        $groupdn = $results[0]['dn'];
        return Zivios_Ldap_Cache::loadDn($groupdn);
    
    }
               

    public function userMoved(EMSUser $user,$olddn, Zivios_Transaction_Group $tgroup)
    {
        $param = $this->getParameter('member');
        $param->removeValue($olddn);
        $param->addValue($user->makeMoveDn());      
        $this->update($tgroup,'Renaming user membership in group '.$this->getProperty('cn'));
    }
  
    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $group, $description=null)
    {

        $this->setProperty('emstype', EMSObject::TYPE_GROUP);

        if ($this instanceof EMSGroup) {
            $this->setProperty('member', $this->ldapConfig->placeholder);
            $objclass = $this->getParameter('objectclass');
            $objclass->addValue('groupofnames');
            $objclass->addValue('EMSGroup');
        }

        return parent::add($parent, $group, $description);
    }

    public function addToGroup(EMSUser $eobj,Zivios_Transaction_Group $group)
    {
        //do a simple addto group - worry about transactions later
        //maybe check group policies
        $param = $this->getParameter('member');
        $param->addValue($eobj->getdn());

        return parent::update($group,"Adding User " . $eobj->getdn() . " to group " . $this->getdn());

    }

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $users = $this->getAllUsers();
        // unsubscribe all users from this group, including plugins.
        foreach ($users as $user) {
            $tgroup = $this->removeFromGroup($user,$tgroup,$description);
        }

        return parent::delete($tgroup);
    }

    public function removeFromGroup(EMSUser $eobj,Zivios_Transaction_Group $tgroup,$force=0,$pluginscan=1)
    {

        // First check if this member is an immediate group member or NOT.
        // If not then remove from group has no solution.
        if ($this->hasImmediateMember($eobj)) {
            Zivios_Log::debug($eobj->getdn()." is immediate member- Starting remove");

            if ($eobj instanceof EMSUser) {
                //Zivios_Log::debug($eobj->getdn()." is a user.. checking groups ");
                $pgroup = $eobj->getPrimaryGroup();
                if (($this->getdn() == $pgroup->getdn()) && ($force == 0)) {
                    throw new Zivios_Exception("User ".$eobj->getdn()." cannot be removed from primary group");
                }
                else {

                    $param = $this->getParameter('member');
                    $param->removeValue($eobj->getdn());
                    $tgroup =  $this->update($tgroup,"Removing user ".$eobj->getdn()." from group ".$this->getdn());
                    if ($pluginscan) {
                        $plugins = $this->getAllPlugins();
                        foreach ($plugins as $plugin) {
                            $tgroup = $plugin->removeFromGroup($eobj,$tgroup);
                        }
                    }

                    return $tgroup;

                }
                //$plugins = $this->getAllPlugins();

                //$pgroupdn = $eobj->getPrimaryGroup();
                //if ($this->getdn() == $pgroupdn) {
            } else {
                Zivios_Log::debug("Not a EMS User...");
                return $tgroup;
            }
        }
        else {

            Zivios_Log::error("RemoveFromGroup called to remove dn ".$eobj->getdn()."
                    from ".$this->getdn()." when not a immediate member. recursive/nested remove not
                        yet implemented");
            return $tgroup;
        }
    }

    public function hasImmediateMember(EMSUser $eobj)
    {
        $class = get_class($eobj);
        $tgtdn = $eobj->getdn();
        //$memberlist = $this->getAllImmediateMembers('NOMODEL');
        $memberlist = $this->getProperty('member');
        foreach ($memberlist as $member) {
            $mdn = $member;
            if (strtolower($mdn) == strtolower($tgtdn)) return 1;
        }
        return 0;
    }
    
    public function isPrimaryGroupForUser($userdn)
    {
        $pgroupdn = strtolower($this->sp_query($userdn,'emsprimarygroupdn'));
        return (strcmp($pgroupdn,strtolower($this->getdn())) == 0);
    }

    public function getAllGroups()
    {
        $grouparray = array();
        $this->getAllGRecurse($this->getdn(),$grouparray);
        return $grouparray;
    }

    private function getAllGRecurse($dn,&$grouparray)
    {
        /** get all immediate groups */
        Zivios_Log::debug("Finding all groups for dn :$dn");
        $ldapdriver = $this->getDriver();
        $filter = "(&(objectclass=groupofnames)(member=$dn))";

        $ldapdriver->filter = $filter;
        $ldapdriver->deref = 'LDAP_DEREF_ALWAYS';
        $ldapdriver->scope = 'SUB';
        $ldapdriver->base_dn = $this->ldapConfig->basedn;
        $ldapdriver->return = array('dn');

        $entries = $ldapdriver->search();

        $log = print_r($entries,1);
        Zivios_Log::debug($log);
        Zivios_log::debug("Search filter : $filter");
        $result = array();

        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $arraysize = sizeof($grouparray);
            //$emsobjdn = $this->newLdapObject($objdn);
            $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
            $grouparray[] = $emsobjdn;
            Zivios_Log::debug("group $objdn holds $dn");
            $this->getAllGRecurse($objdn,$grouparray);
        }
    }

    public function move(Zivios_Ldap_Engine $newparent,Zivios_Transaction_Group $tgroup,$description=null,$parentmove=false)
    {
        $olddn = $this->getdn();
        $primaryusers = $this->getAllPrimaryUsers();
        if (!$parentmove)
            parent::move($newparent,$tgroup,$description);
        
        foreach ($primaryusers as $users) {
            $newdn = $this->makeMoveDn($newparent);
            $user = Zivios_Ldap_Cache::loadDn($users->getdn());
            $user->setProperty('emsprimarygroupdn',$newdn);
            $user->update($tgroup,'Updating primary group membership on group move for user :'.$user->getdn());
        }
    }
        
    public function getAllPrimaryUsers()
    {
        $filter = '(&(objectclass=EMSUser)(emsprimarygroupdn='.$this->getdn().'))';
        $basedn = $this->ldapConfig->basedn;
        return $this->getAllChildren($filter,null,null,$basedn,'NOMODEL');
        
    }
    public function getAllUsers()
    {
        $userarray = array();
        $this->getAllURecurse($this->getdn(),$userarray);
        return $userarray;
    }

    private function _getAllImmediateMembers($listtype,$nomodel)
    {
        Zivios_Log::debug("Finding All IMMEDIATE members for group :".$this->getdn());
        $members = $this->getProperty('member',1);
        Zivios_Log::debug($members);
        $memberarray = array();
        for ($i=0;$i<sizeof($members);$i++) {
            $objdn =$members[$i];
            //Zivios_Log::debug("Processing member : $objdn");
            if ($objdn != $this->ldapConfig->placeholder) {
                //$emsobjdn = $this->newLdapObject($objdn);
                if ($nomodel)
                    $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn,'NOMODEL');
                else 
                    $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
                    
                $modelclass = $emsobjdn->getProperty('emsmodelclass');
                if ($listtype == 0) {
                    $memberarray[] = $emsobjdn;
                }
                else if ($listtype == 1 && $modelclass == 'EMSUser') {
                    $memberarray[] = $emsobjdn;
                }
                else if ($listtype == 2 && $modelclass == 'EMSGroup') {
                    $memberarray[] = $emsobjdn;
                }
            }
        }

        return $memberarray;
    }
  

    public function getAllImmediateUsers($nomodel=false)
    {
        return $this->_getAllImmediateMembers(1,$nomodel);
    }

    public function getAllImmediateGroups($nomodel=false)
    {
        return $this->_getAllImmediateMembers(2,$nomodel);
    }

    public function getAllImmediateMembers($nomodel=false)
    {
        return $this->_getAllImmediateMembers(0,$nomodel);
    }
    
    public function setMainForm($setVals)
    {
        $this->setViaForm($setVals, array('dn'));
    }
    
    private function getAllURecurse($dn,&$userarray)
    {
        /** get all immediate groups */
        Zivios_Log::debug("Finding all Users for group :$dn");
        $currgroup = Zivios_Ldap_Cache::loadDn($dn);
        $members = $currgroup->getProperty('member');
        //$memberarr = print_r($members,1);
        //Zivios_Log::debug("group $dn has member list :$memberarr");

        for ($i=1;$i<sizeof($members);$i++) {
            $objdn = $members[$i];
            Zivios_Log::debug("Processing member : $objdn");
            if ($objdn != $this->ldapConfig->placeholder) {
                try {
                    $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
                    if ($emsobjdn instanceof EMSUser) {
                    $userarray[] = $emsobjdn;
                    } else if ($emsobjdn instanceof EMSGroup) {
                        Zivios_Log::debug("group {$objdn} is a member of {$dn}");
                        $this->getAllURecurse($objdn,$userarray);
                    }
                } catch (Zivios_Exception $e) {
                    Zivios_Log::debug("Lost a member :".$objdn.". Skipping queitly");
                }

                
            }
        }
    }
    
    public function import($tgroup,$entry,$parentdn)
    {
        $groupparent = Zivios_Ldap_Cache::loadDn($parentdn);
        $posixplug = $this->newPlugin("PosixGroup");
        $krbplug = $this->newPlugin("KerberosGroup");
        $caplug = $this->newPlugin("CaGroup");
        Zivios_Log::error($entry);
        $cn = $entry['cn'][0];
        Zivios_Log::error("cn is :$cn");
        $this->setProperty('cn',$cn);
        Zivios_Log::error("CN set is ".$this->getProperty('cn'));
        $this->add($groupparent,$tgroup);
        $posixplug->import($entry);
        $this->addPlugin($posixplug, $tgroup);
        
        // Instantiate Master Kerberos Service and link group to it.
        $service = $krbplug->getMasterService();

        if (!$service instanceof KerberosService) {
            throw new Zivios_Error( "Kerberos Service for Group could not be" .
                " initialized. Please ensure the Master Kerberos Service is running");
        }

        $krbplug->linkToService($service);
        $this->addPlugin($krbplug, $tgroup);

        // Add CA group plugin to new group. Link group to service.
        $service = $caplug->getMasterService();

        if (!$service instanceof CaService) {
            throw new Zivios_Error("CA Service for Group could not be" .
                " initialized.");
        }

        $caplug->linkToService($service);
        $this->addPlugin($caplug, $tgroup);
    }
}
