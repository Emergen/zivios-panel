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


class SambaService extends EMSService
{
    protected $_module = 'samba';

    private $newrootpass;
    
    public function __construct($dn=null,$attrs=null,$acl=null)
    {

        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'sambadomainname';
        $attrs[] = 'sambanextrid';
        $attrs[] = 'sambasid';
        $attrs[] = 'sambalockoutthreshold';
        $attrs[] = 'sambaminpwdlength';
        $attrs[] = 'sambapwdhistorylength';
        $attrs[] = 'sambamaxpwdage';
        $attrs[] = 'sambalockoutduration';
        $attrs[] = 'sambaforcelogoff';
        $attrs[] = 'sambaminpwdage';
        $attrs[] = 'sambalogontochgpwd'; // 0 off 2 = On
        
        //Samba Config file management
        $attrs[] = 'emssambacfgnetbiosname';
        $attrs[] = 'emssambacfgloglevel';
        $attrs[] = 'emssambacfgtimeserver';
        $attrs[] = 'emssambacfgwinssupport';
        $attrs[] = 'emssambacfgldapbase';
        $attrs[] = 'emssambacfgldapadmindn';
        $attrs[] = 'emssambacfgldapserver';
        $attrs[] = 'emssambacfgshareincludefilepath';
        $attrs[] = 'emssambacfgnetlogonpath';
        $attrs[] = 'emssambacfgprofilepath';
        $attrs[] = 'uidnumber';
        $attrs[] = 'gidnumber';
        

        parent::__construct($dn,$attrs,$acl);
        // Need to set the MasterComputerdn here!
    }

    private function setStaticValues()
    {
        $ldapconf = Zend_Registry::get('ldapConfig');
        $base = $ldapconf->basedn;
        $host = $ldapconf->host;
        // TODO: Remove this hardcoding quackery
        $admindn = $ldapconf->admindnprefix . "," . $base;
        $this->setProperty('emssambacfgldapserver',$host);
        $this->setProperty('emssambacfgldapbase',$base);
        $this->setProperty('emssambacfgldapadmindn',$admindn);
    }

    /*
    * The Samba object class requires the following attributes:
    * {3}( 1.3.6.1.4.1.7165.2.2.5 NAME 'sambaDomain' DESC 'Samba Domain Information' SUP top STRUCTURAL MUST
    *       ( sambaDomainName $ sambaSID ) MAY ( sambaNextRid $ sambaNextGroupRid $ sambaNextUserRid $ 
    *           sambaAlgorithmicRidBase $ sambaMinPwdLength $ sambaPwdHistoryLength $ sambaLogonToChgPwd $ 
    *           sambaMaxPwdAge $ sambaMinPwdAge $ sambaLockoutDuration $ sambaLockoutObservationWindow $
    *           sambaLockoutThreshold $ sambaForceLogoff $ sambaRefuseMachinePwdChange ) )
    *
    
     $sambaService->setProperty('emsmastercomputerdn',on->mastercompdn);
        $sambaService->setProperty('sambasid',$this->json->sambasid);
        $sambaService->setProperty('sambadomainname',$this->json->sambadomainname);
        if (isset($this->json->sambatimesupport)) $ts = 'yes'; else $ts = 'no';
        if (isset($this->json->sambawinssupport)) $ws = 'yes'; else $ws = 'no';

        $sambaService->setProperty('emssambacfgnetbiosname',$this->json->sambanetbiosname);
        $sambaService->setProperty('emssambacfgloglevel',$this->json->sambaloglevel);
        $sambaService->setProperty('emssambacfgtimeserver',$ts);
        $sambaService->setProperty('emssambacfgwinssupport',$ws);
        $sambaService->setProperty('emssambacfgnetlogonpath',$this->json->sambanetlogonpath);
        $sambaService->setProperty('emssambacfgprofilepath',$this->json->sambaprofilepath);
    */
    
    public function setMainForm($data,$parentobj)
    {
        $form = $this->getMainForm($parentobj);
        
        if  ($data['password'] != $data['cpassword'])
            throw new Zivios_Error("Passwords do not match!");
        else
            $this->newrootpass = $data['password'];
        
        $data['sambalockoutduration'] *= 60;
        $data['sambamaxpwdage'] *= 86400;
        $data['sambaminpwdage'] *= 86400;
        $this->updateViaForm($form,$data,array('password','cpassword'));
        
    }
    
