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

class Samba_UserController extends Zivios_Controller_User
{
	public function _init() 
    {
        $this->_helper->layout->disableLayout(true);
    }
        
    public function dashboardAction()
    {
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('You must pass the user container dn to continue.');
            }

        $user = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->user = $user;
    }

	public function configAction()
	{
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('You must pass the user container dn to continue.');
            }

        $user = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $user->newPlugin('SambaUser');
        $pluginform = $plugin->getEditForm();
        $form = new Zend_Dojo_Form();
        $form->setName('editsambaform')
             ->setElementsBelongTo('editsambaform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($pluginform,'mainform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Update Samba User',
            'onclick'     => "zivios.formXhrPost('editsambaform','/samba/user/doconfig'); return false;",
        ));
        
        $this->view->user = $user;
        $this->view->form = $form;
	}

    public function doconfigAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $formdata = $this->getParam('editsambaform');
        $mainformdata = $formdata['mainform'];
        $user = Zivios_Ldap_Cache::loadDn($formdata['dn']);
        
        $sambaplugin = $user->getPlugin('SambaUser');
        
        $sambaplugin->setEditForm($mainformdata);
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Samba User Plugin');
        $tgroup = $handler->newGroup('Adding Samba User Plugin',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $sambaplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('usrsambaplugin');
        } 
        
        $this->sendResponse();    
    }
    
    
    public function activatepluginAction()
    {
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('You must pass the user container dn to continue.');
            }

        $user = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $user->newPlugin('SambaUser');
        $pluginform = $plugin->getMainForm();
        $form = new Zend_Dojo_Form();
        $form->setName('addsambaform')
             ->setElementsBelongTo('addsambaform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($pluginform,'mainform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Install Service',
            'onclick'     => "zivios.formXhrPost('addsambaform','/samba/user/doactivateplugin'); return false;",
        ));
        
        $this->view->form = $form;
    }
    
    public function doactivatepluginAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $formdata = $this->getParam('addsambaform');
        $mainformdata = $formdata['mainform'];
        $user = Zivios_Ldap_Cache::loadDn($formdata['dn']);
        
        $sambaplugin = $user->newPlugin('SambaUser');
        
        $sambaplugin->setMainForm($mainformdata);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Samba User Plugin');
        $tgroup = $handler->newGroup('Adding Samba User Plugin',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->addPlugin($sambaplugin,$tgroup);
        $user->changePassword($mainformdata['password'],$tgroup);
        $tgroup->commit();
        
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('utb01');
            $this->refreshPane('userdataleft');
            $this->closeTab('usertabs','usrsambaplugin');
        } 
        
        $this->sendResponse();    
    }
    
    
     public function deleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        $this->view->user = $user;
    }
    
    public function dodeleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Removing Samba Plugin from user '.$user->getdn());
        $tgroup = $handler->newGroup('Removing Samba Plugin from user '.$user->getdn(),Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->removePlugin('SambaUser',$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->closeTab('usertabs','usrsambaplugin');
            $this->refreshPane('sambausercpcenter');
            $this->refreshPane('utb01');
        }
        
        $this->sendResponse();
        
    }

}
