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
 * @package     mod_mail
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class MailUser extends Zivios_Plugin_User
{
    protected $_module = 'mail';

    const MAIL_ACTIVE='1';
    const MAIL_INACTIVE='0';

    private $cyrmboxroot;


    public function getAttrs()
    {
        $attrs = parent::getAttrs();
        $attrs[] = 'emsmailactive';
        $attrs[] = 'emscyrusmboxroot';
        $attrs[] = 'emspostfixsecurityclass';

        /**
         * Adding params & validators
         */
        $attrs[] = 'mail';
        $attrs[] = 'mailalternateaddress';
        $attrs[] = 'emsmailmboxquota';
        $attrs[] = 'amaviswhitelistsender';
        $attrs[] = 'amavisblacklistsender';
        $attrs[] = 'amavisspamkilllevel';
        $attrs[] = 'amavismessagesizelimit';
        $attrs[] = 'amavisspamtaglevel';
        $attrs[] = 'amavisspamtag2level';
        return $attrs;
    }

    public function init(EMSPluginManager $userobj)
    {
        parent::init($userobj);

        $param = $this->getParameter('mail');
        //$param->addValidator(new Zend_Validate_EmailAddress());
        $param = $this->getParameter('emsmailmboxquota');
        //$param->addValidator(new Zend_Validate_Digits(),Zivios_Validate::errorCode2Array('digits','Email Quota'));
        $param = $this->getParameter('amaviswhitelistsender');
        //$param->addValidator(new Zend_Validate_EmailAddress(),Zivios_Validate::errorCode2Array('email','Sender Whitelist'));
        $param = $this->getParameter('amavisblacklistsender');
        //$param->addValidator(new Zend_Validate_EmailAddress(),Zivios_Validate::errorCode2Array('email','Sender Blacklist'));

        $param = $this->getParameter('amavisspamkilllevel');
        //$param->addValidator(new Zend_Validate_Digits(),Zivios_Validate::errorCode2Array('digits','Spam Kill Level'));

        $param = $this->getParameter('amavismessagesizelimit');
        //$param->addValidator(new Zend_Validate_Digits(),Zivios_Validate::errorCode2Array('digits','Message Size Limit'));

        $param = $this->getParameter('amavisspamtaglevel');
        //$param->addValidator(new Zend_Validate_Digits(),Zivios_Validate::errorCode2Array('digits','Spam Tag Level'));

        $param = $this->getParameter('amavisspamtag2level');
        //$param->addValidator(new Zend_Validate_Digits(),Zivios_Validate::errorCode2Array('digits','Spam Tag 2 Level'));

        /**
         * Registering an Action Listener ONLY if this is an existing plugin, calling
         * quota update on a NEW plugin will cause an ERROR as the mailbox does not
         * exist in Cyrus!
         */
         if ($this->_pmobj->hasPlugin('MailUser')) {
            $this->_pmobj->addEventListener('CORE_PCHANGE_EMSMAILMBOXQUOTA',$this);
            $this->_pmobj->addEventListener('CORE_PCHANGE_EMSMAILACTIVE',$this);
            $this->_pmobj->addEventListener('CORE_PCHANGE_EMSACCOUNTLOCKOUT',$this);

         }
    }

    public function eventAction($eventname,Zivios_Transaction_Group $tgroup)
    {
        if ($eventname == 'CORE_PCHANGE_EMSMAILMBOXQUOTA') {
            $this->_quotaUpdate($tgroup,'Changing Quota for user '.$this->getdn(),$this->getParameter('emsmailmboxquota'));
        } else if ($eventname == 'CORE_PCHANGE_EMSMAILACTIVE') {
            $mailactive = $this->getProperty('emsmailactive');
            if ($mailactive == self::MAIL_INACTIVE)
                $this->_disableMailbox($tgroup,'Disabling Mail plugin for user : '.$this->getdn());
            else
                $this->_enableMailbox($tgroup,'Enabling Mail Plugin for user :'.$this->getdn());
        } else if ($eventname == 'CORE_PCHANGE_EMSACCOUNTLOCKOUT') {
            $lockout = $this->getProperty('emsaccountlockout');
            $this->accountLockout($tgroup,$lockout);
        }
    }

    
    public function accountLockout(Zivios_Transaction_Group $tgroup,$lock)
    {
        $this->setActive(!$lock);
        
        $this->update($tgroup);

    }
    
    public function quotaUpdate($newquota)
    {
        /** assumed that quota is in MB */
        $newquota = $newquota->getValue();
        if ($newquota != null) {
            Zivios_Log::debug("MailPlugin doing Quota update to $newquota KB");
            $service = $this->getService();
            $uid = $this->getProperty('uid');
            if (!$service->setMailboxQuota("user/$uid",$newquota)) {
                throw new Zivios_Exception("Unable to Set Mailbox Quota!");
            }
        }
    }

    public function disableMailbox()
    {
        $service= $this->getService();
        $mbox = $this->getProperty('emscyrusmboxroot');
        $uid = $this->getProperty('uid');
        $service->setAcl($mbox,$uid,'');
        $service->setAcl("$mbox/Trash",$uid,"");
        $service->setAcl("$mbox/Sent",$uid,"");
        $service->setAcl("$mbox/Drafts",$uid,"");
        $service->setAcl("$mbox/Spam",$uid,"");
    }
    
    public function enableMailbox()
    {
        $service= $this->getService();
        $mbox = $this->getProperty('emscyrusmboxroot');
        $uid = $this->getProperty('uid');
        $service->setAcl($mbox,$uid,'lrswipcda');
        $service->setAcl("$mbox/Trash",$uid,"lrswipcda");
        $service->setAcl("$mbox/Sent",$uid,"lrswipcda");
        $service->setAcl("$mbox/Drafts",$uid,"lrswipcda");
        $service->setAcl("$mbox/Spam",$uid,"lrswipcda");
    }
    
    public function getQuotaUsage()
    {
        $mbox = $this->getProperty('emscyrusmboxroot');
        return $this->getService()->getStorageQuota($mbox);
    }
    
    public function setActive($active)
    {
        if ($active >= 1)
            $active = self::MAIL_ACTIVE;
        else
        $active = self::MAIL_INACTIVE;

        $this->setProperty('emsmailactive',$active);
    }

    public function delete(Zivios_Transaction_Group $tgroup)
    {
        //Remove all parameters and the objectclass

        $cyrmboxroot = $this->getProperty('emscyrusmboxroot');
        $this->removeObjectClass('emsmailuser');
        $this->removeObjectClass('qmailuser');
        $this->removeObjectClass('amavisAccount');

        $this->removeProperty('emsmailactive');
        $this->removeProperty('mail');
        $this->removeProperty('emspostfixsecurityclass');
        $this->removeProperty('emscyrusmboxroot');
        $this->removeProperty('emsmailmboxquota');
        $this->removeProperty('amaviswhitelistsender');
        $this->removeProperty('amavisblacklistsender');
        $this->removeProperty('amavisspamkilllevel');
        $this->removeProperty('amavismessagesizelimit');
        $this->removeProperty('amavisspamtaglevel');
        $this->removeProperty('amavisspamtag2level');
        $this->removeProperty('mailalternateaddress');
        $tgroup =  parent::delete($tgroup);
        $tgroup = $this->_removeMailBox($tgroup,'Removing Cyrus Mailbox',$cyrmboxroot);
        return $handler;
    }

    public function removeMailBox($mbox)
    {
        //remove Cyrus User and Mailbox
        $service = $this->getService();
        //the delete call should set the cyrmboxroot properly!

        $service->deleteMailBox("$mbox/Trash");
        $service->deleteMailBox("$mbox/Sent");
        $service->deleteMailBox("$mbox/Spam");
        $service->deleteMailBox("$mbox/Drafts");
        $service->deleteMailBox($mbox);

    }

    public function add(Zivios_Transaction_Group $tgroup)
    {
        $this->setProperty('emsmailactive',self::MAIL_ACTIVE);
        Zivios_Log::debug("Object CLass  Before : ");
        Zivios_Log::debug($this->getProperty('objectclass'));
        $this->addObjectClass('emsMailUser');
        
        Zivios_Log::debug("Object CLass  After Ems Mail User : ");
        Zivios_Log::debug($this->getProperty('objectclass'));
        
        $this->addObjectClass('qmailUser');
        Zivios_Log::debug("Object CLass  After Qmail User : ");
        Zivios_Log::debug($this->getProperty('objectclass'));
        
        $this->addObjectClass('amavisAccount');

        Zivios_Log::debug($this->getProperty('objectclass'));
        $cyrusmbox = $this->getProperty('emscyrusmboxroot');
        if ($cyrusmbox == '') {
            $this->setProperty('emscyrusmboxroot',"user/".$this->getProperty('uid'));
        }

        $tgroup = parent::add($tgroup);
        $tgroup = $this->_createCyrusMailbox($tgroup,'Creating Cyrus Mailbox');
        return $tgroup;

        //$this->update($handler);
        //$handler = $this->_userobj->update($handler);
        //parent::_add($handler);
    }

    public function createCyrusMailbox()
    {
        /** All we really need to do here this fine sunny morning
        * is connect to the lovely cyrus IMAP server
        * and create a darling user
        */
        $service = $this->getService();
        $mbox = $this->getProperty('emscyrusmboxroot');

        //Hardcoded Subfolders for now.

        $service->createMailBox($mbox);
        $service->createMailBox("$mbox/Trash");
        $service->createMailBox("$mbox/Sent");
        $service->createMailBox("$mbox/Drafts");
        $service->createMailBox("$mbox/Spam");

        $service->setAcl($mbox,"zadmin","lrswipcda");
        $service->setAcl("$mbox/Trash","zadmin","lrswipcda");
        $service->setAcl("$mbox/Sent","zadmin","lrswipcda");
        $service->setAcl("$mbox/Drafts","zadmin","lrswipcda");
        $service->setAcl("$mbox/Spam","zadmin","lrswipcda");

        $quota = $this->getProperty('emsmailmboxquota');

        if ($quota != "")
            $this->quotaUpdate($this->getParameter('emsmailmboxquota'));
    }

    public function generateContextMenu()
    {
        return false;
    }

    public function getGeneralForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        
        
        $form->addElement('CheckBox', 'emsmailactive',     
                          array(
                                'label'          => 'Account Active?',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'          => $this->getProperty('emsmailactive'),
                                ));
        
        $form->addElement('ValidationTextBox','emsmailmboxquota', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->digits,
                                'label'         => 'Max Email Quota (in Mb)',
                                'invalidMessage' => 'Invalid Quota Specified',
                                'value' => $this->getProperty('emsmailmboxquota') / 1024
                                ));
        
        $form->addElement('ValidationTextBox','amavismessagesizelimit', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->digits,
                                'label'         => 'Max Email Size (in Kb): ',
                                'invalidMessage' => 'Invalid Mail Size Specified',
                                'value' => $this->getProperty('amavismessagesizelimit')/1024
                                ));
        
        $form->addElement('ValidationTextBox','amavisspamtaglevel', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Spam Header at',
                                'label'         => 'Spam Header at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamtaglevel')
                                ));
        
        $form->addElement('ValidationTextBox','amavisspamtag2level', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Spam Tag at',
                                'label'         => 'Spam Tag at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamtag2level')
                                
                                ));

        $form->addElement('ValidationTextBox','amavisspamkilllevel', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Kill At',
                                'label'         => 'Kill at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamkilllevel')
                                ));
        
        return $form;
    }
    
    public function setGeneralForm($data)
    {
        $form = $this->getGeneralForm();
        if ($form->isValid($data)) {
            $this->setProperty('amavismessagesizelimit',$data['amavismessagesizelimit']*1024);
            $this->setProperty('emsmailmboxquota',$data['emsmailmboxquota']*1024);
            $this->setProperty('amavisspamkilllevel',$data['amavisspamkilllevel']);
            $this->setProperty('amavisspamtag2level',$data['amavisspamtag2level']);
            $this->setProperty('amavisspamtaglevel',$data['amavisspamtaglevel']);
            $this->setProperty('emsmailactive',$data['emsmailactive']);
        }
    }
    
    public function getAddPluginForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        
        $form->addElement('ValidationTextBox','amavismessagesizelimit', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->digits,
                                'label'         => 'Max Email Size (in Kb): ',
                                'invalidMessage' => 'Invalid Mail Size Specified'
                                ));
        
        $form->addElement('ValidationTextBox','emsmailmboxquota', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->digits,
                                'label'         => 'Max Email Quota (in MB)',
                                'invalidMessage' => 'Invalid Quota Specified'
                                ));
        
        
        $service = $this->getService();
        $maildomains = $service->getProperty('emsmaildomains',1);
        $marray =array();
        $uid = $this->_pmobj->getProperty('uid');
        
        foreach ($maildomains as $domain)
        {
            $email = $uid . "@" . $domain;
            $marray[$email] = $email;
        }
        
        $form->addElement('FilteringSelect','mail', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->hostname,
                                'label'         => 'Email Address:  ',
                                'invalidMessage' => 'Invalid Email Address Specified.',
                                'multiOptions' => $marray                    
                                ));
        
        return $form;
        
    }
    
    public function setBulkUserSubscribeForm($data)
    {
        $data['mail'] = $this->getProperty('uid').'@'.$data['mail'];
        $this->setAddPluginForm($data);   
    }

    public function setAddPluginForm($form)
    {
        $this->setProperty('mail',$form['mail']);
        $this->setProperty('amavismessagesizelimit',$form['amavismessagesizelimit']*1024);
        $this->setProperty('emsmailmboxquota',$form['emsmailmboxquota']*1024);
    }
    public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

}