    public function setMainEditForm($data)
    {
        $data['sambalockoutduration'] *= 60;
        $data['sambamaxpwdage'] *= 86400;
        $data['sambaminpwdage'] *= 86400;
        
        $form = $this->getMainEditForm();
        $this->updateViaForm($form,$data);
    }
    
    
    public function getMainEditForm()
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
        
        $form->addElement('ValidationTextBox','sambadomainname', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumnospaces,
                                'title'         => 'Domain Name',
                                'label'         => 'Domain Name: ',
                                'invalidMessage' => 'Invalid Domain Name Specified',
                                'value'         => $this->getProperty('sambadomainname')
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgnetbiosname', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumwithspaces,
                                'title'         => 'NetBIOS Name',
                                'label'         => 'NetBIOS Name: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified',
                                'value'         => $this->getProperty('emssambacfgnetbiosname')
                                ));
        
        $form->addElement('CheckBox', 'emssambacfgtimeserver',     
                          array(
                                'label'          => 'NTP Support?',
                                'checkedValue'   => 'yes',
                                'uncheckedValue' => 'no',
                                'value'         => $this->getProperty('emssambacfgtimeserver')
                                ));
        
        $form->addElement('CheckBox', 'emssambacfgwinssupport',     
                          array(
                                'label'          => 'WINS Support?',
                                'checkedValue'   => 'yes',
                                'uncheckedValue' => 'no',
                                'value'         => $this->getProperty('emssambacfgwinssupport')
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgnetlogonpath', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Path',
                                'label'         => 'Logon Path: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified',
                                'value'         => $this->getProperty('emssambacfgnetlogonpath')
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgprofilepath', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Profilepath',
                                'label'         => 'Profile Path: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified',
                                'value'         => $this->getProperty('emssambacfgprofilepath')
                                ));
       
        $form->addElement('NumberSpinner','sambalockoutduration',
                        array(
                                'label'     =>  'Account Lockout (min.)',
                                'smallDelta' => 15,
                                'min'       => 15,
                                'max'       => 5000,
                                'style'     => "width: 100px;",
                                'value'         => $this->getProperty('sambalockoutduration')/60
                            ));
        
        $form->addElement('NumberSpinner','sambamaxpwdage',
                        array(
                                'label'     =>  'Max Password Age (days)',
                                'smallDelta' => 10,
                                'min'       => -1,
                                'max'       => 1000,
                                'style'     => "width: 100px;",
                                'value'         => $this->getProperty('sambamaxpwdage')/86400
                            ));
        
        $form->addElement('NumberSpinner','sambaminpwdage',
                        array(
                                'label'     =>  'Min Password Age (days)',
                                'smallDelta' => 10,
                                'min'       => -1,
                                'max'       => 1000,
                                'style'     => "width: 100px;",
                                'value'         => $this->getProperty('sambaminpwdage')/86400
                            ));
        
        $form->addElement('NumberSpinner','sambapwdhistorylength',
                        array(
                                'label'     =>  'Password History Length',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 10,
                                'style'     => "width: 100px;",
                                'value'         => $this->getProperty('sambapwdhistorylength')
                            ));
        
        $form->addElement('NumberSpinner','sambalockoutthreshold',
                        array(
                                'label'     =>  'Bad logins before Lockout',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 10,
                                'style'     => "width: 100px;",
                                'value'         => $this->getProperty('sambalockoutthreshold')
                            ));
        
        $form->addElement('CheckBox', 'sambalogontochgpwd',     
                          array(
                                'label'          => 'Force password change for all users (careful)?',
                                'checkedValue'   => '2',
                                'uncheckedValue' => '',
                                'value'         => $this->getProperty('sambalogontochgpwd')
                                ));
        return $form;
        
    }
    
    public function getMainForm($parentObject)
    {
        
        $regexLib = $this->_getRegexLibrary();
        Zivios_Log::debug($regexLib);
        $computers = $this->_getCompatibleComputers($parentObject);
        $compArray = array();;
        foreach ($computers as $computer) {
            $compArray[$computer->getdn()] = $computer->getProperty('cn');
        }
        $compArray = array('-1' => '<Select Server>') + $compArray;
        
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'sambaaddservice',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Adding Samba Service',
            ),
        ));
        $form->addElement('ValidationTextBox','sambasid', 
                          array(
                                'required'      => true,
                                'regExp'        => "^[\w\d\-]+$",
                                'title'         => 'Domain SID',
                                'label'         => 'Domain SID: ',
                                'invalidMessage' => 'Invalid SID Specified',
                                'value'         => 'S-1-0-0'
                                ));
        
        $form->addElement('ValidationTextBox','sambadomainname', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->hostname,
                                'title'         => 'Domain Name',
                                'label'         => 'Domain Name: ',
                                'invalidMessage' => 'Invalid Domain Name Specified'
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgnetbiosname', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumwithspaces,
                                'title'         => 'NetBIOS Name',
                                'label'         => 'NetBIOS Name: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified'
                                ));
        
        $form->addElement('CheckBox', 'emssambacfgtimeserver',     
                          array(
                                'label'          => 'NTP Support?',
                                'checkedValue'   => 'yes',
                                'uncheckedValue' => 'no'
                                ));
        
        $form->addElement('CheckBox', 'emssambacfgwinssupport',     
                          array(
                                'label'          => 'WINS Support?',
                                'checkedValue'   => 'yes',
                                'uncheckedValue' => 'no'
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgnetlogonpath', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Logon Path',
                                'label'         => 'Logon Path: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified'
                                ));
        
        $form->addElement('ValidationTextBox','emssambacfgprofilepath', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->homedirectory,
                                'title'         => 'Profilepath',
                                'label'         => 'Profile Path: ',
                                'invalidMessage' => 'Invalid Netvios Name Specified'
                                ));
       
         $form->addElement('PasswordTextBox', 'password', array(
            'required'          => true,
            'label'             => 'Adminstrator Password: ',
            'maxlength'         => 32,
            'value'             => '',
            'regExp'            => $regexLib->exp->postaladdress,
            'invalidMessage'    => 'Specified password too short',
            'filters'           => array('StringTrim','StripTags'),
            'validators'        => array(
                                        array('StringLength', false, array('6','32'))
                                   ),
        ));
         
         $form->addElement('PasswordTextBox', 'cpassword', array(
            'required'          => true,
            'label'             => 'Confirm password: ',
            'maxlength'         => 32,
            'value'             => '',
            'filters'           => array('StringTrim','StripTags'),
        ));
         
        

        $form->addElement('NumberSpinner','sambalockoutduration',
                        array(
                                'label'     =>  'Account Lockout (min.)',
                                'smallDelta' => 15,
                                'min'       => 15,
                                'max'       => 5000,
                                'style'     => "width: 100px;",
                                'value'     => 30
                            ));
        
        $form->addElement('NumberSpinner','sambamaxpwdage',
                        array(
                                'label'     =>  'Max Password Age (days)',
                                'smallDelta' => 10,
                                'min'       => -1,
                                'max'       => 1000,
                                'style'     => "width: 100px;",
                                'value'     => 30
                            ));
        
        $form->addElement('NumberSpinner','sambaminpwdage',
                        array(
                                'label'     =>  'Min Password Age (days)',
                                'smallDelta' => 10,
                                'min'       => -1,
                                'max'       => 1000,
                                'style'     => "width: 100px;",
                                'value'     => 5
                            ));
        
        $form->addElement('NumberSpinner','sambapwdhistorylength',
                        array(
                                'label'     =>  'Password History Length',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 10,
                                'style'     => "width: 100px;",
                                'value'     => 3
                            ));
        
        $form->addElement('NumberSpinner','sambalockoutthreshold',
                        array(
                                'label'     =>  'Bad logins before Lockout',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 10,
                                'style'     => "width: 100px;",
                                'value'     => 5
                            ));
        
        $form->addElement('NumberSpinner','uidnumber',
                        array(
                                'label'     =>  'UID Start at',
                                'smallDelta' => 100,
                                'min'       => 1000,
                                'max'       => 20000,
                                'style'     => "width: 100px;",
                                'value'         => 5000
                            ));
        
        $form->addElement('NumberSpinner','gidnumber',
                        array(
                                'label'     =>  'GID Start',
                                'smallDelta' => 100,
                                'min'       => 1000,
                                'max'       => 20000,
                                'style'     => "width: 100px;",
                                'value'         => 4000
                            ));
        
        $form->addElement('CheckBox', 'sambalogontochgpwd',     
                          array(
                                'label'          => 'Force password change on first login',
                                'checkedValue'   => '2',
                                'uncheckedValue' => '',
                                'value' => ''
                                ));
        
        
        $form->addElement('FilteringSelect', 'emsmastercomputerdn', Array(
                'required'      => true,
                'multiOptions'  => $compArray,
                'regExp'        => $regexLib->exp->hostname,
                'title'         => 'Select Master Server',
                'label'         => 'Select Master Server',
                'invalidMessage'    => 'Invalid characters in hostname field.',
                'filters'           => array('StringTrim'),
                'autocomplete'  => false
        ));
        
        return $form;
        
    }
    
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup)
    {
        $this->setStaticValues();
        $this->addObjectClass('sambadomain');
       // $this->addObjectClass('sambaunixidpool');
        $this->addObjectClass('emssambaservice');

        $this->setProperty('cn','Zivios Samba Service');
        $this->setProperty('emsdescription','Zivios Samba Service');
        $this->setProperty('sambanextrid',1001);


        if ($this->getProperty('sambasid') == NULL)
            $this->setProperty('sambasid','S-1-0-0');

        
        /**
        * Add samba default groups
        * Samba Default Groups include:
        * Legend:
        * cn,group type,RID
        * Account Operators,5,548,S-1-5-32
        * Administrators,5,544 S-1-5-32
        * Backup Operators,5,551 S-1-5-32
        * Domain Admins,2,512
        * Domain Computers,2,515
        * Domain Guests,2,514
        * Domain Users,2,513
        * Print Operators,5,550 S-1-5-32
        * Replicators,5,552 S-1-5-32
        * Default Users:
        * Legend:
        * uid,uidnumber,gidnumber,rid

        * nobody,999,514,2998
        * root,0,0,500
        *
        */

        $rootpass = $this->json->rootpass;
        parent::add($parent,$tgroup);
        
        $ldapconf = Zend_Registry::get('ldapConfig');
        $base = $ldapconf->basedn;

        $zusersdn = "ou=zUsers,ou=Core Control,ou=Zivios,$base";
        $zgroupsdn = "ou=zGroups,ou=Core Control,ou=Zivios,$base";

        $zusersobj = Zivios_Ldap_Cache::loadDn($zusersdn);

        $zgroupsobj = Zivios_Ldap_Cache::loadDn($zgroupsdn);

        // Make Samba Default Groups!!!

        $this->addSambaGroup('Account Operators',5,548,"S-1-5-32-548",$zgroupsobj,$tgroup);
        $this->addSambaGroup('Administrators',5,544,"S-1-5-32-544",$zgroupsobj,$tgroup);
        $this->addSambaGroup('Backup Operators',5,551,"S-1-5-32-551",$zgroupsobj,$tgroup);
        $domainadminsobj = " ";
        $this->addSambaGroup('Domain Admins',2,512,null,$zgroupsobj,$tgroup,$domainadminsobj);
        $this->addSambaGroup('Domain Computers',5,515,null,$zgroupsobj,$tgroup);

        $domainguestsobj = " ";
        $this->addSambaGroup('Domain Guests',2,514,null,$zgroupsobj,$tgroup,$domainguestsobj);
        $this->addSambaGroup('Domain Users',2,513,null,$zgroupsobj,$tgroup);
        $this->addSambaGroup('Print Operators',5,550,"S-1-5-32-550",$zgroupsobj,$tgroup);
        $this->addSambaGroup('Replicators',5,552,"S-1-5-32-552",$zgroupsobj,$tgroup);

        Zivios_Log::debug("domainguestsobj is ".$domainguestsobj->getdn());

        //Make Samba Default Users!!!

        $this->addSambaUser('nobody',999,514,2998,$domainguestsobj,'NOPASSWORD',$zusersobj,$tgroup);
        $this->addSambaUser('root',0,0,500,$domainadminsobj,$this->newrootpass,$zusersobj,$tgroup);

        // automatically also add a idmap subobject

        $idmap = new EMSOrganizationalUnit();
        $idmap->init();

        $idmap->setProperty('cn','idmap');
        $idmap->setProperty('emsdescription','Samba IDMap Object');
        $idmap->setProperty('emstype',EMSObject::TYPE_IGNORE);
        $idmap->addObjectClass('emsignore');
        $idmap->add($this,$tgroup);
        
        $this->_updateCfg($tgroup,'Updating Configuration on Host');
        
        //TODO: Automatically send smb.conf to the COMPUTERS!!

        return $tgroup;

    }
    
    private function addSambaGroup($cn,$type,$gid,$sid,$parent,$tgroup,&$groupmade=null)
    {
        $group = new EMSGroup();
        $group->init();
        $group->setProperty('emsdescription','Samba Builtin Group');
        $group->setProperty('cn',$cn);

        $group->add($parent,$tgroup);
        $groupmade = $group;
        $posixplug = $group->newPlugin("PosixGroup");
        $posixplug->setProperty('gidnumber',$gid);

        $group->addPlugin($posixplug,$tgroup);

        $kerberosplug = $group->newPlugin("KerberosGroup");
        $kerberosplug->linkToService($kerberosplug->getMasterService());

        $group->addPlugin($kerberosplug,$tgroup);

        $sambaplug = $group->newPlugin("SambaGroup");;
        $sambaplug->setProperty('sambagrouptype',$type);
        $sambaplug->setRid($gid);
        if ($sid != null) {
            $sambaplug->setProperty('sambasid',$sid);
        }
        
        $sambaplug->linkToService($this);
        $group->addPlugin($sambaplug,$tgroup);
        return $tgroup;
    }


    private function addSambaUser($uid,$uidnumber,$gidnumber,$rid,$pgroupobj,$password,$parent,$tgroup)
    {
        $user = new EMSUser();
        $user->init();
        $user->setProperty('emsdescription','Samba Builtin User');
        $user->setProperty('uid',$uid);
        $user->setProperty('cn',$uid);
        $user->setProperty('givenname',$uid);
        $user->setProperty('sn','SambaInternal');
        $user->setPrimaryGroup($pgroupobj);
        $user->add($parent,$tgroup);

        $posixplug = $user->newPlugin("PosixUser");
        $posixplug->setProperty('uidnumber',$uidnumber);
        $posixplug->setProperty('gidnumber',$gidnumber);
        $posixplug->setProperty('homedirectory','/nowhere');


        $kerberosplug = $user->newPlugin("KerberosUser");
        $user->addPlugin($posixplug,$tgroup);
        $user->addPlugin($kerberosplug,$tgroup);
        $sambaplug = $user->newPlugin("SambaUser");
        $sambaplug->setRid($rid);
        $sambaplug->setProperty('sambaacctflags','[U          ]');

        $user->addPlugin($sambaplug,$tgroup);
        Zivios_Log::debug("Setting Password!");
        $user->changePassword($password,$tgroup);
        return $tgroup;
    }
    
    public function update(Zivios_Transaction_Group $tgroup)
    {
        $this->setStaticValues();
        parent::update($tgroup);
        $this->_updateCfg($tgroup,'Updating Samba configuration file on remote system');
        $this->_stopService($tgroup,'Stopping Samba');
        $this->_startService($tgroup,'Starting Samba');
        return $tgroup;
    }

    

    public function updateCfg()
    {

        $file = $this->renderConfig();
        $this->_setMasterComputerConfig();

        $shares = $this->getAllShares();
        foreach ($shares as $share) {
            $file .= "\n".$share->renderCfg();
        }
        $this->mastercomp->putFileFromString($file,$this->_compConfig->smbconffile);


    }

    public function getAllShares()
    {
        return $this->getAllChildren('(objectclass=emssambashare)',null,'SUB');
    }


    public function renderConfig()
    {
        $appConfig = Zend_Registry::get('appConfig');
        $tmplfile = $appConfig->modules . '/' . $this->_module . '/config/smb.conf.tmpl';
        $valarray = array ('sambadomainname' => $this->getProperty('sambadomainname'),
                           'emssambacfgnetbiosname' => $this->getProperty('emssambacfgnetbiosname'),
                           'emssambacfgloglevel' => $this->getProperty('emssambacfgloglevel'),
                           'emssambacfgtimeserver' => $this->getProperty('emssambacfgtimeserver'),
                           'emssambacfgwinssupport' => $this->getProperty('emssambacfgwinssupport'),
                           'emssambacfgldapserver' => $this->getProperty('emssambacfgldapserver'),
                           'emssambacfgldapbase' => $this->getProperty('emssambacfgldapbase'),
                           'emssambacfgldapadmindn' => $this->getProperty('emssambacfgldapadmindn'),
                           'emssambacfgnetlogonpath' => $this->getProperty('emssambacfgnetlogonpath'),
                           'emssambacfgprofilepath' => $this->getProperty('emssambacfgprofilepath'));


        return Zivios_Util::renderTmplToCfg($tmplfile,$valarray);

    }



    public function getStatus()
    {
        $agent = $this->_initCommAgent();
        return $agent->getstatus();
    }


    public function startService()
    {
        $agent = $this->_initCommAgent();
        return $agent->startservice();
    }

    public function stopService()
    {
        $agent = $this->_initCommAgent();
        return $agent->stopservice();
    }



    /*public function generateContextMenu()
    {


    }*/

    public function getrdn()
    {
        return 'sambadomainname';
    }

}

