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
 * @package		mod_samba
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id$
 * @lastchangeddate $LastChangedDate$
 **/

class SambaUser extends Zivios_Plugin_User
{
    protected $_module = 'samba';
    public $sambarid = null;

    public function init(EMSPluginManager $pm) {
        parent::init($pm);
        $this->_pmobj->addEventListener('CORE_USER_PWCHANGED',$this);
        $this->_pmobj->addEventListener('CORE_PCHANGE_EMSACCOUNTLOCKOUT',$this);
    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();

        $attrs[] = 'sambaacctflags';
        $attrs[] = 'sambahomedrive';
        $attrs[] = 'sambahomepath';
        $attrs[] = 'sambakickofftime';
        $attrs[] = 'sambalmpassword';
        $attrs[] = 'sambantpassword';
        $attrs[] = 'sambaprofilepath';
        $attrs[] = 'sambalogofftime';
        $attrs[] = 'sambalogontime';
        $attrs[] = 'sambalogonhours';
        $attrs[] = 'sambauserworkstations';
        $attrs[] = 'sambalogonscript';
        $attrs[] = 'sambapwdcanchange';
        $attrs[] = 'sambaprimarygroupsid';
        $attrs[] = 'sambapwdlastset';
        $attrs[] = 'sambapwdmustchange';
        $attrs[] = 'sambasid';
        $attrs[] = 'sambabadpasswordcount';
        $attrs[] = 'sambabadpasswordtime';
        $attrs[] = 'sambapasswordhistory';
        


        return $attrs;


    }
    public function setRid($rid)
    {
        $this->sambarid = $rid;
    }

    public function eventAction($event,Zivios_Transaction_Group $tgroup)
    {
        if ($event == 'CORE_USER_PWCHANGED') {
            $enddate = Zend_Date::now();
            $this->setProperty('sambapwdlastset',$enddate->get(Zend_Date::TIMESTAMP));
            $this->update($tgroup);
        } else if ($event == 'CORE_PCHANGE_EMSACCOUNTLOCKOUT') {
            $lockout = $this->getProperty('emsaccountlockout');
            if ($lockout) {
                $this->disableAccount();
                $this->update($tgroup,'Disabling Samba Plugin');
            }
            else {
                $this->enableAccount();
                $this->update($tgroup,'Enabling Samba Plugin');
            }
        }
    }


    public function getGroupPluginName()
	{
		return 'SambaGroup';
	}

    public function add(Zivios_Transaction_Group $tgroup,$description=null)
    {
        if (!$this->_userobj->hasModule('posix'))
            throw new Zivios_Exception("Samba User Requires Module Posix!");
        if (!$this->_userobj->hasModule('kerberos'))
            throw new Zivios_Exception("Samba User Requires Module kerberos!");

        $this->addObjectClass('sambasamaccount');

        $sambaservice = $this->getService();
        $sid = $sambaservice->getProperty('sambasid');

        if ($this->sambarid == null) {
            $uid = $this->getProperty('uidnumber');
            $rid = (((int)$uid) * 2) + 1000; // SAMBA Quackery
        } else $rid = $this->sambarid;

        $this->setProperty('sambasid',"$sid-$rid");

        $group = $this->_userobj->getPrimaryGroup();
        $groupsid = $group->getProperty('sambasid');

        $this->setProperty('sambaprimarygroupsid',$groupsid);
        $this->setProperty('sambaacctflags','[U          ]');


        return parent::add($tgroup,$description);


    }
    
    public function setMainForm($formdata)
    {
        $form = $this->getMainForm();
        $password = $formdata['password'];
        $ignorevals = array('password','passwordcheck');
        if ($password != $formdata['passwordcheck']) {
            throw new Zivios_Error('Passwords do NOT match');
        }
        
        $this->updateViaForm($form,$formdata,$ignorevals);
        
    }
    
    public function disableAccount()
    {
        $flags = $this->getFlagArray();
        $flags[] = 'D';
        $this->setProperty('sambaacctflags',"[".implode("",array_unique($flags))."]");
    }
    
