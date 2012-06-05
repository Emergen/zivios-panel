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

class UserController extends Zivios_Controller
{
    protected function _init() {}

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

        // load dn and ensure it's a user entry we are viewing.
        $userEntry = Zivios_Ldap_Cache::loadDn($dn);

        if (strtolower($userEntry->getProperty('emstype')) != 'userentry') {
            throw new Zivios_Exception('Invalid call made to user controller.');
        }

        $this->view->entry = $userEntry;
    }

    public function loadtoolbarAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading user toolbar. DN not found in request.');
            echo "Error loading user toolbar";
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // load dn and ensure it's a user entry we are viewing.
        $userEntry  = Zivios_Ldap_Cache::loadDn($dn);

        // load user plugin info.
        $modules    = $userEntry->getModules();
        $pluginInfo = Zivios_Module_UserConfigLoader::initPluginConfigs($modules);

        $this->view->availPlugins = Zivios_Module_UserConfigLoader::initPluginConfigs($userEntry->getAllAvailableModules());

        $this->view->plugins = $pluginInfo;
        $this->view->entry   = $userEntry;
        $this->render('toolbar/utb01');
    }

    public function loadaccountdataAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading User account data. DN not found in request.');
            echo "Error loading account data.";
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->userEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $this->render('dashboard/accountdata');
    }

    public function loadgroupsubscribeAction ()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading group subscribe action. DN not found in request.');
            echo "Error loading group subscribe module.";
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->userEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $this->render('dashboard/subscribetogroup');
    }

    public function loadgroupsubscriptionsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::error('Error loading group subscriptions action. DN not found in request.');
            echo "Error loading group subscriptions.";
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // Get all required data
        $userEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $basedn     = Zivios_Ldap_Cache::loadDn($ldapConfig->basedn);
        $filter     = '(&(objectclass=EMSGroup)(member=' . $userEntry->getdn() . '))';
        $userGroups = $basedn->getAllChildren($filter);

        // Assign look-ups to view and render template.
        $this->view->entry        = $userEntry;
        $this->view->sgroups      = $userGroups;
        $this->view->primaryGroup = $userEntry->getPrimaryGroup();
        $this->render('dashboard/groupsubscriptions');
    }

    /**
     * Action adds a user to a specified group.
     *
     * @return json-data
     */
    public function addtogroupAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

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

        $userEntry  = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);
        $groupEntry = Zivios_Ldap_Cache::loadDn($formData->cleanValues['agsearch']);

        // Create transaction handler.
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding user to group');
        $tgroup = $handler->newGroup('Adding user to group', Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Add subscription operation
        $groupEntry->addToGroup($userEntry, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addCallback('zivios.cpaneRefresh', array('utb01'));
            $this->addCallback('zivios.cpaneRefresh', array('userdatabottom'));
            $this->addCallback('zivios.cpaneRefresh', array('userdataright'));
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error adding user to group. Please check system logs.');
        }
    }

    public function viewcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $userContainer  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry   = $userContainer;
        $this->view->toolbar = "user/container/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "user/container/dashboard/main.phtml";
    }

    public function deletecontainerAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $userContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $containerForm = $userContainer->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this container
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting UserContainer');
            $tgroup = $handler->newGroup('Deleting a User Container',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $userContainer->deleteRecursive($tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($containerForm->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> User Container (<em>".
                                  $userContainer->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('User Container deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting User Container. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $userContainer;
            $this->view->tabheading = "Delete User Container";
            $this->view->dataview = "user/container/delete/delete.phtml";
            $this->render('deletecontainer');
        }
    }
    
    /**
     * Add a user container to the Directory
     *
     */
    public function doaddcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('usercontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['usercontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // set parentdn
        $parentdn = $formData->cleanValues['dn'];

        // Initialize container object
        $userContainer = new EMSOrganizationalUnit();
        $userContainer->init();

        // Set container values.
        $userContainer->setAddUserContainerForm($formData->cleanValues);

        // Load parent container
        $containerParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New User Container');
        $tgroup = $handler->newGroup('Creating New User Container', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $userContainer->add($containerParent, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($containerParent->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                'default/user/viewcontainer/dn/'. $userContainer->getdn()));
            $this->addNotify('User Container added successfully');
        } else {
            throw new Zivios_Error('Error adding User Container. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function deleteuserAction()
    {
        $this->_helper->layout->disableLayout(true);

         if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($this->_request->getParam('dn'));
        }

        $userEntry = Zivios_Ldap_Cache::loadDn($dn);
        $parentdn  = urlencode($userEntry->getParent()->getdn());

        // initialize delete user form.
        $dusubform = $userEntry->getDeleteUserForm();

        $form = new Zend_Dojo_Form();
        $form->setName('deleteuser')
             ->setElementsBelongTo('delete-user')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($dusubform,'deleteuserform');
        $hf = new Zend_Form_Element_Hidden('parentdn');
        $hf->setValue($parentdn)
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $form->addElement($hf);
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => "Delete User",
            'onclick'     => "zivios.formXhrPost('deleteuser','/default/user/dodeleteuser'); return false;",
        ));

        $this->view->entry = $userEntry;
        $this->view->form  = $form;
    }

    public function dodeleteuserAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($_POST['deleteuser']['deleteuserform'])) {
            throw new Zivios_Error('Invalid data in request.');
        }

        $userdn   = strip_tags(urldecode($_POST['deleteuser']['deleteuserform']['userdn']));
        $parentdn = strip_tags(urldecode($_POST['deleteuser']['parentdn']));

        $userEntry = Zivios_Ldap_Cache::loadDn($userdn);

        // Initialize transaction group
        $handler = Zivios_Transaction_Handler::getNewHandler('Deleting User Entry');
        $tgroup = $handler->newGroup('Deleting a user',Zivios_Transaction_Group::EM_SEQUENTIAL);

        // delete user.
        $userEntry->delete($tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);
        

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                '/default/user/viewcontainer/dn/'.urlencode($parentdn)));
            $this->refreshTreeNode($parentdn);
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Could not delete user from system. Please check Zivios Logs.');
        }
    }

    public function addcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($this->_request->getParam('dn')));
        }
    }

    public function adduserAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Base entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($dn));
        }
        
        /*
        // Initialize user model.
        $userEntry = new EMSUser();
        $userEntry->init();

        // Request sub-forms from model.
        $uasubform = $userEntry->getUserAccountForm();
        $ulsubform = $userEntry->getUserLoginForm();

        // The home directory attribute needs to be added manually to the form
        // as it is part of the posix plugin. 
        // @todo: get form from posixUser (static helper function would do it).
        $ulsubform->addElement('ValidationTextBox','homedirectory', 
                    array(
                        'required'       => true,
                        'regExp'         => '^[\.\w\s\-\_\/]+$',
                        'label'          => 'Home Directory: ',
                        'invalidMessage' => 'Invalid directory name specified',
                        'value'          => '',
        ));       

        // The login subform disables the uid field by default.
        $element = $ulsubform->getElement('uid');
        $element->setRequired(true);
        $element->setAttrib('disabled', false);

        $gasubform = $userEntry->getAvailableGroupsForm($dn);
        $element   = $gasubform->getElement('agsearch');
        $element->setLabel('Primary Group');

        // Initialize master form.
        $form = new Zend_Dojo_Form();
        $form->setName('adduser')
             ->setElementsBelongTo('add-user')
             ->setMethod('post')
             ->setAction('#');

        // submit button is part of the login form (last tab)
        $form->addSubForm($uasubform,'useraccountform');
        $form->addSubForm($gasubform, "getavailablegroupsform");
        $form->addSubForm($ulsubform,'userloginform');

        // Add the parent DN as a hidden field (remove decorators).
        $hf_parentdn = new Zend_Form_Element_Hidden('parentdn');
        $hf_parentdn->setValue(urlencode($dn))
                    ->removeDecorator('label')
                    ->removeDecorator('HtmlTag');

        $form->addElement($hf_parentdn);


        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => "Add User To System",
            'onclick'     => "zivios.formXhrPost('adduser','/default/user/doadd'); return false;",
        ));

        $this->view->form = $form;
        */

    }

    public function doaddAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('userdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['userdata']);

        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }
            
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        // Initialize user model.
        $userEntry = new EMSUser();
        $userEntry->init();

        $primaryGroup = $formData->cleanValues['agsearch'];
        $primaryGroup = Zivios_Ldap_Cache::loadDn($primaryGroup);
        $parentNode   = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);

        // Set user's primary group
        $userEntry->setPrimaryGroup($primaryGroup);

        // Initialize transaction group
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding a user');
        $tgroup = $handler->newGroup('Adding a user',Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Initialize kerberos & posix plugins
        $kerberosUser = $userEntry->newPlugin('KerberosUser');
        $posixUser    = $userEntry->newPlugin('PosixUser');
        $ldapUser     = $userEntry->newPlugin('OpenldapUser');

        // define ignore array
        $ignoreKeys = array(
            'dn',
            'cpassword',
            'password',
            'agsearch'
        );
        
        // cn is calculated from sn and givenname values
        $formData->cleanValues['cn'] = $formData->cleanValues['givenname'] . ' ' . $formData->cleanValues['sn'];

        // set properties on user object
        $userEntry->setViaForm($formData->cleanValues, $ignoreKeys);

        $userEntry->add($parentNode, $tgroup);
        $userEntry->addPlugin($kerberosUser, $tgroup);
        $userEntry->addPlugin($posixUser, $tgroup);
        $userEntry->addPlugin($ldapUser,$tgroup);

        // register password update
        $userEntry->changePassword($formData->cleanValues['password'], $tgroup);

        // commit & process transaction
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                '/default/user/view/dn/'.urlencode($userEntry->getdn())));
            $this->refreshTreeNode($formData->cleanValues['dn']);
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Could not add user. Please check Zivios logs. ');
        }
    }

    public function unsubscribefromgroupsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->getRequest()->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }

        if (null === ($groupData = $_POST['grouplisting']) ||
            null === ($userdn = strip_tags(urldecode($_POST['userdn'])))) {
            throw new Zivios_Error('Required data not present in request.');
        }

        $userEntry = Zivios_Ldap_Cache::loadDn($userdn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Removing user from group(s)');
        $tgroup = $handler->newGroup('Removing user from group(s)', Zivios_Transaction_Group::EM_SEQUENTIAL);

        foreach ($groupData as $group => $active) {
            $group = strip_tags(urldecode($group));
            $groupObj = Zivios_Ldap_Cache::loadDn($group);
            $groupObj->removeFromGroup($userEntry, $tgroup);
        }
        
        // commit transaction & process.
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addCallback('zivios.cpaneRefresh', array('utb01'));
            $this->addCallback('zivios.cpaneRefresh', array('userdatabottom'));
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error removing user from group. Please check system logs.');
        }
    }

    
    
    /**
     * Action tied to the zivios auto-completion store.
     *
     * @return json-data
     */
    public function getavailablegroupsAction()
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

            $userEntry  = Zivios_Ldap_Cache::loadDn($dn);
            $filter     = '(&(objectclass=EMSGroup)(cn='.$query.')' .
                          '(!(member='.$userEntry->getdn().'))(!(objectclass=emsIgnore)))';
            $groups     = $userEntry->getAllPossibleGroups($filter);

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
    
    public function logindialogAction()
    {
        $this->view->pwdmustchange = true;
    }
    
    public function logindialogupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $form = $this->processForm('pw');
        Zivios_Log::debug($form);
        $newpw = $form['newpw'];
        $cnewpw = $form['cnewpw'];
        if ($newpw != $cnewpw) {
            $this->view->error = "Passwords do not Match";
            $this->view->pwdmustchange = true;
        } else {
        
            $usercreds = Zivios_Ldap_Engine::getUserCreds();
            $dn = $usercreds['dn'];
            $myself = Zivios_Ldap_Cache::loadDn($dn,'EMSUser');
            $handler = Zivios_Transaction_Handler::getNewHandler('Forceful Password change on expiration for :'.$dn);
            $tgroup = $handler->newGroup('Forceful Password change on expiration for :'.$dn,Zivios_Transaction_Group::EM_SEQUENTIAL);
            
            $myself->changePassword($newpw,$tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);
            
            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->view->success = 'Password Successfully Updated';
            } else {
                $this->view->error = $handler->getLastExceptionMessage();
                $this->view->pwdmustchange = true;
            }
        }
        
        $this->render('logindialog');
   
    }

    public function updateuseraccountAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('userdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['userdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // load user entry
        $userEntry = Zivios_Ldap_Cache::loadDn($formData->cleanValues['userdn']);

        // set cn field
        $formData->cleanValues['cn'] = $formData->cleanValues['givenname'] . 
            ' ' . $formData->cleanValues['sn'];
        
        // if 'emsaccountlockout' is not set, pass it as 0.
        if (!isset($formData->cleanValues['emsaccountlockout'])) {
            $formData->cleanValues['emsaccountlockout'] = 0;
        }
        
        // set ignore fields value
        $ignoreKeys = array('userdn');

        // update user entry
        $userEntry->setViaForm($formData->cleanValues, $ignoreKeys);

        // create transaction handler
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating user account details');
        $tgroup = $handler->newGroup('Updating user account details',Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Run transaction
        $userEntry->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        // Get the parent dn to refresh the tree node.
        $parentDn = $userEntry->getParent()->getdn();

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($parentDn);
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error updating user properties. Please check Zivios logs.');
        }
    }

    public function updateuserloginAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('userlogindata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['userlogindata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        $userEntry = Zivios_Ldap_Cache::loadDn($formData->cleanValues['userdn']);

        // create transaction handler
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating user password');
        $tgroup = $handler->newGroup('Updating user password',Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Run transaction
        $userEntry->changePassword($formData->cleanValues['password'], $tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);
        $this->sendResponse();
        /*if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error updating user properties. Please check Zivios logs.');
        }*/
        
    }
}

