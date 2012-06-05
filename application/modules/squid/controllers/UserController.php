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
 **/

class Squid_UserController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function addpluginAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $userEntry = Zivios_Ldap_Cache::loadDn($dn);

        if($userEntry->hasPlugin('SquidUser')) {
            throw new Zivios_Error('The Squid plugin has already been activated.');
        }

        $this->view->entry = $userEntry;
    }

    public function doaddpluginAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid request received by controller.');
        }

        if (!isset($_POST['dn'])) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($_POST['dn']));
        }
        
        $userEntry = Zivios_Ldap_Cache::loadDn($dn);

        if ($userEntry->hasPlugin('SquidUser')) {
            throw new Zivios_Error('Squid user plugin appears to already be enabled.');
        }
        
        // Initialize squid user plugin
        $squidUser = $userEntry->newPlugin("SquidUser");
        
        // Create transaction for plugin add.
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Squid user plugin.');
        $tgroup = $handler->newGroup('Adding Squid plugin to user: '.$userEntry->getProperty('cn'), 
            Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Add plugin to user object.
        $userEntry->addPlugin($squidUser, $tgroup);
        $tgroup->commit();
        
        // Process transaction.
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $sqcfg = $squidUser->getConfigSectionGeneral();
            $this->refreshPane('utb01');
            $this->closeTab('usertabs', $sqcfg->tabid);
            $this->addCallBack('zivios.loadApp', array(
                $sqcfg->url . '/dn/'.urlencode($dn), 'usertabs',
                $sqcfg->tabid, $sqcfg->displayname));
            $this->addNotify('Squid user plugin activated successfully.');
        } else {
            throw new Zivios_Error('Error activating Squid user Plugin. Please check system logs.');
        }

        $this->sendResponse();
    }
    
    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $userEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidUser = $userEntry->getPlugin('SquidUser');

        $cform = $squidUser->getUserConfigForm();
        $form  = new Zend_Dojo_Form();
        $form->setName('editsquiduser')
             ->setElementsBelongTo('editsquiduser')
             ->setMethod('post')
             ->setAction('#');
        
        $form->addSubForm($cform, 'userconfigform');
        
        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Update Configuration',
            'onclick'     => "zivios.formXhrPost('editsquiduser','/squid/user/doconfig'); return false;",
        ));

        $this->view->entry = $userEntry;
        $this->view->form  = $form;
    }

    public function doconfigAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        Zivios_Log::debug($_POST);

        if (!isset($_POST['editsquiduser']) || !is_array($_POST['editsquiduser']) ||
            (null === ($dn = $_POST['editsquiduser']['dn'])) || 
            !isset($_POST['editsquiduser']['userconfigform'])) {
            throw new Zivios_Error('Required data missing from request');
        } else {
            $dn = strip_tags(urldecode($dn));
            $formdata = $_POST['editsquiduser']['userconfigform'];
        }
        
        $userEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidUser = $userEntry->getPlugin('SquidUser');
        $squidUser->setUserConfigForm($formdata);

        // Create transaction handler for config update.
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Squid user plugin config.');
        $tgroup = $handler->newGroup('Updating configuration for: ' .
                    $squidUser->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);
        $squidUser->update($tgroup);
        $tgroup->commit();
        
        // Process transaction
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('User settings updated successfully.');
            $this->sendResponse();
        } else {
            throw new Zivios_Error('User configuration update failed. Check system logs.');
        }
    }

    public function reportsAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
    }

    public function deleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function dodeleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Invalid request received by controller.');
        } else {
             $dn = strip_tags(urldecode($dn));
             $userEntry  = Zivios_Ldap_Cache::loadDn($dn);
             $squidPlugin = $userEntry->getPlugin('SquidUser');
        }

        $handler = Zivios_Transaction_Handler::getNewHandler('Removing Squid User Plugin');
        $tgroup = $handler->newGroup('Removing Squid User Plugin for: '.
            $userEntry->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);

        $userEntry->removePlugin('SquidUser', $tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);
        switch ($status) {
            case Zivios_Transaction_Handler::STATUS_COMPLETED:
                // Transaction successful.
                $sqcfg = $squidPlugin->getConfigSectionGeneral();
                $this->addNotify('Squid user plugin successfully removed.');
                $this->refreshPane('utb01');
                $this->closeTab('usertabs', $sqcfg->tabid);
                $this->sendResponse();
                break;

            default: 
                throw new Zivios_Error('Error removing the Squid Group plugin. Please check Zivios logs.');
        }
    }
}
