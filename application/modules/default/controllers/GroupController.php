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

class GroupController extends Zivios_Controller
{
    protected function _init()
    {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function viewAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // load dn and ensure it's a group entry we are viewing.
        $groupEntry  = Zivios_Ldap_Cache::loadDn($dn);

        // load user plugin info.
        $modules    = $groupEntry->getModules();
        $pluginInfo = Zivios_Module_GroupConfigLoader::initPluginConfigs($modules);

        if (strtolower($groupEntry->getProperty('emstype')) != 'groupentry') {
            throw new Zivios_Exception('Invalid call made to group controller.');
        }

        $this->view->entry   = $groupEntry;
        $this->view->plugins = $pluginInfo;

        $this->view->toolbar = "group/toolbar/gtb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "group/dashboard/layout.phtml";
    }

    public function getallgroupsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        // unless a dn is specified for the search, we return
        // an empty array.
        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('no dn specified...');
            echo Zend_Json::encode(array());
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // check query for legality.
        if (null === ($query = $this->_request->getParam('q'))) {
            // ignore lookups till 'q' is sent
            echo Zend_Json::encode(array());
            return;
        } else {
            // proceed with group lookup if at least 3 characters
            // have come in the query.
            $query = trim(strip_tags($query));
            if (strlen($query) < 2) {
                echo Zend_Json::encode(array());
                return;
            }

            $ouentry  = Zivios_Ldap_Cache::loadDn($dn);
            $filter     = '(&(objectclass=EMSGroup)(cn='.$query.')' .
                          '(!(member='.$ouentry->getdn().'))(!(objectclass=emsIgnore)))';
            $groups     = $ouentry->getAllPossibleGroups($filter,'NOMODEL');

            $groupData  = array();
            if (is_array($groups) && !empty($groups)) {
                foreach ($groups as $groupEntry) {
                    $groupData[urlencode($groupEntry->getdn())] = $groupEntry->getProperty('cn');
                    Zivios_Log::debug($groupData);
                }
                
                // Get response and send to client.
                $response = $this->_helper->autoCompleteDojo
                                 ->prepareAutoCompletion($groupData);
                echo $response;
                return;

            } else {
                echo Zend_Json::encode(array());
                return;
            }
        }
    }
    
    public function subscribeouAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->_request->getParam('dn'));
        $ou = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->users = $ou->getAllChildren('(objectclass=emsuser)',null,null,null,'NOMODEL');
        $this->view->ou = $ou;
        
                
    }
    
    public function dosubscribeouAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        Zivios_Log::debug($_POST);
        if (!Zivios_Util::isFormPost('addtogroup')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['addtogroup']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
                Zivios_Log::debug($formData->cleanValues);

        $group = Zivios_Ldap_Cache::loadDn($formData->cleanValues['agsearch']);
        $ou = Zivios_Ldap_Cache::loadDn($this->getParam('oudn'));
        $users = $ou->getAllChildren('(objectclass=emsuser)',null,null,null,'NOMODEL');
        $handler = Zivios_Transaction_Handler::getNewHandler('Bulk Subscribing Users in OU : '.$ou->getdn() . ' to group : '.$group->getdn());
        foreach ($users as $user) {
            
            $userobj = Zivios_Ldap_Cache::loadDn($user->getdn());
            if (!$group->hasImmediateMember($userobj)) { 
                $tgroup = $handler->newGroup('Adding User '.$userobj->getdn().' to group', Zivios_Transaction_Group::EM_SEQUENTIAL);
                $group->addToGroup($userobj,$tgroup);
                $tgroup->commit();
            }
        }
        
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            //$this->refreshTreeNode($group->->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                '/default/group/view/dn/'.urlencode($group->getdn())));
        }
        
        $this->sendResponse();
    }
    public function loadtoolbarAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // load dn and ensure it's a group entry we are viewing.
        $groupEntry  = Zivios_Ldap_Cache::loadDn($dn);

        // load user plugin info.
        $modules    = $groupEntry->getModules();
        $pluginInfo = Zivios_Module_GroupConfigLoader::initPluginConfigs($modules);

        if (strtolower($groupEntry->getProperty('emstype')) != 'groupentry') {
            throw new Zivios_Exception('Invalid call made to group controller.');
        }

        $services = $groupEntry->getAvailableServices();
        $modules = array();

        if ($services != null && sizeof( $services ) > 0) {
            $this->view->availPlugins = Zivios_Module_GroupConfigLoader::initAvailServicesConfigs($services);
        } else {
            $this->view->availPlugins = array();
        }

        $this->view->entry   = $groupEntry;
        $this->view->plugins = $pluginInfo;
        $this->render('toolbar/gtb01');
    }

    public function doaddAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('groupdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['groupdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // get DN of parent container.
        $parentdn = $formData->cleanValues['dn'];

        $groupEntry = new EMSGroup();
        $groupEntry->init();
        $groupEntry->setMainForm($formData->cleanValues);

        $groupparent = Zivios_Ldap_Cache::loadDn($parentdn);

        $posixplug = $groupEntry->newPlugin("PosixGroup");
        $krbplug = $groupEntry->newPlugin("KerberosGroup");
        $caplug = $groupEntry->newPlugin("CaGroup");
        $ldapplug = $groupEntry->newPlugin("OpenldapGroup");

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Group');
        $tgroup = $handler->newGroup('Creating New Group', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $groupEntry->add($groupparent,$tgroup);

        $posixplug->setGid(-1);
        $groupEntry->addPlugin($posixplug, $tgroup);

        // Instantiate Master Kerberos Service and link group to it.
        $service = $krbplug->getMasterService();

        if (!$service instanceof KerberosService) {
            throw new Zivios_Error( "Kerberos Service for Group could not be" .
                " initialized. Please ensure the Master Kerberos Service is running");
        }

        $krbplug->linkToService($service);
        $groupEntry->addPlugin($krbplug, $tgroup);

        // Add CA group plugin to new group. Link group to service.
        $service = $caplug->getMasterService();

        if (!$service instanceof CaService) {
            throw new Zivios_Error("CA Service for Group could not be" .
                " initialized.");
        }

        $caplug->linkToService($service);
        $groupEntry->addPlugin($caplug, $tgroup);
        
        $service = $ldapplug->getMasterService();
        $ldapplug->linkToService($service);
        $groupEntry->addPlugin($ldapplug,$tgroup);
        
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($groupparent->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                '/default/group/view/dn/'.urlencode($groupEntry->getdn())));
        }

        $this->sendResponse();
    }

    public function addAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($dn));
        }
    }

    public function subscribeusersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $dn = urldecode($this->_request->getParam('dn'));
        $group = Zivios_Ldap_Cache::loadDn($dn);

        $users = $this->_request->getParam('users');
        $handler = Zivios_Transaction_Handler::getNewHandler('Subscribing Users to Group');

        foreach ($users as $user) {
            $usrObj = Zivios_Ldap_Cache::loadDn(urldecode($user));
            $tgroup = $handler->newGroup('Subscribing User '. $usrObj->getdn() . 'to group',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $group->addToGroup($usrObj,$tgroup);
            $tgroup->commit();
        }       

        $this->processTransaction($handler);
        $this->addCallback('zivios.cpaneRefresh', array('searchresults'));
        $this->addCallback('zivios.cpaneRefresh', array('groupdataleft'));
        $this->sendResponse();
    }

    public function unsubscribeusersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $dn = urldecode($this->_request->getParam('dn'));
        $group = Zivios_Ldap_Cache::loadDn($dn);

        $users = $this->_request->getParam('users');
        $handler = Zivios_Transaction_Handler::getNewHandler('Unsubscribing Users from Group');

        foreach ($users as $user) {
            $usrObj = Zivios_Ldap_Cache::loadDn(urldecode($user));
            $tgroup = $handler->newGroup('Removing User '. $usrObj->getdn() . 'from group',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $group->removeFromGroup($usrObj,$tgroup);
            $tgroup->commit();
        }
        $this->processTransaction($handler);
        $this->addCallback('zivios.cpaneRefresh', array('searchresults'));
        $this->addCallback('zivios.cpaneRefresh', array('groupdataleft'));
        $this->sendResponse();
    }

    public function searchAction()
    {
        $this->_helper->layout->disableLayout(true);

        $dn     = $this->_request->getParam('dn');
        $search = $this->_request->getParam('filter');
        $type   = $this->_request->getParam('type');

        if ($type == "") {
            // initial load -- no need to perform a search.
            $this->view->iniLoad = true;
            $this->render('dashboard/searchresults');
            return;
        }

        $group = Zivios_Ldap_Cache::loadDn($dn);
        //$members = $group->getAllImmediateUsers(true);
        //$members = $group->getProperty('member');

        $this->view->returnusers = array();
        $this->view->type = $type;

        if ($type == 'members') {
            $root = Zivios_Util::getRoot();
            $filter = "(&(objectclass=EMSUser)(cn=*".$search."*)(memberof=".$group->getdn().")(!(cn=placeholder)))";
            $this->view->returnusers = $group->getAllChildren($filter,null,null,$root,'NOMODEL');
            
        } else if ($type == 'nonmembers') {
            $ldapConfig = Zend_Registry::get('ldapConfig');
            $root = Zivios_Util::getRoot();
            $groupMembers = $group->getProperty("member",1);
            $filter = '(&(objectclass=EMSUser)(cn=*'.$search.'*)(!(cn=placeholder))(!(memberof='.$group->getdn().')))';
            $this->view->returnusers = $group->getAllChildren($filter,null,null,$root,'NOMODEL');

            /*$this->view->returnusers = array();
            foreach ($allMembers as $memberEntry) {
                if (!in_array($memberEntry->getdn(), $groupMembers) && $memberEntry != $ldapConfig->placeholder)
                    $this->view->returnusers[] = $memberEntry;
            }
            */

        }

        $this->view->entry = $group;
        $this->render('dashboard/searchresults');
    }

    public function loadaccountdataAction()
    {
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->_helper->layout->disableLayout(true);
        $group = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->group = $group;
        $this->render('dashboard/main');
    }

    public function deleteAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        $groupEntry  = Zivios_Ldap_Cache::loadDn($dn);

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this group
            $parentnode = $groupEntry->getParent()->getdn();
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Group');
            $tgroup = $handler->newGroup('Deleting a Single Group',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $groupEntry->delete($tgroup);
            $tgroup->commit();

            
            $status = $this->processTransaction($handler);
            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($parentnode);
                $this->refreshPane('dirdata','/default/search/forwardtoresult/dn/'.urlencode($parentnode));
            }
            
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            // load user plugin info.
            $modules    = $groupEntry->getModules();
            $pluginInfo = Zivios_Module_GroupConfigLoader::initPluginConfigs($modules);

            $this->view->entry   = $groupEntry;
            $this->view->plugins = $pluginInfo;
        }
    }

    public function viewcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $groupContainer  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry   = $groupContainer;
        $this->view->toolbar = "group/container/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "group/container/dashboard/main.phtml";
    }

    public function deletecontainerAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $groupContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $containerParent = $groupContainer->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this container
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting GroupContainer');
            $tgroup = $handler->newGroup('Deleting a Group Container',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $start = microtime(true);
            $groupContainer->deleteRecursive($tgroup);
            $stop = microtime(true);
            
            Zivios_Log::info("Delete Recursive Transaction Construction done on ".$groupContainer->getdn()." took :".($stop-$start)."s"); 
            $tgroup->commit();
            
            $start = microtime(true);
            $status = $this->processTransaction($handler);
            $stop = microtime(true);
            
            Zivios_Log::info("Delete transaction execution took :".($stop-$start)."s");

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($containerParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Group Container (<em>".
                                  $groupContainer->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('Group Container deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Group Container. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $groupContainer;
            $this->view->tabheading = "Delete Group Container";
            $this->view->dataview = "group/container/delete/delete.phtml";
            $this->render('deletecontainer');
        }
    }

    public function doaddcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('groupcontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['groupcontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // set parentdn
        $parentdn = $formData->cleanValues['dn'];

        $groupContainer = new EMSOrganizationalUnit();
        $groupContainer->init();
        $groupContainer->setAddGroupContainerForm($formData->cleanValues);
        
        // Load parent container object.
        $containerParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Group Container');
        $tgroup = $handler->newGroup('Creating New Group Container', Zivios_Transaction_Group::EM_SEQUENTIAL );
        $groupContainer->add($containerParent, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($containerParent->getdn());
            $this->addNotify('Group Container added successfully');
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                'default/group/viewcontainer/dn/' . $groupContainer->getdn()));
        } else {
            throw new Zivios_Error('Error adding Group Container. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function addcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($dn));
        }
    }
}

