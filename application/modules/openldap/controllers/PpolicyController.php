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
 * @package     mod_openldap
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Openldap_PpolicyController extends Zivios_Controller_Computer
{
    protected function _init()
    {
        
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        $this->view->policy = Zivios_Ldap_Cache::loadDn($this->getParam('dn'));
        Zivios_Log::debug("Loading PPolicy Dashboard");
        
    }
    
    public function userdashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        $ldap = Zivios_Ldap_Cache::loadDn($dn);
        $ppolicy = new PPolicy();
        $this->view->policies = $ppolicy->getAllPolicies();
        $this->view->dn = $dn;
        $this->view->plugin = $ldap->getPlugin('OpenldapUser');
    }
    
    public function userupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $form = $this->processForm('ldap');

        $dn = $form['dn'];
        if (null === $dn) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        
        $ldap = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $ldap->getPlugin('OpenldapUser');
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating User Account Policy for: '.$dn);
        $tgroup = $handler->newGroup('Updating Policy',Zivios_Transaction_Group::EM_SEQUENTIAL );
        
        if ($form['pwdpolicydn'] == '' || $form['pwdpolicydn'] == '-') {
            $plugin->removeProperty('pwdpolicysubentry');
        } else {
            $plugin->setProperty('pwdpolicysubentry',urldecode($form['pwdpolicydn']));
        }
        
        if ($form['pwdreset'] == 'TRUE') {
            $plugin->resetAccount($tgroup,true);
        } else if ($form['pwdreset'] == 'FALSE') {
            $plugin->resetAccount($tgroup,false);
        }
        
        if ($form['pwdmustchange'] == 1) {
            $plugin->resetAccount($tgroup,true);
        }
        
        $plugin->update($tgroup);
        Zivios_Log::debug($form);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('usropenldapplugin');
        } 
        
        $this->sendResponse();    
        
        
    }
    
    public function addAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        $this->view->parentdn = $dn;
        $this->view->policy = new PPolicy();
        $this->view->policy->init();
        $this->view->isnew = true;
        $this->render('dashboard');
        
    }
    
    public function updateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $form = $this->processForm('ldap');
        Zivios_Log::debug($form);
        $action = $form['action'];
        if ($action == 'edit') {
            $policy = Zivios_Ldap_Cache::loadDn($form['dn']);
        } else {
            $parent = Zivios_Ldap_Cache::loadDn($form['parentdn']);
            $policy = new PPolicy();
            $policy->init();
            $policy->setProperty('cn',$form['cn']);
        }
        

        $policy->setProperty('pwdmaxage',$form['pwdmaxage']*86400);
        $policy->setProperty('pwdexpirewarning',$form['pwdexpirewarning']*86400);
        $policy->setProperty('pwdminage',$form['pwdminage']*86400);
        $policy->setProperty('pwdlockout',$form['pwdlockout']);
        $policy->setProperty('pwdminlength',$form['pwdminlength']);
        $policy->setProperty('pwdmustchange',$form['pwdmustchange']);
        $policy->setProperty('pwdmaxfailure',$form['pwdmaxfailure']);
        $policy->setProperty('pwdgraceauthnlimit',$form['pwdgraceauthnlimit']);
        $policy->setProperty('pwdinhistory',$form['pwdinhistory']);
        $policy->setProperty('pwdallowuserchange',$form['pwdallowuserchange']);
        $policy->setProperty('pwdlockoutduration',$form['pwdlockoutduration']);
        
        
        if ($action == 'edit') {
            $handler = Zivios_Transaction_Handler::getNewHandler('Updating Policy: '.$policy->getdn());
            $tgroup = $handler->newGroup('Updating Policy',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $policy->update($tgroup);
        } else if ($action == 'add') {
            $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Policy: ');
            $tgroup = $handler->newGroup('Updating Policy',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $policy->add($parent, $tgroup);
        }
        
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('direntry');
            if ($action == 'add')
                $this->refreshTreeNode($parent->getdn());
        } 
        
        $this->sendResponse();    
    }
    
    
    
}
