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
 * @package		mod_mail
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: GroupController.php 953 2008-08-27 08:01:13Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-27 14:01:13 +0600 (Wed, 27 Aug 2008) $
 **/


class Mail_GroupController extends Zivios_Controller_Group
{
	public function indexAction() {}

    
    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        
    }
    
    public function activatemembersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        $groupplugin = $this->view->entry->getPlugin('MailGroup');
        
        $form = new Zend_Dojo_Form();
        $form->setName('mailgroupbulkform')
             ->setElementsBelongTo('mailgroupbulkform')
             ->setMethod('post')
             ->setAction('#');

        $subform = $groupplugin->getBulkUserSubscribeForm();
        
        $form->addSubForm($subform,'bulksubscribeform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Activate Members',
            'onclick'     => "zivios.formXhrPost('mailgroupbulkform','mail/group/doactivatemembers'); return false;",
        ));
        $this->view->form = $form;
        
    }
    
    public function doactivatemembersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $formdata = $this->getParam('mailgroupbulkform');
        $dn = $formdata['dn'];
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        $groupplugin = $this->view->entry->getPlugin('MailGroup');
        
        $subformdata = $formdata['bulksubscribeform'];
        Zivios_Log::debug($subformdata);
        $handler = Zivios_Transaction_Handler::getNewHandler('Bulk Subscribing mail users for group '.$dn);
        $groupplugin->bulkSubscribeMembers($handler,$subformdata);
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            //$this->refreshPane('gtb01');
            //$this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs','mailplugin');
        }
        
        $this->sendResponse();
        
    }
	public function configAction()
	{
        $this->_helper->layout->disableLayout(true);
        

        $dn = urldecode($this->getParam('dn'));
        $group = Zivios_Ldap_Cache::loadDn($dn);
        $groupplugin = $group->getPlugin('MailGroup');
        
        $form = new Zend_Dojo_Form();
        $form->setName('mailgroupform')
             ->setElementsBelongTo('mailgroupform')
             ->setMethod('post')
             ->setAction('#');

        $subform = $groupplugin->getPluginForm();
        
        $form->addSubForm($subform,'pluginsubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('mailgroupform','mail/group/dochangegeneral'); return false;",
        ));
        $this->view->form = $form;
	}
    
    public function dochangegeneralAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->getParam('mailgroupform');
        
        $group = Zivios_Ldap_Cache::loadDn($form['dn']);
        
        $plugin = $group->getPlugin("MailGroup");
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Mail Plugin Settings');
        $tgroup = $handler->newGroup('Changing Mail Plugin Settings',Zivios_Transaction_Group::EM_SEQUENTIAL );
        
        $data = $form['pluginsubform'];
        
        $plugin->setPluginForm($data);
        
        $plugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            //$this->refreshPane('gtb01');
            //$this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs','mailplugin');
        }
        
        $this->sendResponse();
        
    }
    
    

    public function linktoserviceAction()
	{
         $this->_helper->layout->disableLayout(true); 
        $this->view->srvdn = urldecode($this->getParam('srvdn'));
        $dn = urldecode($this->getParam('dn'));
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $this->view->entry->newPlugin('MailGroup');
        
        $addpluginform = $mailplugin->getAddPluginForm();
        
        $form = new Zend_Dojo_Form();
        $form->setName('addmailgroupform')
             ->setElementsBelongTo('addmailgroupform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($addpluginform,'addpluginsubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        
        $hfdn = new Zend_Form_Element_Hidden('srvdn');
        $hfdn->setValue($this->view->srvdn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('addmailgroupform','mail/group/dolinktoservice'); return false;",
        ));
        $this->view->form = $form;
        $this->render('linkgroup');
    }
    
    public function dolinktoserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->getParam('addmailgroupform');
        Zivios_Log::debug($form);
        $srvobj = Zivios_Ldap_Cache::loadDn($form['srvdn']);
        $group = Zivios_Ldap_Cache::loadDn($form['dn']);
        
        $plugin = $group->newPlugin("MailGroup");
        

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Mail Plugin To Group');
        $tgroup = $handler->newGroup('Adding Mail Plugin to Group',Zivios_Transaction_Group::EM_SEQUENTIAL );
        
        $plugin->linkToService($srvobj);
        
        $group->addPlugin($plugin,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('gtb01');
            $this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs','mailplugin');
        }
        $this->sendResponse();
        
    }
    
	public function linkGroupToServiceAction()
	{
        
        
        
        $this->view->group = $this->view->obj;
        if (isset($this->json->action)) {
            $servicedn = $this->json->srvSelect;
            $service = Zivios_Ldap_Cache::loadDn($this->json->srvSelect);
            $group = $this->view->obj;
            $mailplug = $group->newPlugin('MailGroup');

            if (isset($this->json->mailinglist)) {
                $mailinglist = MailGroup::LIST_ACTIVE;
            } else $mailinglist = MailGroup::LIST_INACTIVE;

            $mailplug->setProperty('emsmailactive',$mailinglist);
            $mailplug->setProperty('mail',$this->json->email);
            $mailplug->linkToService($service);
            $trans = $group->addPlugin($mailplug);
            if ($this->processTransaction($trans)) {
	            $this->_createPopupReturn(0,"Mail Group Activated Successfully");
	            $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
            }
        }
        else {
            $this->view->srvSelect = $this->json->srvSelect;
            Zivios_Log::debug($this->json->srvSelect);
            $service = Zivios_Ldap_Cache::loadDn($this->json->srvSelect);
            $this->render('linkgroup');
        }
    }
    
    


}

