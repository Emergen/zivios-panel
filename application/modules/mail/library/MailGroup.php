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
 * @version     $Id: MailGroup.php 953 2008-08-27 08:01:13Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-27 14:01:13 +0600 (Wed, 27 Aug 2008) $
 **/
class MailGroup extends Zivios_Plugin_Group
{
    protected $_module = 'mail';
    const LIST_ACTIVE = 'active';
    const LIST_INACTIVE = 'inactive';

    public function getAttrs()
    {
        $attr = parent::getAttrs();
        $attr[] = "emsmailactive";
        $attr[] = "mail";
        return $attr;
    }

    public function init(EMSPluginManager $groupobj)
    {
        parent::init($groupobj);
    }

    public function add(Zivios_Transaction_Group $tgroup)
    {
        $this->addObjectClass('emsMailGroup');
        parent::add($tgroup);
    }

    public function delete(Zivios_Transaction_Group $tgroup)
    {
        $this->removeObjectClass('emsmailgroup');
        $this->setProperty('emsmailactive',null);
        $this->setProperty('mail',null);
       // $handler = $this->update($handler);
        return parent::delete($tgroup);
    }

    public function getAddPluginForm()
    {
        return $this->getPluginForm(1);
    }

    public function setPluginForm($data)
    {
        $form = $this->getPluginForm(1);
        $this->updateViaForm($form,$data);
    }

    public function getPluginForm($skipvals=0)
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'addmailplugin',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Add Group Mail Plugin',
            ),
        ));


        $groupemail = null;
        $emsmailaction = null;

        if (!$skipvals) {
            $groupemail = $this->getProperty('mail');
            $emsmailactive = $this->getProperty('emsmailactive');
        }

        $form->addElement('ValidationTextBox','mail',
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->email,
                                'title'         => 'Group Email',
                                'label'         => 'Group Email: ',
                                'invalidMessage' => 'Invalid Email Specifided',
                                'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->email.'/i')),
                                   ),
                                'value'         =>  $groupemail,
                                ));

        $form->addElement('CheckBox', 'emsmailactive',
                          array(
                                'label'          => 'Group is a Maling List',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'          => $emsmailactive,
                                ));
        return $form;
    }

    
     public function getBulkUserSubscribeForm()
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
                                'label'         => 'Email Quota (in MB)',
                                'invalidMessage' => 'Invalid Quota Specified'
                                ));
        
        
        $service = $this->getService();
        $maildomains = $service->getProperty('emsmaildomains',1);        
        
        $marray = array();
        foreach ($maildomains as $domain)
        {
            $marray[$domain] = $domain;
        }
        
        $form->addElement('FilteringSelect','mail', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->hostname,
                                'label'         => 'Email Domain:  ',
                                'invalidMessage' => 'Invalid Email Domain Specified.',
                                'multiOptions' => $marray,
                                'value' => $marray[0]
                                ));
        
        return $form;
    }
    
    public function bulkSubscribeMembers(Zivios_Transaction_Handler $handler,$bulkformdata)
    {
          $users = $this->_groupobj->getAllImmediateUsers(true);
          foreach ($users as $user) {
              $iter = Zivios_Ldap_Cache::loadDn($user->getdn());
              if (!$iter->hasPlugin('MailUser')) {
                  $tgroup = $handler->newGroup('Adding Mail Plugin to User '.$iter->getdn(),Zivios_Transaction_Group::EM_SEQUENTIAL);
                  $mailplugin = $iter->newPlugin('MailUser');
                  $mailplugin->setBulkUserSubscribeForm($bulkformdata);
                  $iter->addPlugin($mailplugin,$tgroup);
              }
          }
    }
    
    public function getUserPluginName()
    {
        return 'MailUser';
    }

    public function generateContextMenu()
    {
        return false;
    }
}