    public function getFlagArray()
    {
        $flags = $this->getProperty('sambaacctflags');
        $flags = substr($flags,1,-1);
        $flags = str_split($flags);
        return $flags;
    }
    
    public function enableAccount()
    {
        $flags = $this->getFlagArray();
        $key = array_search('D',$flags);
        if ($key !== FALSE) {
            unset($flags[$key]);
            $this->setProperty('sambaacctflags',"[".implode("",array_unique($flags))."]");
        }
    }
    
    
    /*
    * {0}( 1.3.6.1.4.1.7165.2.2.6 NAME 'sambaSamAccount' DESC 'Samba 3.0 Auxilary SAM Account' SUP top AUXILIARY
    * MUST ( uid $ sambaSID ) MAY ( cn $ sambaLMPassword $ sambaNTPassword $ sambaPwdLastSet $ sambaLogonTime $
    * sambaLogoffTime $ sambaKickoffTime $ sambaPwdCanChange $ sambaPwdMustChange $ sambaAcctFlags $ 
    * displayName $ sambaHomePath $ sambaHomeDrive $ sambaLogonScript $ sambaProfilePath $ description
    * $ sambaUserWorkstations $ sambaPrimaryGroupSID $ sambaDomainName $ sambaMungedDial $ sambaBadPasswordCount
    * $ sambaBadPasswordTime $ sambaPasswordHistory $ sambaLogonHours ) )
    */
    
