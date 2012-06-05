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
 * @package     mod_openldap
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Openldap_ServiceController extends Zivios_Controller
{
    protected function _init() {}

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        // Initialize service & master server in view.
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->iniDashboardLoader($serviceEntry);
    }

    public function loadtoolbarAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->render('toolbars/primarytb');
    }

    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
    }

    public function cfginfoAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }

        $this->render('config/info');
    }

    public function cfgindexesAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->primaryIndexes = $serviceEntry->getPrimaryDbIndexes();
        $this->view->service = $serviceEntry;
        $this->render('config/indexes');
    }

    public function cfgupdateindexesAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid Request Type');
        }

        $formData = $_POST;

        if (!isset($formData['servicedn']) || !isset($formData['indexes'])) {
            throw new Zivios_Error('Invalid / Missing data in request.');
        } else {
            $dn = urldecode($formData['servicedn']);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Updating OpenLDAP Core Configuration');
        $tgroup = $handler->newGroup('Updating Primary DB Indexes',Zivios_Transaction_Group::EM_SEQUENTIAL);

        $serviceEntry->refreshPrimaryDbIndexes($formData['indexes'], $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Indexes updated successfully. Restart Directory Service.');
            $this->addCallback('zivios.cpaneRefresh', array('ldapconfdata', 
                '/openldap/service/cfgindexes/dn/'.$serviceEntry->getdn()));
        } else {
            throw new Zivios_Error('Error updating Primary DB Indexes. Please check Zivios and OpenLDAP Logs.');
        }

        $this->sendResponse();
    }

    public function cfgaddindexAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid Request Type');
        }

        $formData = $_POST;

        if (!isset($formData['servicedn']) || !isset($formData['addindex'])) {
            throw new Zivios_Error('Invalid / Missing data in request.');
        } else {
            $dn = urldecode($formData['servicedn']);
        }

        // ensure some properties were specified for index.
        if (count($formData['addindex']) < 2) {
            throw new Zivios_Error('No properties specified for Index.');
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating OpenLDAP Core Configuration');
        $tgroup = $handler->newGroup('Adding Index to Primary DB',Zivios_Transaction_Group::EM_SEQUENTIAL);

        $serviceEntry->addPrimaryDbIndex($formData['addindex'], $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Index added successfully. Restart Directory Service.');
            $this->addCallback('zivios.cpaneRefresh', array('ldapconfdata', 
                '/openldap/service/cfgindexes/dn/'.$serviceEntry->getdn()));
        } else {
            throw new Zivios_Error('Error adding Index to Primary DB. Please check Zivios and OpenLDAP Logs.');
        }

        $this->sendResponse();
    }

    public function cfglogsAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->loglevel = $serviceEntry->getLogLevel();
        $this->view->service = $serviceEntry;
        $this->render('config/logs');
    }

    public function cfglogsupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            throw new Zivios_Error('Invalid Request Type');
        }
        
        $formData = $_POST;

        if (!isset($formData['servicedn'])) {
            throw new Zivios_Error('Invalid / Missing data in request.');
        } else {
            $dn = urldecode($formData['servicedn']);
        }
        
        // if no data is available, we switch loglevel off.
        if (!isset($formData['loglevel']) || empty($formData['loglevel'])) {
            $formData['loglevel']['off'] = 1;
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Updating OpenLDAP Core Configuration');
        $tgroup = $handler->newGroup('Updating Log Level',Zivios_Transaction_Group::EM_SEQUENTIAL );

        $serviceEntry->updateLogLevel($formData['loglevel'], $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Log Level updated successfully.');
        } else {
            throw new Zivios_Error('Error updating OpenLDAP log level. Please check Zivios and OpenLDAP Logs.');
        }

        $this->sendResponse();
    }

    public function cfgreplicasAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->render('config/replicas');
    }

    public function listreplicasAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (!isset($_POST['data'])) {
            throw new Zivios_Error('Missing data in request');
        } else {
            $dn = urldecode($_POST['data']);
            $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        }

        $this->view->replicas = $this->view->service->getReplicas();

        if (empty($this->view->replicas)) {
            $this->render('config/noreplicasystems');
        } else {
            $this->render('config/listreplicas');
        }
    }

    public function managereplicaAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (!isset($_POST['serviceDn']) || !isset($_POST['replicaDn'])) {
            throw new Zivios_Exception('Missing data from request.');
        } else {
            $masterServiceDn  = urldecode(trim(strip_tags($_POST['serviceDn'])));
            $replicaServiceDn = urldecode(trim(strip_tags($_POST['replicaDn'])));
        }

        $this->view->service = Zivios_Ldap_Cache::loadDn($masterServiceDn);
        $this->view->replicaService = Zivios_Ldap_Cache::loadDn($replicaServiceDn);
        $this->view->replicaServer  = $this->view->replicaService->mastercomp;
        
        // render the template
        $this->render('config/managereplica');
    }

    public function cfgdbtuningAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->render('config/dbtuning');
    }

    public function cfgupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
    }

    public function monitorAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function schemamanagerAction()
    {
        $this->_helper->layout->disableLayout(true);
    }
}

