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
 * @package     mod_kerberos
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class KerberosUser extends Zivios_Plugin_User
{
    protected $_module = 'kerberos';
    private $_krbMasterService;

    /**
     * Instantiates the passed user object and add required krb ldap params
     *
     * @param EMSUser $userobj
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getAttrs()
    {
        $attr = parent::getAttrs();


        /**
         * Push Kerberos LDAP attributes to user obj.
         */
        $attr[] = 'krb5principalname';
        $attr[] = 'krb5keyversionnumber';
        $attr[] = 'krb5realmname';
        $attr[] = 'krb5encryptiontype';
        $attr[] = 'krb5kdcflags';
        $attr[] = 'krb5passwordend';
        $attr[] = 'krb5maxlife';
        $attr[] = 'krb5maxrenew';
        $attr[] = 'krb5kdcflags';
       // $attr[] = 'pwdaccountlockedtime';
       //  $attr[] = 'pwdattribute';

        return $attr;
    }

    /**
     * User password update in Kerberos
     *
     * @param string $password
     */

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);
        
        // Cancelling event listener for kerberos! This is handled via openldap now
        
        //$pm->addEventListener('CORE_USER_PWCHANGED',$this);
        //$pm->addEventListener('CORE_PCHANGE_EMSACCOUNTLOCKOUT',$this);
    }

    public function setKrbPassword($password, $getval=true)
    {
        if ($getval)
            $password = $password->getValue();

        if ($password == null || $password == "")
            throw new Zivios_Exception("Cannot update Kerberos Password to Null Value!");
        
        $uid = $this->getProperty('uid');
        $this->_initKrbService();

        /**
         * Log error silently.
         */
         /*
        if (!$this->_krbMasterService->setpw($uid, $password))
            Zivios_Log::error("Password Set Failed for uid: " .$uid." and password: $password");
        else
            Zivios_Log::info("Password changed successfully for uid: " . $uid);
        */
        
    }

    public function _initKrbService()
    {
        Zivios_Log::debug('init comm agent');

        if (!$this->_krbMasterService instanceof KerberosService) {
            $group = $this->getGroupPlugin();
            $this->_krbMasterService = $group->getService();
            Zivios_Log::debug('Kerberos Service Initialized.');
        }
    }

    /**
     * @param Zivios_Transaction_Handler $handler
     * @return Zivios_Transaction_Handler $handler
     */
    public function add(Zivios_Transaction_Group $group,$description=null)
    {

        /**
         * Set KRB Properties
         */
        $krb5Config = Zend_Registry::get('krbMaster');
        $uid = $this->getProperty('uid');

        /**
         * Set krb params in userobj
         */
        $this->setProperty('krb5principalname', $uid."@".$krb5Config->realm);
        $this->setProperty('krb5keyversionnumber','1');
        $this->setProperty('krb5realmname', $krb5Config->realm);
        $this->setProperty('krb5encryptiontype', $krb5Config->encryptiontype);
        $this->setProperty('krb5kdcflags', $krb5Config->kdcflags);
        $this->addObjectClass('krb5Realm');
        $this->addObjectClass('krb5Principal');
        $this->addObjectClass('krb5KDCEntry');
        
        //PPolicy hacks for now
        
       // $this->addObjectClass('pwdPolicy');
        //$this->setPropertY('pwdattribute','userPassword');

        return parent::add($group,$description);
    }

    public function getMainForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'krbmain',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Kerberos User Form',
            ),
        ));
        
        $date = new Zend_Date($this->getProperty('krb5passwordend'),Zend_Date::ISO_8601);
        Zivios_Log::debug("Date is :: ".$date->toString());
        Zivios_Log::debug("Max life : ".$this->getProperty('krb5maxlife'));
        Zivios_Log::debug("max renewals : ".$this->getProperty('krb5maxrenew'));
       
        $form->addElement('DateTextBox','passwordenddate', 
                          array(
                                'required'      => false,
                                'label'         => 'Expires at: ',
                                'invalidMessage' => 'Invalid Date Specified',
                                'value' =>  $date->toString('YYYY-MM-dd')
                                ));
       
        $form->addElement('NumberSpinner','krb5maxlife',
                        array(
                                'required' => false,
                                'label'     =>  'Max Life (days)',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 100,
                                'style'     => "width: 80px;",
                                'value'		=> 0
                                //'value'         => $this->getProperty('krb5maxlife')/86400
                            ));
 
        
        $form->addElement('NumberSpinner','krb5maxrenew',
                        array(
                                'required' => false,
                                'label'     =>  'Max Renewals',
                                'smallDelta' => 1,
                                'min'       => 0,
                                'max'       => 100,
                                'style'     => "width: 80px;",
                                'value' 	=> 0
                                //'value'         => $this->getProperty('krb5maxrenew')/86400
                            ));
                            
                 
        /**
        #krb5KDCFlagsSyntax SYNTAX ::= {
        #   WITH SYNTAX            INTEGER
        #--        initial(0),             -- require as-req
        #--        forwardable(1),         -- may issue forwardable
        #--        proxiable(2),           -- may issue proxiable
        #--        renewable(3),           -- may issue renewable
        #--        postdate(4),            -- may issue postdatable
        #--        server(5),              -- may be server
        #--        client(6),              -- may be client
        #--        invalid(7),             -- entry is invalid
        #--        require-preauth(8),     -- must use preauth
        #--        change-pw(9),           -- change password service
        #--        require-hwauth(10),     -- must use hwauth
        #--        ok-as-delegate(11),     -- as in TicketFlags
        #--        user-to-user(12),       -- may use user-to-user auth
        #--        immutable(13)           -- may not be deleted
        #   ID                     { 1.3.6.1.4.1.5322.10.0.1 }
        #}
        */

        
        $flags = $this->getProperty('krb5kdcflags');
        Zivios_Log::debug("Krb5 flags are ".$flags);
        /*
        $kclient = preg_match('/6/',$flags);
        if ($kclient) 
            $kclient = 6;
        
        $form->addElement('CheckBox', 'flag_kclient',     
                          array(
                                'label'          => 'Krb Client',
                                'checkedValue'   => '6',
                                'uncheckedValue' => '',
                                'value'          => $kclient,
                                ));
         
        $kserver = preg_match('/5/',$flags);
        if ($kserver) 
            $kserver = 5;
        
        $form->addElement('CheckBox', 'flag_kserver',     
                          array(
                                'label'          => 'Krb Server',
                                'checkedValue'   => '5',
                                'uncheckedValue' => '',
                                'value'          => $kserver,
                                ));
        
        $kpreauth = preg_match('/8/',$flags);
        if ($kpreauth) 
            $kpreauth = 8;
        
        $form->addElement('CheckBox', 'flag_preauth',     
                          array(
                                'label'          => 'Req PreAuth?',
                                'checkedValue'   => '8',
                                'uncheckedValue' => '',
                                'value'          => $kpreauth,
                                ));
        
        $kvalid = preg_match('/7/',$flags);
        if ($kvalid) 
            $kvalid = 7;
        else
        	$kvalid = 0;
        
        $form->addElement('CheckBox', 'flag_disable',     
                          array(
                                'label'          => 'Disable Account?',
                                'checkedValue'   => '7',
                                'uncheckedValue' => '0',
                                'value'          => $kvalid,
                                ));
        
        */
        
        $form->addElement('CheckBox','forcefulpassword',
                array(
                                'label'          => 'Forceful Password Change',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                
                                ));
                  
        $form->addElement('CheckBox','meow',
                array(
                                'label'          => 'Forceful Password Change',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                
                                ));
        return $form;
        
    }
    
    public function setLocked($lock=1)
    {
    	    $flags = $this->getProperty('krb5kdcflags');
    	    if ($lock) {
    	    	    if (!$this->isLocked()) {
    	    	    	    $flags = "7" . $flags;
    	    	    	    $this->setProperty('krb5kdcflags',$flags);
    	    	    }
    	    } else {
    	    	    $flags = str_replace('7','',$flags);
    	    	    Zivios_Log::debug("Unlocking Kerberos account, settings flags to ".$flags);
    	    	    $this->setProperty('krb5kdcflags',$flags);
    	    }
    }
    
    public function isLocked()
    {
    	    $flags = $this->getProperty('krb5kdcflags');
    	    return preg_match('/7/',$flags);
    }
    
    
    public function setMainForm($formvals)
    {
        $ignore = array('flag_kserver',
                    'flag_preauth','flag_kclient','flag_disable','forcefulpassword',
                    'passwordenddate','krb5maxrenew','krb5maxlife');
        
        $form = $this->getMainForm();
        $this->updateViaForm($form,$formvals,$ignore);
        $flags = array();
        $flags[] = $formvals['flag_disable'];
        $flags[] = $formvals['flag_preauth'];
        $flags[] = $formvals['flag_kserver'];
        $flags[] = $formvals['flag_kclient'];
        
        $this->setProperty('krb5kdcflags',implode('',$flags));
        $enddate = new Zend_Date($formvals['passwordenddate']);
        if ($formvals['forcefulpassword'] == 1) {
            $enddate = Zend_Date::now();
        }
        
        $this->setProperty('krb5passwordend',$enddate->get('YYYYMMdd').'000000Z');
        $this->setProperty('krb5maxrenew',$formvals['krb5maxrenew']*86400);
        $this->setProperty('krb5maxlife',$formvals['krb5maxlife']*86400);
    }
    
    
    public function eventAction($eventname,Zivios_Transaction_Group $tgroup)
    {
        Zivios_Log::debug("Event Action called with event $eventname");
        if ($eventname == 'CORE_USER_PWCHANGED') {
            $this->_setKrbPassword($tgroup,'Changing Kerberos Password for User',$this->_userobj->getNewPassword(), false);
        } else if ($eventname == 'CORE_PCHANGE_EMSACCOUNTLOCKOUT') {
            $this->setLocked($this->getProperty('emsaccountlockout'));
            $this->update($tgroup);
        }
    }
    
    public function setAccountLockout($lockoutval,Zivios_Transaction_Group $tgroup)
    {
        $flags = $this->getProperty('krb5kdcflags');
        if ($lockoutval == 1) {
            $enddate = Zend_Date::now();
            $enddate = $enddate->get('YYYYMMdd').'000000Z';
            if (!preg_match('/7/',$flags))
                $flags = '7'.$flags;
        } else {
            $enddate = '';
            if (preg_match('/7/',$flags))
                $flags = str_replace('7','',$flags);
        }
        $this->setProperty('krb5kdcflags',$flags);
        //$this->setProperty('pwdaccountlockedtime',$enddate);
        $this->update($tgroup);
    }

    public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function generateContextMenu()
    {}
}
