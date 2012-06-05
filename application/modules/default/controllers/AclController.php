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

class AclController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }
    
    public function manageaclAction() 
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);        
    }
    
    public function acidispAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        $secobj = $this->view->entry->getSecurityObject();
        $this->view->aciarray = $secobj->aci_array;
    }
    
    public function emsacldispAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
        $secobj = $this->view->entry->getSecurityObject();
        $this->view->emsaclarray = $secobj->emsacl_array;
    }
    
    public function renderformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->type = $this->getParam('type');
        $this->view->dn = $this->getParam('dn');
    }
     
    public function doaddaclAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = $this->getParam('dn');
        $acl = new Zivios_Acl($dn);
        $acl->setAction($this->getParam('aclaction'));
        $acl->setScope($this->getParam('scope'));
        $acl->setAclName($this->getParam('acl'));
        $acl->setType($this->getParam('appliesto'));
        $acl->setSubject(urldecode($this->getParam('user')));
        $obj = Zivios_Ldap_Cache::loadDn($dn);
        $emssec = $obj->getSecurityObject();
        $emssec->addEmsAcl($acl);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding a Zivios ACL');
        $tgroup = $handler->newGroup('Adding a Zivios ACL',Zivios_Transaction_Group::EM_SEQUENTIAL);
        $emssec->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('emsacldisp');
            $this->refreshPane('aclform','/acl/renderform');
        }
        $this->sendResponse();       
        
    }
    public function doaddaciAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $this->view->type = $this->getParam('type');
        $aci = new Zivios_Ldap_Aci($this->getParam('dn'));
        $aci->setAction($this->getParam('aciaction'));
        $aci->setType($this->getParam('appliesto'));
        $aci->setSubject(urldecode($this->getParam('user')));
        $aci->setScope($this->getParam('scope'));
        
       
        /**
         * This is currently hardcoded as per attribute perms
         * cannot be handled currently
        */
        $aci->setTarget(Zivios_Ldap_Aci::TARGET_ALL);
        $perms = $this->getParam('perms');
         
        if ($perms == null || $perms == "") 
            throw new Zivios_Error("Cannot created ACI without permissions.");
         
        if (!is_array($perms)) {
			$permarray = array();
			$permarray[] = $perms;
		} else
			$permarray = $perms;

        $aci->setPerms($permarray);
        $obj = Zivios_Ldap_Cache::loadDn($this->getParam('dn'));
        $emssec = $obj->getSecurityObject();
        $emssec->addLdapAci($aci);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding OpenLdap ACI');
        $tgroup = $handler->newGroup('Adding a OpenLdap ACI',Zivios_Transaction_Group::EM_SEQUENTIAL);
        $emssec->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('acidisp');
            $this->refreshPane('aclform','/acl/renderform');
        }
        $this->sendResponse();       
    }
    
    public function deleteaciAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = $this->getParam('dn');
        $origline = $this->getParam('origline');
        
        $emssec = Zivios_Ldap_Cache::loadDn($dn)->getSecurityObject();
    	$emssec->removeLdapAci($origline);
        $handler = Zivios_Transaction_Handler::getNewHandler('Removing OpenLdap ACI');
        $tgroup = $handler->newGroup('Removing a OpenLdap ACI',Zivios_Transaction_Group::EM_SEQUENTIAL);
    	$emssec->update($tgroup);
    	$tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('acidisp');
        }
        
        $this->sendResponse();       
    }
    
    public function deleteaclAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = $this->getParam('dn');
        $origline = $this->getParam('origline');
        
        $emssec = Zivios_Ldap_Cache::loadDn($dn)->getSecurityObject();
    	$emssec->removeEmsAcl($origline);
        $handler = Zivios_Transaction_Handler::getNewHandler('Removing Zivios ACL');
        $tgroup = $handler->newGroup('Removing a Zivios ACL',Zivios_Transaction_Group::EM_SEQUENTIAL);
    	$emssec->update($tgroup);
    	$tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('emsacldisp');
        }
        
        $this->sendResponse();  
    }
    
    public function getallusersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        Zivios_Log::debug("output :".$this->getParam('q'));
        $query = $this->getParam('q');
        if ($query == null) {
            echo Zend_Json::encode(array());
            return;
        } else {
            if ($query == '*') {
                echo Zend_Json::encode(array());
                return;
            }

            // proceed with user lookup if at least 3 characters
            // have come in the query.
            $query = trim(strip_tags($query));
            if (strlen($query) < 3) {
                echo Zend_Json::encode(array());
                return;
            }

            // proceed with lookup.
            Zivios_Log::debug($query);
            $ldapconf = Zend_Registry::get('ldapConfig');
            $base = $ldapconf->basedn;
            $emsobj = Zivios_Ldap_Cache::loadDn($base);

            $filter     = '(&(objectclass=EMSUser)(cn='.$query.')(!(objectclass=emsIgnore)))';
            $users     = $emsobj->getAllChildren($filter,null,null,null,'NOMODEL');

            $userData  = array();
            if (is_array($users) && !empty($users)) {
                foreach ($users as $userEntry) {
                    $userData[urlencode($userEntry->getdn())] = $userEntry->getProperty('cn');
                }
            } else {
                echo Zend_Json::encode(array());
                return;
            }

            $this->_helper->autoCompleteDojo($userData);
        }
    }
    
    
}
    
    
