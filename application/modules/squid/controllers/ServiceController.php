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

class Squid_ServiceController extends Zivios_Controller_Service
{
    public function predispatch()
    {
        parent::predispatch();
    }

    protected function _init()
    {
        parent::_init();
        $this->view->displayname = $this->_serviceConfig->general->displayname;
    }

    public function addserviceAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $serviceContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $squidService = new SquidService();
        $squidService->init();

        // Get all compatible computers for the service in question.
        $compatComputers = $squidService->getCompatibleComputers($serviceContainer);
        $this->view->compatComps = $compatComputers;
        
        // Compatible computers were found. Load form.
        if (is_array($compatComputers)) {
            $this->view->form = $squidService->getAddServiceForm($dn);
        }
    }

    public function doaddserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }

        if (!is_array($_POST['addserviceform']) || (!isset($_POST['addserviceform']['dn']))) {
            throw new Zivios_Error('Required information missing from add service request.');
        } else {
            $dn = strip_tags(urldecode($_POST['addserviceform']['dn']));
            $formdata = $_POST['addserviceform'];
        }

        // Ensure a server was selected.
        if (!isset($formdata['serviceconfigform']['emsmastercomputerdn']) ||
            $formdata['serviceconfigform']['emsmastercomputerdn'] == '-1') {
            throw new Zivios_Error('Please select a Server.');
        }

        // Load service container.
        $serviceContainer = Zivios_Ldap_Cache::loadDn($dn);
        
        // All information available. Initialize service object and test remote system
        // for service activation.
        $squidService = new SquidService();
        $squidService->init();
        $squidService->setAddServiceForm($formdata);
        
        // Check communication with remote server
        if (!$squidService->pingZiviosAgent()) {
            throw new Zivios_Error('Communication failed with Zivios agent. Please ensure '
            .'that the Zivios agent service is running on the remote computer.');
        }

        // Check service status on remote server
        if (!$squidService->getServiceStatus()) {
            throw new Zivios_Error('It appears that the squid service is not running on the remote'.
            ' system.');
        }

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Squid Service');
        $tgroup = $handler->newGroup('Adding Squid Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $squidService->add($serviceContainer, $tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($serviceContainer->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                '/squid/service/dashboard/dn/'.urlencode($squidService->getdn())));
            $this->addNotify('Squid Service added successfully. <b>Please restart the service</b>.');
        } else {
            throw new Zivios_Error('Error adding Squid Service. Please check system logs.');
        }

        $this->sendResponse();
    }
    
    public function serviceconfigAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

        // Initialize add service form. 
        $form = new Zend_Dojo_Form();
        $form->setName('configform')
             ->setElementsBelongTo('configform')
             ->setMethod('post')
             ->setAction('#');
        
        $configForm = $serviceEntry->getServiceConfigForm();
        $form->addSubForm($configForm, "serviceconfigform");

        $form->addElement('submitButton', 'submit', array(
           'required'    => false,
           'ignore'      => true,
           'label'        => 'Update Service Configuration',
           'onclick'     => "zivios.formXhrPost('configform','/squid/service/doserviceconfig'); return false;",
        ));

        // Add hidden field for service dn.
        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        $form->addElement($hfdn);
        $this->view->form =  $form;
    }

    public function doserviceconfigAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }

        if (!is_array($_POST['configform']) || (!isset($_POST['configform']['dn']))) {
            throw new Zivios_Error('Required information missing from add service request.');
        } else {
            $dn = strip_tags(urldecode($_POST['configform']['dn']));
            $formdata = $_POST['configform']['serviceconfigform'];
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $serviceEntry->setServiceConfigForm($formdata);

        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Squid service configuration.');
        $tgroup = $handler->newGroup('Updating service configuration for: ' .
            $serviceEntry->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);
        
        $serviceEntry->update($tgroup);
        $serviceEntry->_configUpdate($tgroup, 'Updating Squid configuration on Squid server');
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Squid Service updated successfully.');
        } else {
            throw new Zivios_Error('Error updating Squid service. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function trustednetworksconfigAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing in request');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function addtrustednetworkAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('squidtrustednetworks')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['squidtrustednetworks']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);
        $serviceEntry->setTrustedNetworksForm($formData->cleanValues);

        // Create transaction for service update.
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Squid trusted networks.');
        $tgroup = $handler->newGroup('Updating trusted networks for squid service: ' .
            $serviceEntry->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);
        
        $serviceEntry->update($tgroup);
        $serviceEntry->_trustedNetworksUpdate($tgroup, 'Updating Squid trusted networks on Squid server');
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Squid trusted networks updated successfully. Please restart the Squid service.');
            $this->refreshPane('squidlisttnets');
        } else {
            throw new Zivios_Error('Error updating trusted networks. Please check system logs.');
        }

        $this->sendResponse();        
    }
    
    public function listtrustednetworksAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing in request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
    }

    public function removetrustednetworksAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($_POST['trustednet']) || !is_array($_POST['trustednet']) || 
            empty($_POST['trustednet'])) {
            throw new Zivios_Error('Please select at least one network to remove.');
        }

        if (null === ($dn = $_POST['dn'])) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $serviceEntry->removeTrustedNetworks($_POST['trustednet']);

        // Create transaction for service update.
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Squid trusted networks.');
        $tgroup = $handler->newGroup('Updating trusted networks for squid service: ' .
            $serviceEntry->getProperty('cn'), Zivios_Transaction_Group::EM_SEQUENTIAL);
        
        $serviceEntry->update($tgroup);
        $serviceEntry->_trustedNetworksUpdate($tgroup, 'Updating Squid trusted networks on Squid server');
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Squid trusted networks updated successfully. Please restart the Squid service.');
            $this->refreshPane('squidlisttnets');
        } else {
            throw new Zivios_Error('Error updating trusted networks. Please check system logs.');
        }

        $this->sendResponse();   

    }

    public function servicegroupsAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Required data missing from request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $serviceEntry   = Zivios_Ldap_Cache::loadDn($dn);
        $serviceMembers = $serviceEntry->getAllSubscribingGroups();
        
        // Assign to view
        $this->view->service = $serviceEntry;
        $this->view->members = $serviceMembers;
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->dn = $dn;
    }

    public function loaddashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->masterComputer = $this->view->service->getMasterComputer();
        $this->render('dashboard/main');
    }

    /**
     * Start or stop the squid service. 
     *
     * @return JSON
     */
    public function servicectrlAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->getRequest()->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }

        if (null === ($dn     = $_POST['dn']) ||
            null === ($action = $_POST['action'])) {
            throw new Zivios_Error('Required data not present in request.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        // Load service.
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

        switch ($action) {
            case "start": 
                if ($serviceEntry->startService()) {
                    $this->addNotify('Squid Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('squidsrvctrl'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start Squid Service. Please see system logs.');
                }
            break;

            case "stop" : 
                if ($serviceEntry->stopService()) {
                    $this->addNotify('Squid Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('squidsrvctrl'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop Squid Service. Please see system logs.');
                }
            break;

            case "restart" :
                throw new Zivios_Error('Restart option pending implementation.');
            break;

            default: 
                throw new Zivios_Error('Unknown command option for service.');
        }
    }
}