    public function getMainForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'sambaedit',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Editing Samba Service',
            ),
        ));
        
        $form->addElement('ValidationTextBox','sambalogonscript', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Script Path:',
                                'label'         => 'Logon Script Path: ',
                                'invalidMessage' => 'Invalid Path Specified',
                                
                                ));
        
        $form->addElement('ValidationTextBox','sambaprofilepath', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Profile Path:',
                                'label'         => 'Profile Path: ',
                                'invalidMessage' => 'Invalid Path Specified',
                                
                                ));
        
        
        $form->addElement('ValidationTextBox','sambalogonhours', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Hours:',
                                'label'         => 'Logon Hours:',
                                'invalidMessage' => 'Invalid Path Specified',
                                'disabled'      => true,
                                
                                ));
        
        
        $form->addElement('ValidationTextBox','sambauserworkstations', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => '',
                                'label'         => 'Allowed Workstations: ',
                                'invalidMessage' => 'Invalid Workstation Specified',
                                'disabled'      => true
                                ));
        
        $form->addElement('ValidationTextBox','password', 
                          array(
                                'description'   => 'The samba plugin MUST reset the password of the user'.
                                                    ' please enter a new password here.',
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumnospaces,
                                'label'         => 'Password: ',
                                'invalidMessage' => 'Invalid Password Specified',
                                ));
        $form->getElement('password')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));
        
        $form->addElement('ValidationTextBox','passwordcheck', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumnospaces,
                                'label'         => 'Confirm Password: ',
                                'invalidMessage' => 'Invalid Password Specified',
                                ));
        
         $form->addElement('CheckBox', 'sambapwdlastset',     
                          array(
                                'label'          => 'Force Password Change?',
                                'checkedValue'   => '0',
                                'uncheckedValue' => '',
                                'value'          => '0'
                                ));
         
         return $form;
         
    }

    public function getEditForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'sambaedit',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Editing Samba Service',
            ),
        ));
        
        $form->addElement('ValidationTextBox','sambalogonscript', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Script Path:',
                                'label'         => 'Logon Script Path: ',
                                'invalidMessage' => 'Invalid Path Specified',
                                'value'         => $this->getProperty('sambalogonscript')
                                ));
        
        $form->addElement('ValidationTextBox','sambaprofilepath', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Profile Path:',
                                'label'         => 'Profile Path: ',
                                'invalidMessage' => 'Invalid Path Specified',
                                'value'         => $this->getProperty('sambaprofilepath')
                                ));
        
        
        $form->addElement('ValidationTextBox','sambalogonhours', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Hours:',
                                'label'         => 'Logon Hours:',
                                'invalidMessage' => 'Invalid Path Specified',
                                'disabled'      => true,
                                'value'         => $this->getProperty('sambalogonhours')
                                ));
        
        
        $form->addElement('ValidationTextBox','sambauserworkstations', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => '',
                                'label'         => 'Allowed Workstations: ',
                                'invalidMessage' => 'Invalid Workstation Specified',
                                'value'         => $this->getProperty('sambauserworkstations'),
                                'disabled'      => true
                                ));
        
         $form->addElement('CheckBox', 'sambapwdlastset',     
                          array(
                                'label'          => 'Force Password Change?',
                                'checkedValue'   => '0',
                                'uncheckedValue' => '',
                                'value'          => ''
                                ));

         $form->addElement('CheckBox', 'disableuser',     
                          array(
                                'label'          => 'Account Disabled?',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'          => $this->hasFlag('D')
                                ));

         $form->addElement('CheckBox', 'lockaccount',     
                          array(
                                'label'          => 'Account Locked?',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'          => $this->hasFlag('L')
                                ));                  
         
         $form->addElement('CheckBox', 'flushbadpw',     
                          array(
                                'label'          => 'Flush Bad Password History?',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'          => '0'
                                ));                  
         return $form;
         
    }
    
    public function hasFlag($flag)
    {
        $flags = $this->getFlagArray();
        return in_array($flag,$flags);
    }
    
    public function setFlag($flag)
    {
        if (!$this->hasFlag($flag)) {
            $flagarr = $this->getFlagArray();
            $flagarr[] = $flag;
            $this->setProperty('sambaacctflags',"[".implode("",$flagarr)."]");
        }
    }
    
    public function removeFlag($flag)
    {
        if ($this->hasFlag($flag)) {
            $flags = $this->getFlagArray();
            $key = array_search($flag,$flags);
            if ($key !== FALSE) {
                unset($flags[$key]);
                $this->setProperty('sambaacctflags',"[".implode("",array_unique($flags))."]");
            }
        }
    }
    
    public function setEditForm($formdata)
    {
        
        $form = $this->getEditForm();
        $ignorevals = array('disableuser','lockaccount','flushbadpw');
        
        $this->updateViaForm($form,$formdata,$ignorevals);
        $disable = $formdata['disableuser'];
        $lockout = $formdata['lockaccount'];
        $flushbadpw = $formdata['flushbadpw'];
        
        if ($disable)
            $this->setFlag('D');
        else
            $this->removeFlag('D');
            
        if ($lockout)
            $this->setFlag('L');
        else
            $this->removeFlag('L');
            
        if ($flushbadpw) {
            $this->setProperty('sambabadpasswordcount',null);
            $this->setProperty('sambabadpasswordtime',null);
        }
        
    }
    
    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->removeObjectClass('sambasamaccount');
        
        $this->setProperty('sambaacctflags',null);
        $this->setProperty('sambahomedrive',null);
        $this->setProperty('sambahomepath',null);
        $this->setProperty('sambakickofftime',null);
        $this->setProperty('sambalmpassword',null);
        $this->setProperty('sambantpassword',null);
        $this->setProperty('sambaprofilepath',null);
        $this->setProperty('sambalogofftime',null);
        $this->setProperty('sambalogontime',null);
        $this->setProperty('sambalogonscript',null);
        $this->setProperty('sambalogonhours',null);
        $this->setProperty('sambapwdcanchange',null);
        $this->setProperty('sambauserworkstations',null);
        $this->setProperty('sambaprimarygroupsid',null);
        $this->setProperty('sambapwdlastset',null);
        $this->setProperty('sambapwdmustchange',null);
        $this->setProperty('sambasid',null);
        $this->setProperty('sambapasswordhistory',null);
        
        return parent::delete($tgroup,$description);
    }


    public function returnDisplayName()
	{
		return "Samba Plugin";
	}

	public function returnModuleName()
	{
		return "samba";
	}

    public function generateContextMenu()
    {
        return false;
    }


    public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

	public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}


}
