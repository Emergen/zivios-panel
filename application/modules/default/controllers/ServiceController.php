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

class ServiceController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function addserviceAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null == ($service = $this->_request->getParam('service'))) {
            if (null == ($dn = $this->_request->getParam('dn'))) {
                throw new Zivios_Error('Invalid request received by Service Controller.');
            } else {
                $dn = strip_tags(urldecode($dn));
            }
            
            $serviceContainer = Zivios_Ldap_Cache::loadDn($dn);
            $parentContainer  = $serviceContainer->getParent();
            $this->view->parentDn = $parentContainer->getdn();
            $this->view->srvcntDn = $dn; // service container dn.
            
            // Load all modules configs.
            $this->view->serviceConfigs = Zivios_Module_ServiceConfigLoader::initServiceAvailability();

        } else {
            // forwarding request (action, controller, module)
            //$this->_forward('addservice', 'service', $service);
            if (null === ($dn = $this->_request->getParam('dn'))) {
                throw new Zivios_Error('Invalid request received by Service controller.');
            }

            $url = '/'.$service.'/service/addservice/dn/'.urlencode($dn);
            $this->_redirect($url);
        }
    }

    public function deletecontainerAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $serviceContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $containerParent = $serviceContainer->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this container
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting ServiceContainer');
            $tgroup = $handler->newGroup('Deleting a Service Container',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $serviceContainer->delete($tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($containerParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Service Container (<em>".
                                  $serviceContainer->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('Service Container deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Service Container. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $serviceContainer;
            $this->view->tabheading = "Delete Service Container";
            $this->view->dataview = "service/container/delete/delete.phtml";
            $this->render('deletecontainer');
        }
    }

    public function viewcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $serviceContainer  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry   = $serviceContainer;
        $this->view->toolbar = "service/container/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "service/container/dashboard/main.phtml";
    }

    public function doaddcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('servicecontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['servicecontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        // get parent dn
        $parentdn = $formData->cleanValues['dn'];

        $serviceContainer = new EMSOrganizationalUnit();
        $serviceContainer->init();

        // Initialize container with suppplied data.
        $serviceContainer->setAddServiceContainerForm($formData->cleanValues);
        
        // Load parent
        $containerParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Service Container');
        $tgroup = $handler->newGroup('Creating New Service Container', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $serviceContainer->add($containerParent, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($containerParent->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                'default/service/viewcontainer/dn/'.$serviceContainer->getdn()));
            $this->addNotify('Service Container added successfully');
        } else {
            throw new Zivios_Error('Error adding Service Container. Please check system logs.');
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

