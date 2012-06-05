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
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Squid_GroupController extends Zivios_Controller
{
    protected function _init()
    {}

    public function predispatch()
    {
        parent::predispatch();
    }

    public function dolinktoserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        // Validate request.
        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid call received by controller');
        }

        // Ensure required form data is present.
        if (null === ($formdata = $_POST['addsquidgroupform']) ||
            null === ($dn = ($_POST['addsquidgroupform']['dn'])) ||
            null === ($srvdn = ($_POST['addsquidgroupform']['srvdn']))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
            $srvdn = strip_tags(urldecode($srvdn));
        }

        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        $serviceEntry = Zivios_Ldap_Cache::loadDn($srvdn);

        // Initialize Squid plugin for groupEntry.
        $squidGroup = $groupEntry->newPlugin('SquidGroup');
        $squidGroup->setAddPluginForm($formdata);

        // Initialize transaction
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Squid plugin to Group');
        $tgroup  = $handler->newGroup('Adding Squid plugin to Group: ' . $groupEntry->getProperty('cn'));
        
        // Link service to plugin and call add to group.
        $squidGroup->linkToService($serviceEntry);
        $groupEntry->addPlugin($squidGroup, $tgroup);
        
        $tgroup->commit();
        // Process transaction
        $status = $this->processTransaction($handler);
        //$handler->__destruct();

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $sqcfg = $squidGroup->getConfigSectionGeneral();
            $this->refreshPane('gtb01');
            $this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs', $sqcfg->tabid);
            $this->addCallBack('zivios.loadApp', array(
                $sqcfg->url . '/dn/'.urlencode($dn), 'grouptabs',
                $sqcfg->tabid, $sqcfg->displayname));
            $this->sendResponse();
        }
    }

    /**
     * Service activation for Squid Groups.
     */
    public function linktoserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($srvdn = $this->_request->getParam('srvdn')) ||
            null === ($dn    = $this->_request->getParam('dn'))) {
            Zivios_Log::error('Invalid call to linktoservice action in squid group controller.'.
                              ' srvdn or dn missing from request.');
            throw new Zivios_Error('Missing data from request.');
        } else {
            $srvdn = strip_tags(urldecode($srvdn));
            $dn    = strip_tags(urldecode($dn));
        }
    
        // Load service and group objs.
        $serviceEntry = Zivios_Ldap_Cache::loadDn($srvdn);
        $groupEntry   = Zivios_Ldap_Cache::loadDn($dn);
       
        $squidPlugin = $groupEntry->newPlugin('SquidGroup');
        $form = $squidPlugin->getAddPluginForm($srvdn, $dn);
        
        $this->view->form = $form;
    }
    
    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Invalid request received by controller.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        // Add group object to view.
        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidPlugin= $groupEntry->getPlugin('SquidGroup');
        $configForm = $squidPlugin->getGroupConfigForm($dn);

        $form = new Zend_Dojo_Form();
        $form->setName('editsquidgroup')
             ->setElementsBelongTo('editsquidgroup')
             ->setMethod('post')
             ->setAction('#');
        
        $form->addSubForm($configForm, 'groupconfigform');
        
        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Update Configuration',
            'onclick'     => "zivios.formXhrPost('editsquidgroup','/squid/group/doconfig'); return false;",
        ));

        $this->view->entry = $groupEntry;
        $this->view->form  = $form;
    }

    public function doconfigAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($_POST['editsquidgroup']) || !is_array($_POST['editsquidgroup']) ||
            (null === ($dn = $_POST['editsquidgroup']['dn'])) || 
            !isset($_POST['editsquidgroup']['groupconfigform'])) {
            throw new Zivios_Error('Required data missing from request');
        } else {
            $dn = strip_tags(urldecode($dn));
            $formdata = $_POST['editsquidgroup']['groupconfigform'];
        }
        
        $squidGroup  = Zivios_Ldap_Cache::loadDn($dn);
        $squidPlugin = $squidGroup->getPlugin('SquidGroup');
        $squidPlugin->setGroupConfigForm($formdata);

        // Create transaction handler for config update.
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Squid group plugin config.');
        $tgroup = $handler->newGroup('Updating configuration for: ' .
                    $squidPlugin->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);
        $squidPlugin->update($tgroup);
        $tgroup->commit();
        
        // Process transaction
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Group configuration update failed. Check system logs.');
        }
    }

    public function svcmembersAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // Load group entry and attach to view.
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function memberactivationstatusAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        
        if ($groupEntry->getProperty('emssquidenablemembers') == 'Y') {
            $this->view->deactivateOption = true;
        }

        // assign group entry to view
        $this->view->entry = $groupEntry;

    }

    public function getactivemembersAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidPlugin = $groupEntry->getPlugin('SquidGroup');
        $activatedMembers = $squidPlugin->getActiveMembers();
        
        // Add to view object.
        $this->view->entry = $groupEntry;
        $this->view->activeMembers = $activatedMembers;
    }

    public function doactivatemembersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidPlugin = $groupEntry->getPlugin('SquidGroup');
        
        // Create transaction handler to enable group members.
        $handler = Zivios_Transaction_Handler::getNewHandler('Activating squid plugin for group members.');
        
        // Add plugin to group.
        $squidPlugin->activateGroupMembers($handler);
        
        // Process transaction
        $status = $this->processTransaction($handler);
        //$handler->__destruct();

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            // refresh the activation and member listing panes.
            $this->refreshPane('squidgrpmemstop');
            $this->refreshPane('squidgrpmemscenter');
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Member activation failed. Check system logs.');
        }
    }

    public function dodeactivatemembersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $groupEntry = Zivios_Ldap_Cache::loadDn($dn);
        $squidPlugin = $groupEntry->getPlugin('SquidGroup');
        
        // Create transaction handler to disable group members
        $handler = Zivios_Transaction_Handler::getNewHandler('Deactivating squid plugin for group members.');
        
        // Add plugin to group
        $squidPlugin->deactivateGroupMembers($handler);
        
        // Process transaction
        $status = $this->processTransaction($handler);
        //$handler->__destruct();

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            // refresh the activation and member listing panes.
            $this->refreshPane('squidgrpmemstop');
            $this->refreshPane('squidgrpmemscenter');
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Member deactivation failed. Check system logs.');
        }   
    }

    public function reportsAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        }
    }

    public function deleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
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
             $groupEntry  = Zivios_Ldap_Cache::loadDn($dn);
             $squidPlugin = $groupEntry->getPlugin('SquidGroup');
        }

        $handler = Zivios_Transaction_Handler::getNewHandler('Removing Squid Group Plugin');
        $tgroup = $handler->newGroup('Removing Squid Group Plugin for: '.
            $groupEntry->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);

        $groupEntry->removePlugin('SquidGroup', $tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);
        switch ($status) {
            case Zivios_Transaction_Handler::STATUS_COMPLETED:
                // Transaction successful.
                $sqcfg = $squidPlugin->getConfigSectionGeneral();
                $this->addNotify('Squid group plugin successfully removed.');
                $this->refreshPane('gtb01');
                $this->refreshPane('groupdataleft');
                $this->closeTab('grouptabs', $sqcfg->tabid);
                $this->sendResponse();
                break;

            default: 
                throw new Zivios_Error('Error removing the Squid Group plugin. Please check Zivios logs.');
        }
    }
}

