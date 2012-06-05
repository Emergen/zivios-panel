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

class EMSUser extends EMSPluginManager
{
    public $primary_group;
    private $dirty_groups,$dirty_group_plugins, $newpassword;

    public function __construct($dn=null,$attrs = null,$acls = null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        if ($acls == null) {
            $acls = array();
        }
        
        $attrs[] = 'cn';
        $attrs[] = 'givenname';
        $attrs[] = 'uid';
        $attrs[] = 'sn';
        $attrs[] = 'mobile';
        $attrs[] = 'title';
        $attrs[] = 'ou';
        $attrs[] = 'telephonenumber';
        $attrs[] = 'facsimiletelephonenumber';
        $attrs[] = 'homephone';
        $attrs[] = 'homepostaladdress';
        $attrs[] = 'emsprimarygroupdn';
        $attrs[] = 'userpassword';
        $attrs[] = 'emsaccountlockout';
        $attrs[] = 'employeenumber';
        $attrs[] = 'departmentnumber';
        $attrs[] = 'employeetype';
        $attrs[] = 'mail';

        $acls[] = 'CORE_USER_CANCHANGEPW';
        $acls[] = 'CORE_USER_CANDELETE';
        $acls[] = 'CORE_USER_CANUPDATE';
        

        $this->dirty_groups = array();
        $this->dirty_group_plugins = array();

        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();

        $param = $this->getParameter('givenname');
        $param = $this->getParameter('uid');
        $param = $this->getParameter('sn');
        $param = $this->getParameter('mobile');
        $param = $this->getParameter('title');
        $param = $this->getParameter('ou');
        $param = $this->getParameter('telephonenumber');
        $param = $this->getParameter('facsimiletelephonenumber');
        $param = $this->getParameter('homephone');
        $param = $this->getParameter('homepostaladdress');
        $groupdn = $this->getProperty('emsprimarygroupdn');

        if ($groupdn != null) {
            $this->primary_group = Zivios_Ldap_Cache::loadDn($groupdn);
        }
    }

    public function setPassword($newpass)
    {
        Zivios_Log::debug("setPassword called");
    }

    public function getGidNumber()
    {
        return $this->primary_group->getProperty("gidnumber");
    }
    
    public function getByUid($uid)
    {
        $entries = $this->getAll('(&(uid='.$uid.')(objectclass=emsuser))');
        if (sizeof($entries) > 0)
            return $entries[0];
        else 
            return null;
    }

    public function getPrimaryGroup()
    {
        if ($this->primary_group == null)
            $this->primary_group = Zivios_Ldap_Cache::loadDn($this->getProperty('emsprimarygroupdn'));
        
        return $this->primary_group;
    }

    public function setPrimaryGroup(EMSGroup $group)
    {
        $this->primary_group = $group;
        $this->setProperty('emsprimarygroupdn',$this->primary_group->getdn());
    }

