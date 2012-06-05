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

class ZreplicaController extends Zivios_Controller
{
    protected $model;

    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function addziviosreplicaAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Invalid request -- missing "dn"');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $directoryServiceDn = 'cn=zivios directory,ou=master services,ou=core control,ou=zivios,'
                              . $ldapConfig->basedn;

        $ldapService = Zivios_Ldap_Cache::loadDn($directoryServiceDn);
        $this->view->compatibleSystems = $ldapService->getReplicaCandidates($dn);
        $this->view->parentdn = $dn;

        if (empty($this->view->compatibleSystems)) {
            $this->view->replicaCompatible = $ldapService->getReplicaCompatibleForDisplay();
            $this->render('noreplicacandidates');
        } else {
            $this->view->service = $ldapService;
            $this->render();
        }
    }

    public function doaddziviosreplicaAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // validate submitted data.
        $formData = $this->processForm('zvreplicadata');

        // ensure that all defined "dn" constructs are available.
        // we do this before creating or trying to run the transaction.
        $checkDn   = array();
        $checkDn[] = 'cn='.$formData['ldapcn'] . ',' . $formData['dn'];
        $checkDn[] = 'cn='.$formData['krbcn']  . ',' . $formData['dn'];
        $checkDn[] = 'cn='.$formData['dnscn']  . ',' . $formData['dn'];

        $checkCn   = array();
        $checkCn[] = $formData['ldapcn'];
        $checkCn[] = $formData['krbcn'];
        $checkCn[] = $formData['dnscn'];

        for ($c = 0; $c < count($checkDn); $c++) {
            $testDn = new Zivios_Ldap_Engine($checkDn[$c]);
            Zivios_Log::debug('testing DN: ' . $checkDn[$c]);
            if ($testDn->exists()) {
                throw new Zivios_Error('Defined label is already in use.');
            }
        }

        for ($c = 0; $c < count($checkCn); $c++) {
            $cnTest = array_pop($checkCn);

            if (in_array($cnTest, $checkCn)) {
                throw new Zivios_Error('Labels must be unique.');
            }
        }

        // create a temporary folder for replica deployment
        $folderName = Zivios_Util::randomString(6);
        $folderPath = APPLICATION_PATH . '/tmp';

        if (!mkdir($folderPath . '/' . $folderName, 0700)) {
            throw new Zivios_Error('Could not create temporary folder for replica setup.');
        }
        
        // load service container
        $serviceContainer = Zivios_Ldap_Cache::loadDn($formData['dn']);

        // Initialize replica deployment models
        $ldapReplica = new OpenldapReplicaService();
        $ldapReplica->init();
        $replicaData = $ldapReplica->setServiceProperties($formData);
        $ldapReplica->testPrerequisites();
        $ldapReplica->setTempFolderPath($folderPath, $folderName);

        $krbReplica = new KerberosReplicaService();
        $krbReplica->init();
        $krbReplica->setServiceProperties($formData);
        $krbReplica->setTempFolderPath($folderPath, $folderName);

        $dnsReplica = new DnsReplicaService();
        $dnsReplica->init();
        $dnsReplica->setServiceProperties($formData);
        $dnsReplica->setTempFolderPath($folderPath, $folderName);

        // initialize the master directory service
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $directoryServiceDn = 'cn=zivios directory,ou=master services,ou=core control,'
            .'ou=zivios,' . $ldapConfig->basedn;

        $masterService = Zivios_Ldap_Cache::loadDn($directoryServiceDn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Zivios Replica Setup');
        $tgroup  = $handler->newGroup('Initializing Replica Services', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $ldapReplica->add($serviceContainer, $tgroup, "Initializing OpenLDAP Replica Service");
        $krbReplica->add($serviceContainer, $tgroup, "Initializing Kerberos Replica Service");
        $dnsReplica->add($serviceContainer, $tgroup, "Initializing DNS Replica Service");
        $masterService->registerReplica($replicaData, $tgroup, "Registering Replica with Zivios Directory service");
        $tgroup->commit();

        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($serviceContainer->getdn());
            $this->addNotify('Zivios Replica service setup successful!');

            $this->addCallback('zivios.hideDiv', array('addreplicadata'));
            $this->addCallback('zivios.showDiv', array('replicaaddsuccess'));

            // recursively remove the temporary data folder
            Zivios_Util::rmTmpFolder($folderPath . '/' . $folderName);
        }

        $this->sendResponse();
    }
}