    public function move(Zivios_Ldap_Engine $newparent,Zivios_Transaction_Group $tgroup,$description=null,$parentmove=false)
    {
        Zivios_Log::debug("Inside User Move!!");
        $groups = $this->getAllGroups();
        
        $olddn = $this->getdn();
        parent::move($newparent,$tgroup,$description,$parentmove);
        
        foreach($groups as $group) {
            $group->userMoved($this,$olddn,$tgroup);
        }
    }
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $group,$description=null)
    {
        $this->requireAcl("CORE_USER_CANADD");
        $uid = $this->getProperty('uid');

        if ($this instanceof EMSUser) {
            $param = $this->getParameter('objectclass');
            $param->addValue('inetOrgPerson');
            $param->addValue('EMSUser');

            $this->setProperty('userpassword','{K5KEY}');
            $this->setProperty('emstype', EMSObject::TYPE_USER);
            //$this->setProperty('cn',$this->getProperty('givenname')." ".$this->getProperty('sn'));
        }
        
        parent::add($parent,$group,$description);
        $this->primary_group->addToGroup($this,$group);
        return $group;
    }

    public function changePassword($newpassword,Zivios_Transaction_Group $group)
    {
        $this->requireAcl('CORE_USER_CANCHANGEPW');
        $this->newpassword = $newpassword;
        $usercreds = Zivios_Ldap_Engine::getUserCreds();
        $this->_changeLdapPassword($group,'Changing Ldap Password',$newpassword);
        $this->fireEvent('CORE_USER_PWCHANGED',$group);
        
        if (strtolower($this->getdn()) == strtolower($usercreds['dn'])) {
            $handler = $this->_changeSessionPassword($group,'Change password in Session',array());
        }
        return $handler;
    }

    public function changeLdapPassword($newpassword)
    {
        $this->reconnect();
        $this->setControls();
        $usercreds = Zivios_Ldap_Engine::getUserCreds();
        $oldpw = null;
        //Zivios_Log::debug("Old password is : ".$oldpw." new is :".$newpassword);
        $result = ldap_exop_passwd($this->conn,$this->getdn(),$oldpw,$newpassword);
        ldap_ctrl_ppolicy_resp($this->conn,$result,$exp,$gr,$err,$emsg);
        if (isset($err)) {
            throw new Zivios_Ldap_Exception('Password Could not be Changed:',$emsg,$err);
        }
    }
    public function changeSessionPassword()
    {
        $newpass = $this->getNewPassword();
        $_userSession = new Zend_Session_Namespace("userSession");
        $_userSession->password = Zivios_Security::encrypt($newpass);
        Zivios_Log::debug("Password in Session updated!");
        $this->reconnect(1);
    }

    public function getNewPassword()
    {
        return $this->newpassword;
    }

    protected function getrdn()
    {
        return 'uid';
    }

    protected function makeDn($parent)
    {
        return $this->getrdn().'='.$this->getProperty('uid').','.$parent->getdn();
    }

    /* Take a look at this later!
    public function prepare($childcall=0) {
        /* Add Kerberos Details if this is new, add before parent call
            so EMSObject takes care of LDAP
        $uid = $this->getProperty('uid');



        parent::prepare(1);

        if (!$childcall && ($this->isNew())) {
            //$this->_lobj->addItem('uid',$paramarray['cn']->getValue());

        }
    }*/

    public function getAllGroups($norecurse=0)
    {
        $grouparray = array();
        $this->getAllGRecurse($this->getdn(),$grouparray,$norecurse);
        return $grouparray;
    }

    public function isMemberOf(EMSGroup $group)
    {
        // Inefficient code follows. Returns 0 if not a member and 1 if it is
        $tgtdn = $group->getdn();
        $found = 0;
        $grouparray = $this->getAllGroups();
        foreach ($grouparray as $group) {
            $iterdn = $group->getdn();
            Zivios_Log::debug("$iterdn  ===== $tgtdn");
            if ($tgtdn == $iterdn) $found=1;
        }
        return $found;
    }


    public function getAllPossibleModules()
    {
        $groups = $this->getAllGroups();
        $modulelist = array();
        foreach ($groups as $group) {
            //Zivios_Log::debug('calling get modules on group: ' . $group->getProperty('cn'));
            $modulearray = $group->getModules();
            //ecl_log::debug_r($modulearray);
            $modulelist = array_merge($modulearray,$modulelist);
            //ecl_log::debug_r($modulelist);
        }

        return array_unique($modulelist);
    }
    
    public function getAllAvailableModules()
    {
        $possible = $this->getAllPossibleModules();
        $hasmodules = $this->getModules();
        $diff = array_diff($possible,$hasmodules);
        return $diff;
    }

    public function getPossiblePlugins()
    {
        // Another inefficient solution, but should work fine...
        $groups = $this->getAllGroups();
        $pluginarray = array();
        foreach($groups as $group) {
            $pluginlist = $group->getAllPlugins();
            foreach ($pluginlist as $plugin) {
                $pluginclass = $plugin->getUserPluginName();
                if (!in_array($pluginclass,$this->_plugins)) {
                    $pluginarray[] = $pluginclass;
                }
            }
        }
        return $pluginarray;

    }

    private function getAllGRecurse($dn,&$grouparray,$norecurse=0)
    {
        // get all immediate groups
        $filter = "(&(objectclass=groupofnames)(member=$dn))";
        $entries = $this->search($filter,array('dn'),null,'SUB');
        $result = array();

        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $arraysize = sizeof($grouparray);
            $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
            $grouparray[] = $emsobjdn;
            if (!$norecurse) {
                //Zivios_Log::debug("group $objdn holds $dn");
                $this->getAllGRecurse($objdn,$grouparray);
            }
        }
    }

    public function addPlugin($plugin,Zivios_Transaction_Group $group,$description=null)
    {
        /** Match corresponding GROUP plugin
        */
        //$groupplugname = $plugin->getGroupPluginName();
        //$group = $this->getGroupWithPlugin($groupplugname);
        //$plugin->setGroup($group);

        //$groupplug = $group->getPlugin($groupplugname);
        return parent::addPlugin($plugin,$group,$description);
        //$this->dirty_group_plugins[] = $groupplug;
        //$this->dirty_groups[] = $group;
    }

    public function getGroupWithPlugin($pluginclassname)
    {
        return $this->getAllGroupsWithPlugin($pluginclassname,1);
    }

    public function getAllGroupsWithPlugin($pluginclassname,$firstonly=0)
    {
        /** Search IMMEDIATE groups and locate required plugin*/

        if ($this->isNew()) {
            /*This user does not exist yet. Use the group provided for primarygroup!*/
            $plugin = $this->primary_group->getPlugin($pluginclassname);
            if ($plugin == null) throw new Zivios_Exception("No groups found for this plugin");
            return $this->primary_group;
        }

        $dn = $this->getdn();
        //Zivios_Log::debug("Finding all groups for dn :$dn");

        $filter = "(&(objectclass=groupofnames)(member=$dn)(emsplugins=$pluginclassname))";
        $entries = $this->search($filter,array('dn'),null,'SUB');
        $result = array();

        if ($entries['count'] == 0) {
            //throw new Zivios_Exception("No groups found for this plugin, searching for $pluginclassname");
            return null;
        } else {
            if ($firstonly) {
                $objdn = $entries[0]['dn'];
                $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);
                return $emsobjdn;
            } else {
                $retarray = array();
                foreach ($entries as $entry) {
                    if ($entry['dn'] != "")
                        $retarray[] = Zivios_Ldap_Cache::loadDn($entry['dn']);
                }

                return $retarray;
            }
        }
    }

    public function update(Zivios_Transaction_Group $tgroup,$description=null,$namespace='CORE')
    {
        $this->requireAcl("CORE_USER_CANUPDATE");
        parent::update($tgroup,$description,$namespace);
    }
    
    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->requireAcl("CORE_USER_CANDELETE");
        $groups = $this->getAllGroups();
        foreach ($groups as $group) {
            $tgroup = $group->removeFromGroup($this,$tgroup,1,0);
        }
        return parent::delete($tgroup,$description);
    }

    public function getUserAccountForm()
    {
        $regexLib = $this->_getRegexLibrary();

        // csrf element.
        $noCsrf = new Zend_Form_Element_Hash('no_csrf_useraccountform');
        $noCsrf->setSalt(md5(rand(0,1000)));

        $uaform = new Zend_Dojo_Form_SubForm();
        $uaform->setAttribs(array(
            'name'   => 'useraccountform',
            'legend' => 'User Account Details',
            'dijitParams' => array(
                'title' => 'User Account Details',
            ),
        ));

        $uaform->addElement('ValidationTextBox', 'givenname', array(
            'required'          => true,
            'label'             => 'First Name: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->givenname,
            'invalidMessage'    => 'Invalid characters in first name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->givenname.'/i')),
                                   ),
            'value'             => $this->getProperty('givenname'),
        ));

        $uaform->addElement('ValidationTextBox', 'sn', array(
            'required'          => true,
            'label'             => 'Last Name: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->sn,
            'invalidMessage'    => 'Invalid characters in last name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->sn.'/')),
                                   ),
            'value'             => $this->getProperty('sn'),
        ));

        $uaform->addElement('ValidationTextBox', 'title', array(
            'required'          => false,
            'label'             => 'Title: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->alnumwithspaces,
            'invalidMessage'    => 'Invalid characters title.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/')),
                                   ),
            'value'             => $this->getProperty('title'),                                   
        ));
        
        $uaform->addElement('ValidationTextBox', 'employeenumber', array(
            'required'          => false,
            'label'             => 'Employee No: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->digits,
            'invalidMessage'    => 'Invalid characters in id.',
            'filters'           => array('StringTrim'),
            'value'             => $this->getProperty('employeenumber'),                                   
        ));

        $uaform->addElement('ValidationTextBox', 'departmentnumber', array(
            'required'          => false,
            'label'             => 'Department: ',
            'maxlength'         => 50,
            'filters'           => array('StringTrim'),
            'value'             => $this->getProperty('departmentnumber'),                                   
        ));

        $uaform->addElement('ValidationTextBox', 'employeetype', array(
            'required'          => false,
            'label'             => 'Designation: ',
            'maxlength'         => 50,
            'filters'           => array('StringTrim'),
            'value'             => $this->getProperty('employeetype'),                                   
        ));

        $uaform->addElement('ValidationTextBox', 'ou', array(
            'required'          => false,
            'label'             => 'Department: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->alnumwithspaces,
            'invalidMessage'    => 'Invalid characters in department field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/')),
                                   ),
            'value'             => $this->getProperty('ou'),                                   
        ));        

        $uaform->addElement('ValidationTextBox', 'mobile', array(
            'required'          => false,
            'label'             => 'Mobile Phone: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->phonenumber,
            'invalidMessage'    => 'Invalid characters in mobile phone number.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->phonenumber.'/')),
                                   ),
            'value'             => $this->getProperty('mobile'),                                   
        ));

        $uaform->addElement('ValidationTextBox', 'homephone', array(
            'required'          => false,
            'label'             => 'Home Phone: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->phonenumber,
            'invalidMessage'    => 'Invalid characters in home phone number field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->phonenumber.'/')),
                                   ),
            'value'             => $this->getProperty('homephone'),                                   
        ));

        $uaform->addElement('ValidationTextBox', 'telephonenumber', array(
            'required'          => false,
            'label'             => 'Office Phone: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->phonenumber,
            'invalidMessage'    => 'Invalid characters in office phone number field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->phonenumber.'/')),
                                   ),
            'value'             => $this->getProperty('telephonenumber'),                                   
        ));

        $uaform->addElement('SimpleTextarea', 'homepostaladdress', array(
            'required'          => false,
            'label'             => 'Home Address: ',
            'regExp'            => $regexLib->exp->postaladdress,
            'invalidMessage'    => 'Invalid characters in postal address field.',
            'style'             => 'width: 14.5em; height: 5em;',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->postaladdress.'/')),
                                   ),
            'value'             => $this->getProperty('homepostaladdress'),                                   
        ));
        
        $uaform->addElement('CheckBox', 'emsaccountlockout', array(
            'required'          => false,
            'label'             => 'Account Lockout: ',
            'checkedValue'      => '1',
            'uncheckedValue'    => '0',
            'value'           => $this->getProperty('emsaccountlockout'),
        ));

        $hf = new Zend_Form_Element_Hidden('userdn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $uaform->addElement($hf);
        // no implementation currently for csrf as it defaults to expire after 1 hop
        // need a good solution here rather than overriding zf libs.
        //$uaform->addElement($noCsrf);

        return $uaform;
    }

    public function getUserLoginForm($spass = null)
    {
        $regexLib = $this->_getRegexLibrary();
        
        // form hash (not currently implemented)
        $noCsrf = new Zend_Form_Element_Hash('no_csrf_userloginform');
        $noCsrf->setSalt(md5(rand(0,1000)));

        // remove decorations from hash element.
        $noCsrf->removeDecorator('label')
               ->removeDecorator('HtmlTag');

        $ulform = new Zend_Dojo_Form_SubForm();
        $ulform->setAttribs(array(
            'name'   => 'userloginform',
            'legend' => 'User Login Details',
            'dijitParams' => array(
                'title' => 'User Login Details',
            ),
        ));

        $ulform->addElement('ValidationTextBox', 'uid', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Login ID: ',
            'maxlength'         => 32,
            'value'             => $this->getProperty('uid'),
            'regExp'            => $regexLib->exp->uid,
            'invalidMessage'    => 'Specified login incorrect',
            'filters'           => array('StringTrim','StripTags'),
            'validators'        => array(
                                        array('StringLength', false, array('3','32')),
                                        array('Regex', true, array('/'.$regexLib->exp->uid.'/')),
                                   ),
        ));

        $ulform->addElement('PasswordTextBox', 'password', array(
            'required'          => true,
            'label'             => 'Password: ',
            'maxlength'         => 32,
            'value'             => '',
            'regExp'            => $regexLib->exp->postaladdress,
            'invalidMessage'    => 'Specified password too short',
            'filters'           => array('StringTrim','StripTags'),
            'validators'        => array(
                                        array('StringLength', false, array('6','32')),
                                        array('Identical', true, array($spass)),
                                   ),
        ));

        $ulform->addElement('PasswordTextBox', 'cpassword', array(
            'required'          => true,
            'label'             => 'Confirm password: ',
            'maxlength'         => 32,
            'value'             => '',
            'filters'           => array('StringTrim','StripTags'),
        ));
        
        
        /*
        // @todo: forceful password change on login field, and;
        //        account disable option
        $ulform->addElement('CheckBox', 'accountactive', array(
            'required'          => false,
            'label'             => 'Disable Account: ',
            'checkedValue'      => '1',
            'uncheckedValue'    => '0',
            'checked'           => false,
        ));
        */

        $hf = new Zend_Form_Element_Hidden('userdn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $ulform->addElement($hf);

        // CSRF checking is pending implementation.
        //$ulform->addElement($noCsrf);

        return $ulform;
    }

    public function getAvailableGroupsForm($dn = null)
    {
        if ($dn === null) {
            $dn = $this->getdn();
        }

        $agform = new Zend_Dojo_Form_SubForm();
        $agform->setAttribs(array(
            'name'          => 'getavailablegroupsform',
            'legend'        => 'Group Search',
            'dijitParams'   => array(
                'title' => 'Primary Group',
            ),
        ));

        $hf = new Zend_Form_Element_Hidden('userdn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $agform->addElement($hf);

        $agform->addElement('FilteringSelect', 'agsearch', array(
            'required'          => 'true',
            'invalidMessage'    => 'Group entry not found.',
            'label'             => 'Search for Group:',
            'storeId'           => 'autocompleter',
            'storeType'         => 'zivios.AutocompleteReadStore',
            'storeParams'       => array(
                    'url'           => '/user/getavailablegroups/dn/'.urlencode($dn).'/',
                    'requestMethod' => 'get',
                    ),
            'hasDownArrow'      => 'false',
        ));

        return $agform;
    }

    public function getDeleteUserForm()
    {
        // form hash (not currently implemented)
        $noCsrf = new Zend_Form_Element_Hash('no_csrf_userdeleteform');
        $noCsrf->setSalt(md5(rand(0,1000)));

        // remove decorations from hash element.
        $noCsrf->removeDecorator('label')
               ->removeDecorator('HtmlTag');

        $duform = new Zend_Dojo_Form_SubForm();
        $duform->setAttribs(array(
            'name'   => 'userdeleteform',
            'legend' => 'Delete User',
        ));

        $hf = new Zend_Form_Element_Hidden('userdn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $duform->addElement($hf);

        // CSRF checking is pending implementation.
        //$ulform->addElement($noCsrf);

        return $duform;
    }
    
    public function import($tgroup,$entry,$parentdn,$defaultprimarygroupdn)
    {
        $parentNode = Zivios_Ldap_Cache::loadDn($parentdn);
        
        $primarygid = $entry['gidnumber'][0];
        
        $searchgroup = new EMSGroup();
        $primaryGroup = $searchgroup->getGroupByGidNumber($primarygid);
        
        if ($primaryGroup == null) 
            $primaryGroup = Zivios_Ldap_Cache::loadDn($defaultprimarygroupdn);
        
        // Set user's primary group
        $this->setPrimaryGroup($primaryGroup);
        
        // Initialize kerberos & posix plugins
        $kerberosUser = $this->newPlugin('KerberosUser');
        $posixUser    = $this->newPlugin('PosixUser');
        
        $posixUser->import($entry);
        
        $this->setProperty('givenname',$entry['givenname'][0]);
        $this->setProperty('uid',$entry['uid'][0]);
        $this->setProperty('sn',$entry['sn'][0]);
        $this->setProperty('mobile',$entry['mobile'][0]);
        $this->setProperty('title',$entry['title'][0]);
        $this->setProperty('ou',$entry['ou'][0]);
        $this->setProperty('telephonenumber',$entry['telephonenumber'][0]);
        $this->setProperty('facsimiletelephonenumber',$entry['facsimiletelephonenumber'][0]);
        $this->setProperty('homephone',$entry['homephone'][0]);
        $this->setProperty('homepostaladdress',$entry['homepostaladdress'][0]);
        $this->setProperty('cn',$entry['givenname'][0].' '.$entry['sn'][0]);
        $this->setProperty('mail',$entry['mail'][0]);
        
        // not implementing password change as yet.
        $this->add($parentNode, $tgroup);
        $this->addPlugin($kerberosUser, $tgroup);
        $this->addPlugin($posixUser, $tgroup);
       
    }
}
