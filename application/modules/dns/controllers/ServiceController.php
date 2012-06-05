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
 * @package     mod_dns
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Dns_ServiceController extends Zivios_Controller
{
    protected function _init()
    {}

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
        $serviceEntry           = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service    = $serviceEntry;
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
        
        $serviceEntry        = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->render('dashboard/toolbar/tb01');
    }

    public function servicecontrolAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        $serviceEntry           = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service    = $serviceEntry;
        $this->view->mastercomp = $serviceEntry->mastercomp;
        $this->view->numZones   = sizeof($serviceEntry->getAllZones());

        $this->render('dashboard/servicecontrol');
    }

    public function addzonefileAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (isset($_POST['addfwzoneform'])) {
            $tzone = EMSDnsZone::TYPE_FORWARD;
            $form  = $_POST['addfwzoneform'];
            $srvdn = urldecode($_POST['addfwzoneform']['servicedn']);

            // Initialize service & retrieve the relevant form.
            $dnsService = Zivios_Ldap_Cache::loadDn($srvdn);
            $srvForm = $dnsService->getAddFwZoneForm();
        } else {
            $tzone = EMSDnsZone::TYPE_REVERSE;
            $form  = $_POST['addrvzoneform'];
            $srvdn = urldecode($_POST['addrvzoneform']['servicedn']);
            $dnsService = Zivios_Ldap_Cache::loadDn($srvdn);
            $srvForm = $dnsService->getAddRvZoneForm();
        }

        // Initialize DNS service
        $dnsService = Zivios_Ldap_Cache::loadDn($srvdn);
        
        // Initialize new zone & set zone type.
        $nzone = new EMSDnsZone();
        $nzone->init();
        $nzone->setType($tzone);
        $ignore = array('servicedn');
        $nzone->updateViaForm($srvForm, $form, $ignore);
        
        // Initialize transaction group
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New DNS Zone');
        $tgroup = $handler->newGroup('Adding DNS Zone Type: ' . $tzone, Zivios_Transaction_Group::EM_SEQUENTIAL);

        // Add zone add to transaction and process.
        $nzone->add($dnsService, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addCallback('zivios.cpaneRefresh', array('dsbleft'));
            $this->addCallback('zivios.cpaneRefresh', array('dsbbottom'));
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error adding new zone file. Please check system logs.');
        }
    }

    public function addzoneoptionsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }

        // Load service entry. 
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        
        // Retrieve forward & reverse add-zone forms.
        $fwf = $serviceEntry->getAddFwZoneForm();
        $rvf = $serviceEntry->getAddRvZoneForm();

        // Assign forms to view
        $this->view->fwzform = $fwf;
        $this->view->rvzform = $rvf;
        
        $this->render('dashboard/addazone');
    }
    
    /**
     * Service control action.
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
                if ($serviceEntry->startDns()) {
                    // @todo all control actions should go via transaction groups. 
                    $this->addNotify('DNS Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbleft'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start DNS Service. Please see system logs.');
                }
            break;

            case "stop" : 
                if ($serviceEntry->stopDns()) {
                    $this->addNotify('DNS Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbleft'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop DNS Service. Please see system logs.');
                }
            break;

            default: 
                throw new Zivios_Error('Unknown command option for service.');
        }
    }

    public function revzonedetailsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Request data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);

        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;

        $this->render('zonemanagement/revzonedetails');
    }
    
    public function revzoneoptionsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Request data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);

        // Generate dns add entry options form.
        $form         = new Zend_Dojo_Form();
        $strpCn       = strtolower(preg_replace("/[^a-z0-9]/i", "", $zoneEntry->getProperty('cn')));
        $formId       = 'addptr' . $strpCn;

        $form->setName($formId)
             ->setElementsBelongTo($formId)
             ->setMethod('post')
             ->setAction('#');

        // Add options form to master form.
        $optform   = $zoneEntry->getOptionsForm($zoneEntry->getProperty('emsdnszonetype'), $serviceEntry);
        $optformId = 'dnsaddptr' . $strpCn;
        $form->addSubForm($optform, $optformId);
       
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Add PTR Record',
            'onclick'     => "zivios.formXhrPost('".$formId."','/dns/service/entryhandler/?fid=".$formId."&rfid=".$optformId."'); return false;",
        ));

        $this->view->optForm = $form;
        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;
       
        $this->render('zonemanagement/revzoneoptions');
    }

    public function fwdzonedetailsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Request data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);

        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;

        $this->render('zonemanagement/fwdzonedetails');
    }

    public function fwdzoneoptionsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Request data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);

        // Generate dns add entry options form.
        $form   = new Zend_Dojo_Form();
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zoneEntry->getProperty('cn')));
        $formId = 'adddnsopt' . $strpCn;

        $form->setName($formId)
             ->setElementsBelongTo($formId . '-form')
             ->setMethod('post')
             ->setAction('#');

        // Add options form to master form.
        $optform = $zoneEntry->getOptionsForm($zoneEntry->getProperty('emsdnszonetype'));
        $form->addSubForm($optform,'dnsoptsform' . $strpCn);

        // Generate the onClick string.
        $cpId = $strpCn . '-dnszeopts'; // content pane ID.
        $widgetId = $formId . 'form-dnsoptsform' . $strpCn . '-dnslrt' . $strpCn;

        $onClickString = "zivios.cpaneRefresh('".$cpId."', '/dns/service/getrecordentryform/dn/" . 
                   urlencode($serviceEntry->getdn()) . "/zonedn/" . urlencode($zoneEntry->getdn()) . "/et/'" . 
                   " + dijit.byId('" . $widgetId . "').attr('value')); return false;";

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => "Display Add Options",
            'onclick'     => $onClickString,
        ));
        
        $this->view->optForm = $form;
        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;
       
        $this->render('zonemanagement/fwdzoneoptions');
    }

    public function deletezoneAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if ($this->_request->isPost()) {
            if (!array_key_exists('dn', $_POST) ||
                !array_key_exists('zonedn', $_POST)) {
                throw new Zivios_Error('Invalid call to action. Missing data in request.');
            } else {
                $dn     = strip_tags(urldecode($_POST['dn']));
                $zonedn = strip_tags(urldecode($_POST['zonedn']));
            }
            
            // Load Zone & service.
            $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
            $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);
            $zoneCn       = $zoneEntry->getProperty('cn');

            // Create transaction group for zone removal.
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Zone: ' . 
                $zoneEntry->getProperty('cn') . ' from Zivios DNS Service.');

            $tgroup = $handler->newGroup('Deleting Zone: ' . $zoneEntry->getProperty('cn') . 
                ' from Zivios DNS Service', Zivios_Transaction_Group::EM_SEQUENTIAL);
            
            // Call wrapper method for delete as recursion may be required.
            $zoneEntry->removeZone($tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                // Zone removed; send response to close existing zone tab, select the
                // dashboard tab and refresh zone listing view (just-in-case it displays
                // the zone that has been removed in its listing section).
                $callback  = 'zivios.closeTab';
                $arguments = array(
                    'dnssrvtabs01',
                    'ze_' . $zoneCn
                );
                $this->addCallback($callback, $arguments);
                
                // Ensure we switch to the dns dashboard tab.
                $callback  = 'zivios.selectTab';
                $arguments = array(
                    'dnssrvtabs01',
                    'dnsdashboardtab'
                );
                $this->addCallback($callback, $arguments);

                // Refresh the zone listing and zone count.
                $this->addCallback('zivios.cpaneRefresh', array('dsbbottom'));
                $this->addCallback('zivios.cpaneRefresh', array('dsbleft'));

                // Send response.
                $this->sendResponse();
                return;
            } else {
                throw new Zivios_Error('Error deleting Zone. Please check system logs.');
            }
        }
        
        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Request data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);
        
        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;
        $this->render('zonemanagement/deletezone');
    }

    public function deleterecordentryAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if ($this->_request->isPost()) {
            if (!array_key_exists('dn', $_POST) ||
                !array_key_exists('zonedn', $_POST) ||
                !array_key_exists('recorddn', $_POST)) {
                throw new Zivios_Error('Invalid call to action. Missing data in request.');
            }
            
            // Decode required information.
            $recordDn  = strip_tags(trim(urldecode($_POST['recorddn'])));
            $zoneDn    = strip_tags(trim(urldecode($_POST['zonedn'])));
            $serviceDn = strip_tags(trim(urldecode($_POST['dn'])));
            
            // Load entries.
            $recordEntry  = Zivios_Ldap_Cache::loadDn($recordDn);
            $zoneEntry    = Zivios_Ldap_Cache::loadDn($zoneDn);
            $serviceEntry = Zivios_Ldap_Cache::loadDn($serviceDn);

            // Initialize transaction group
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Record from zone: ' . 
                $zoneEntry->getProperty('cn'));

            $tgroup = $handler->newGroup('Deleting record from zone: ' . $zoneEntry->getProperty('cn'), 
                Zivios_Transaction_Group::EM_SEQUENTIAL);
            
            $recordEntry->deleteRecord($tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zoneEntry->getProperty('cn')));
                $cpIdb  = $strpCn . '-dnszeopts';
                $cpIda  = $zoneEntry->getProperty('cn') . '_rp';
                $this->addCallback('zivios.cpaneRefresh', array($cpIda));
                $this->addCallback('zivios.cpaneRefresh', array($cpIdb, '/dns/service/getrecordentryform/dn/'.
                    urlencode($serviceEntry->getdn())));
                $this->sendResponse();
                return;
            } else {
                throw new Zivios_Error('Error deleting zone record. Please check system logs.');
            }
        }

        if (null === $this->_request->getParam('recorddn') &&
            null === $this->_request->getParam('zonedn') &&
            null === $this->_request->getParam('dn')) {
            // invalid call.
            return;
        }

        // Load form by record type & populate accordingly.
        $recordDn  = strip_tags(trim(urldecode($this->_request->getParam('recorddn'))));
        $zoneDn    = strip_tags(trim(urldecode($this->_request->getParam('zonedn'))));
        $serviceDn = strip_tags(trim(urldecode($this->_request->getParam('dn'))));

        // Load all entries.
        $recordEntry  = Zivios_Ldap_Cache::loadDn($recordDn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zoneDn);
        $serviceEntry = Zivios_Ldap_Cache::loadDn($serviceDn);
            
        // Establish entry type.
        $entryType = $recordEntry->getProperty('dlztype');
        
        $this->view->recordType = $entryType;
        $this->view->zone       = $zoneEntry;
        $this->view->service    = $serviceEntry;
        $this->view->record     = $recordEntry;

        $this->render('zonemanagement/deleterecord');
    }

    public function getrecordentryformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        // Look for service & zonedn in get request, validate, and load entry data
        // for form population.
        if (null === $this->_request->getParam('zonedn') ||
            null === $this->_request->getParam('dn')) {
            // Initial load request, return empty string.
            return '';
        } elseif (null !== $this->_request->getParam('recorddn') &&
                  null !== $this->_request->getParam('zonedn') &&
                  null !== $this->_request->getParam('dn')) {


            // Load form by record type & populate accordingly.
            $recordDn  = strip_tags(trim(urldecode($this->_request->getParam('recorddn'))));
            $zoneDn    = strip_tags(trim(urldecode($this->_request->getParam('zonedn'))));
            $serviceDn = strip_tags(trim(urldecode($this->_request->getParam('dn'))));

            // Load all entries.
            $recordEntry  = Zivios_Ldap_Cache::loadDn($recordDn);
            $zoneEntry    = Zivios_Ldap_Cache::loadDn($zoneDn);
            $serviceEntry = Zivios_Ldap_Cache::loadDn($serviceDn);
            
            // Establish entry type.
            $entryType = $recordEntry->getProperty('dlztype');

            // We want to further ensure that the view object is aware that
            // an existing record is being edited. This enables us to further
            // provision for options at a later stage based on the record type.
            $this->view->newRecord  = false;
            $this->view->recordType = $entryType;
            $formSubmitText         = 'Update Record';

        } elseif (null !== $this->_request->getParam('et') &&
                  null !== $this->_request->getParam('dn') &&
                  null !== $this->_request->getParam('zonedn')) {

            // New record entry.
            $entryType = strip_tags(trim($this->_request->getParam('et')));
            $zoneDn    = strip_tags(trim($this->_request->getParam('zonedn')));
            $serviceDn = strip_tags(trim($this->_request->getParam('dn')));

            // Load all entries.
            $zoneEntry    = Zivios_Ldap_Cache::loadDn($zoneDn);
            $serviceEntry = Zivios_Ldap_Cache::loadDn($serviceDn);
            $recordEntry  = new EMSDnsRecord();

            // Ensure the view object is aware that this is a new record
            $this->view->newRecord  = true;            
            $this->view->recordType = $entryType;
            $formSubmitText         = 'Add Record';            
        }

        // Load form by type.
        $form = $recordEntry->getEntryForm($serviceEntry, $entryType, $zoneEntry);
        $fid  = $form->getAttrib('name');
        
        // Add submit button to form.
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => $formSubmitText,
            'onclick'     => "zivios.formXhrPost('".$fid."','/dns/service/entryhandler/?fid=".$fid."'); return false;",
        ));        

        $this->view->form = $form;
        $this->render('zonemanagement/recordentryopts');
    }

    /**
     * Works with form post requests to handle add and edit
     * of record entries.
     * 
     * @todo: break method down with helpers in model class.
     * @return JSON
     */
    public function entryhandlerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        // Ensure the received request is valid and post & get arrays
        // get be linked for data lookup.
        if (null === $this->_request->getParam('fid')) {
            throw new Zivios_Error('Missing data received in request.');
        } else {
            $fid = strip_tags(trim($this->_request->getParam('fid')));
        }

        if (!array_key_exists($fid, $_POST)) {
            throw new Zivios_Error('Invalid data received in request.');
        }
        
        if (null === $this->_request->getParam('rfid')) {
            // If rfid is not defined, form data is not nested further.
            $fdata = $_POST[$fid];
        } else {
            // Retrieve nested form data.
            $rfid = strip_tags(trim($this->_request->getParam('rfid')));
            $fdata = $_POST[$fid][$rfid];
        }

        // Load service & zone dns.
        $zonedn       = urldecode(strtolower(strip_tags(trim($fdata['zonedn']))));
        $servicedn    = urldecode(strtolower(strip_tags(trim($fdata['servicedn']))));

        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);
        $serviceEntry = Zivios_Ldap_Cache::loadDn($servicedn);
        
        // Establish record entry type & status.
        if (array_key_exists('nentry', $fdata) && $fdata['nentry'] == 1) {
            // New entry record; if no hostname exists, we assume the 
            // master record is being added to.

            switch (strip_tags(trim($fdata['et']))) {
                case EMSDnsRecord::PTR_REC:
                    // Adding a PTR record.
                    $ptrRecord = new EMSDnsPtrRecord();
                    $ptrRecord->init();

                    $form = $ptrRecord->rvZoneOpts($serviceEntry, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn','dlzdata');
                    
                    // Automatic server side validation runs here.
                    $ptrRecord->updateViaForm($form, $fdata, $ignoreFields);

                    // Set hostname data in ptr record.
                    if (substr($fdata['dlzdata'], -1) != '.') {
                        $fdata['dlzdata'] .= '.';
                    }
                    $ptrRecord->setHostname($fdata['dlzdata']);
                   
                    // Initialize transaction group
                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new ptr record to zone: ' . 
                        $zoneEntry->getProperty('cn'));

                    $tgroup = $handler->newGroup('Adding new ptr record to zone: ' . $zoneEntry->getProperty('cn'), 
                        Zivios_Transaction_Group::EM_SEQUENTIAL);

                    $ptrRecord->add($zoneEntry, $tgroup);
                    $tgroup->commit();
                    
                    // Process transaction
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::A_REC:
                    // A record.
                    $nRecord = new EMSDnsRecord();
                    $form = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::A_REC, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn');
                    
                    // Automatic server side validation runs here.
                    $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                    // Initialize transaction group
                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new records to zone: ' . 
                        $zoneEntry->getProperty('cn'));

                    $tgroup = $handler->newGroup('Adding new records to zone: ' . $zoneEntry->getProperty('cn'), 
                        Zivios_Transaction_Group::EM_SEQUENTIAL);

                    if (strip_tags(trim($fdata['dlzhostname'])) == '') {
                        // Entry is for master record.
                        // A record entry for master zone.
                        // Validate the form.
                        $zoneRec = $zoneEntry->getMasterZoneHN();
                    } else {
                        // New hostname entry required; we create a dnshostname 
                        // and assign values as required.
                        $zoneRec = new EMSDnsHostName();
                        $zoneRec->init();

                        // Note: the hostname has been verified via form-update, we can
                        // hence update the record directly here and add it to the transaction.
                        $zoneRec->setProperty('dlzhostname', $fdata['dlzhostname']);
                        // Add host name
                        $zoneRec->add($zoneEntry, $tgroup);            
                    }

                    // Add A record for host name
                    $nRecord->add($zoneRec, $tgroup);
                    $tgroup->commit();
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::MX_REC:
                    // MX record entry
                    /**
                     * Currently adding MX entries for added hostnames is not possible, however,
                     * this is something we will add for the coming releases. It requires a bit
                     * of refactoring in the display and display options as presented to the end
                     * user. MX entries are hence set for the primary domain name only.
                     */
                    $nRecord = new EMSDnsRecord();
                    $form = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::MX_REC, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn');

                    // Before we send user data for server side validation, we ensure that a trailing
                    // dot is appended to dlzdata.
                    if (substr($fdata['dlzdata'], -1) != '.') {
                        $fdata['dlzdata'] .= '.';
                    }

                    // Automatic server side validation runs here.
                    $nRecord->updateViaForm($form, $fdata, $ignoreFields);                
                    $zoneRec = $zoneEntry->getMasterZoneHN();

                    // Initialize transaction group.
                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new records to zone: ' . 
                        $zoneEntry->getProperty('cn'));

                    $tgroup = $handler->newGroup('Adding new records to zone: ' . $zoneEntry->getProperty('cn'), 
                        Zivios_Transaction_Group::EM_SEQUENTIAL);
                
                    $nRecord->add($zoneRec, $tgroup);
                    $tgroup->commit();
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::CNAME_REC:
                    // CNAME record entry for master zone.
                    $nRecord = new EMSDnsRecord();
                    $form = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::CNAME_REC, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn');
                    
                    // Before we send user data for server side validation, we ensure that a trailing
                    // dot is appended to dlzdata.
                    if (substr($fdata['dlzdata'], -1) != '.') {
                        $fdata['dlzdata'] .= '.';
                    }

                    // Automatic server side validation runs here.
                    $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new records to zone: ' . 
                        $zoneEntry->getProperty('cn'));

                    $tgroup = $handler->newGroup('Adding new records to zone: ' . $zoneEntry->getProperty('cn'), 
                        Zivios_Transaction_Group::EM_SEQUENTIAL);

                    // Add the hostname entry for CNAME record.
                    $zoneRec = new EMSDnsHostName();
                    $zoneRec->init();
                    $zoneRec->setProperty('dlzhostname', $fdata['dlzhostname']);
                    $zoneRec->add($zoneEntry, $tgroup);

                    // Add record to CNAME host entry. 
                    $nRecord->add($zoneRec, $tgroup);
                
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::TXT_REC:
                    $nRecord = new EMSDnsRecord();
                    $form    = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::TXT_REC, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn');

                    $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new records to zone: ' . 
                        $zoneEntry->getProperty('cn'));
                    
                    $tgroup = $handler->newGroup('Adding new records to zone: ' . $zoneEntry->getProperty('cn'),
                        Zivios_Transaction_Group::EM_SEQUENTIAL);
                    
                    // Check if a name was provided, or create entry against master hostname.
                    if ($nRecord->getProperty('dlzhostname') != '') {
                        $zoneRec = new EMSDnsHostName();
                        $zoneRec->init();
                        $zoneRec->setProperty('dlzhostname', $fdata['dlzhostname']);
                        $zoneRec->add($zoneEntry, $tgroup);
                    } else {
                        $zoneRec = $zoneEntry->getMasterZoneHN(); 
                    }

                    // Add txt record to relevant hostname.
                    $nRecord->add($zoneRec, $tgroup);
                    $tgroup->commit();
                    // process transaction
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                case EMSDNSRecord::SRV_REC:
                    $nRecord = new EMSDnsRecord();
                    $form    = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::SRV_REC, $zoneEntry);
                    $ignoreFields = array('nentry','et','addnew','zonedn','servicedn','servicename',
                        'protocol','target','priority','weight','port');

                    $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                    // Calculate dlzdata and call setProperty
                    if (!isset($fdata['protocol']) || !isset($fdata['target']) || !isset($fdata['weight']) ||
                        !isset($fdata['port']) || !isset($fdata['priority'])) {
                        throw new Zivios_Error('Required data missing from request.');
                    } else {
                        // Ensure target has a trailing dot.
                        if (substr($fdata['target'], -1) != '.') {
                            $fdata['target'] .= '.';
                        }
                    }
                    
                    // generate & set dlzdata
                    $dlzdata = $fdata['priority'] . ' ' . $fdata['weight'] . ' ' . $fdata['port'] . ' ' . 
                        $fdata['target'];
                    $nRecord->setProperty('dlzdata', $dlzdata);

                    $handler = Zivios_Transaction_Handler::getNewHandler('Adding new record to zone: ' .
                        $zoneEntry->getProperty('cn'));

                    $tgroup = $handler->newGroup('Adding new record to zone: ' . $zoneEntry->getProperty('cn'),
                        Zivios_Transaction_Group::EM_SEQUENTIAL);
                    
                    // Some basic error checking before a hostname is initialized.
                    if ((!isset($fdata['servicename']) || $fdata['servicename'] == '') || 
                        !isset($fdata['protocol']) || $fdata['protocol'] == '') {
                        throw new Zivios_Error('Service Name and/or protocol data missing from request.');
                    }

                    $hostname = $fdata['servicename'] . '.' . $fdata['protocol'];
                    $zoneRec  = new EMSDnsHostName();
                    $zoneRec->init();
                    $zoneRec->setProperty('dlzhostname', $hostname);

                    // Adding hostname to zone
                    $zoneRec->add($zoneEntry, $tgroup);
                    
                    // Adding SRV record to host
                    $nRecord->add($zoneRec, $tgroup);

                    $tgroup->commit();
                    // process transaction
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                    }
                    break;

                default:
                    throw new Zivios_Error('Invalid / missing data received.');
            }
        } else {
            // Edit of existing entry.
            $recorddn    = urldecode(strtolower(strip_tags(trim($fdata['recorddn']))));
            $recordEntry = Zivios_Ldap_Cache::loadDn($recorddn);
            
            // Initialize transaction group
            $handler = Zivios_Transaction_Handler::getNewHandler('Updating record in zone: ' . 
                $zoneEntry->getProperty('cn'));

            $tgroup = $handler->newGroup('Updating record in zone: ' . $zoneEntry->getProperty('cn'), 
                Zivios_Transaction_Group::EM_SEQUENTIAL);

            switch ($fdata['et']) {
                case EMSDnsRecord::A_REC:
                    if ($fdata['addnew'] == 1) {
                        // Add IP as new record entry.
                        $nRecord = new EMSDnsRecord();
                        $parent  = $recordEntry->getParent();

                        $form    = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::A_REC, $zoneEntry);
                        $ignoreFields = array('nentry','et','addnew','zonedn','servicedn','recorddn');
                        $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                        // Add record as new to parent host
                        $nRecord->add($parent, $tgroup);

                        // Process transaction.
                        $status = $this->processTransaction($handler);

                        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                            $cpId = $zoneEntry->getProperty('cn') . '_rp';
                            $this->addCallback('zivios.cpaneRefresh', array($cpId));
                            $this->sendResponse();
                        } else {
                            throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                        }
                    } else {
                        // Update properties on existing entry.
                        $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::A_REC, $zoneEntry);
                        $ignoreFields = array('nentry', 'et', 'addnew', 'zonedn', 'servicedn', 'recorddn');
                        $recordEntry->updateViaForm($form, $fdata, $ignoreFields);

                        // update form.
                        $recordEntry->update($tgroup);
                        $tgroup->commit();
                        // Process transaction.
                        $status = $this->processTransaction($handler);

                        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                            $cpId = $zoneEntry->getProperty('cn') . '_rp';
                            $this->addCallback('zivios.cpaneRefresh', array($cpId));
                            $this->sendResponse();
                        } else {
                            throw new Zivios_Error('Error updating record entry. Please check system logs.');
                        }
                    }
                    break;

                case EMSDnsRecord::MX_REC:
                    $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::MX_REC, $zoneEntry);
                    $ignoreFields = array('nentry', 'et', 'zonedn', 'servicedn', 'recorddn');
                
                    // We ensure the trailing '.' at the end of dlzdata.
                    if (substr($fdata['dlzdata'], -1) != '.') {
                        $fdata['dlzdata'] .= '.';
                    }

                    // Server side validate of form & object update.
                    $recordEntry->updateViaForm($form, $fdata, $ignoreFields);
                    $recordEntry->update($tgroup);
                    
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error updating record entry. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::CNAME_REC:
                    // Update properties on existing entry.
                    $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::CNAME_REC, $zoneEntry);
                    $ignoreFields = array('dlzhostname', 'nentry', 'et', 'zonedn', 'servicedn', 'recorddn');
                    $recordEntry->updateViaForm($form, $fdata, $ignoreFields);

                    // update form.
                    $recordEntry->update($tgroup);
                    
                    $tgroup->commit();
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error updating record entry. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::SOA_REC:
                    $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::SOA_REC, $zoneEntry);
                    $ignoreFields = array('et', 'zonedn', 'servicedn', 'recorddn');
                
                    // Server side validate of form & object update.
                    $recordEntry->updateViaForm($form, $fdata, $ignoreFields);
                    $recordEntry->update($tgroup);
                    $tgroup->commit();
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error updating record entry. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::TXT_REC:
                    // Update properties on existing entry.
                    $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::TXT_REC, $zoneEntry);
                    $ignoreFields = array('dlzhostname', 'nentry', 'et', 'zonedn', 'servicedn', 'recorddn');
                    $recordEntry->updateViaForm($form, $fdata, $ignoreFields);

                    // update form.
                    $recordEntry->update($tgroup);
                    $tgroup->commit();
                    // Process transaction.
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $cpId = $zoneEntry->getProperty('cn') . '_rp';
                        $this->addCallback('zivios.cpaneRefresh', array($cpId));
                        $this->sendResponse();
                    } else {
                        throw new Zivios_Error('Error updating record entry. Please check system logs.');
                    }
                    break;

                case EMSDnsRecord::SRV_REC:
                    if ($fdata['addnew'] == '1') {
                        // Append record to existing host entry.
                        $nRecord = new EMSDnsRecord();
                        $parent  = $recordEntry->getParent();

                        $form    = $nRecord->getEntryForm($serviceEntry, EMSDnsRecord::SRV_REC, $zoneEntry);
                        $ignoreFields = array('nentry','et','addnew','zonedn','servicedn','servicename', 'recorddn',
                                              'protocol','target','priority','weight','port');
                        
                        // Before update, we save & remove the 'servicename' field.
                        $element = $form->getElement('servicename');
                        $form->removeElement('servicename');
                        $nRecord->updateViaForm($form, $fdata, $ignoreFields);

                        // Add the servicename element again.
                        $form->addElement($element);

                        // Calculate dlzdata and call setProperty
                        if (!isset($fdata['protocol']) || !isset($fdata['target']) || !isset($fdata['weight']) ||
                            !isset($fdata['port']) || !isset($fdata['priority'])) {
                            throw new Zivios_Error('Required data missing from request.');
                        } else {
                            // Ensure target has a trailing dot.
                            if (substr($fdata['target'], -1) != '.') {
                                $fdata['target'] .= '.';
                            }
                        }
                        
                        // dlzdata is calculated and set manually.
                        $dlzdata = $fdata['priority'] . ' ' . $fdata['weight'] . ' ' . $fdata['port'] . ' ' . 
                            $fdata['target'];
                        $nRecord->setProperty('dlzdata', $dlzdata);

                        // Add record as new to parent host
                        $nRecord->add($parent, $tgroup);
                        $tgroup->commit();
                        // Process transaction.
                        $status = $this->processTransaction($handler);

                        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                            $cpId = $zoneEntry->getProperty('cn') . '_rp';
                            $this->addCallback('zivios.cpaneRefresh', array($cpId));
                            $this->sendResponse();
                        } else {
                            throw new Zivios_Error('Error adding new zone record. Please check system logs.');
                        }
                    } else {
                        $form         = $recordEntry->getEntryForm($serviceEntry, EMSDnsRecord::SRV_REC, $zoneEntry);
                        $ignoreFields = array('nentry','et','addnew','zonedn','servicedn','servicename', 'recorddn',
                                              'protocol','target','priority','weight','port');

                        $recordEntry->updateViaForm($form, $fdata, $ignoreFields);

                        // Calculate dlzdata and call setProperty
                        if (!isset($fdata['protocol']) || !isset($fdata['target']) || !isset($fdata['weight']) ||
                            !isset($fdata['port']) || !isset($fdata['priority'])) {
                            throw new Zivios_Error('Required data missing from request.');
                        } else {
                            // Ensure target has a trailing dot.
                            if (substr($fdata['target'], -1) != '.') {
                                $fdata['target'] .= '.';
                            }
                        }
                        
                        // dlzdata is calculated and set manually.
                        $dlzdata = $fdata['priority'] . ' ' . $fdata['weight'] . ' ' . $fdata['port'] . ' ' . 
                            $fdata['target'];
                        $recordEntry->setProperty('dlzdata', $dlzdata);
                        
                        // update form.
                        $recordEntry->update($tgroup);
                        $tgroup->commit();
                        // Process transaction.
                        $status = $this->processTransaction($handler);

                        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                            $cpId = $zoneEntry->getProperty('cn') . '_rp';
                            $this->addCallback('zivios.cpaneRefresh', array($cpId));
                            $this->sendResponse();
                        } else {
                            throw new Zivios_Error('Error updating record entry. Please check system logs.');
                        }

                    }
                    break;

                default: 
                    throw new Zivios_Error('Unknown record entry type. Could not process request.');
                    break;
            }
        }
    }

    /**
     * Function initializes the DNS service and does a lookup via submitted
     * user query for zone names. Queries are performed on the 'cn' attribue
     * of the dns zone in question.
     *
     * @return JSON $data
     */
    public function searchzonesAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Invalid call. Service DN missing.');
            echo Zend_Json::encode(array());
            return;
        } else {
            $dn = urldecode($dn);
        }

        if (null === ($query = $this->_request->getParam('q'))) {
            echo Zend_Json::encode(array());
            return;
        } else {
            if ($query == '*') {
                echo Zend_Json::encode(array());
                return;
            }

            // proceed with lookup if at least 3 character
            // has come in the query.
            $query = trim(strip_tags($query));
            if (strlen($query) < 3) {
                echo Zend_Json::encode(array());
                return;
            }
        }

        // search for zone based on query.
        $serviceEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $filter     = '(&(objectclass=emsdnszone)(cn='.$query.'))';
        $zones      = $serviceEntry->getImmediateChildren($filter);

        $zoneData  = array();
        if (is_array($zones) && !empty($zones)) {
            foreach ($zones as $zoneEntry) {
                $zoneData[urlencode($zoneEntry->getdn())] = $zoneEntry->getProperty('cn');
            }
        } else {
            echo Zend_Json::encode(array());
            return;
        }

        $this->_helper->autoCompleteDojo($zoneData);
    }

    public function searchzoneoptionsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }

        // Load service entry. 
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

        // Get zone search form
        $szform = new Zend_Dojo_Form();
        $szform->setName('searchzonesform')
                     ->setElementsBelongTo('searchzones-form')
                     ->setMethod('post')
                     ->setAction('#');

        $szsubform = $serviceEntry->getZoneSearchForm();
        $szform->addSubForm($szsubform, "searchavailablezonesform");
        $szform->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Manage Zone',
            'onclick'     => "zivios.formXhrPost('searchzonesform','/dns/service/genzonetab'); return false;",
        ));

        // Assign form to view.
        $this->view->szform  = $szform;

        // Lookup 10 random zones for listing.
        $options = array('limit' => 5);
        $zoneListing  = $serviceEntry->getZoneListing($options);
        
        // Assign zone listing to view.
        $this->view->zoneListing = $zoneListing;
        $this->view->service     = $serviceEntry;
        $this->render('dashboard/searchzones');
    }

    /**
     * Generate a zone management tab by force. Required callback is sent with
     * zone details generated via the search zone form post. 
     *
     * @return JSON
     */
    public function genzonetabAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!$this->getRequest()->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }

        if (null === ($form = $_POST['searchzonesform']['searchavailablezonesform'])) {
            throw new Zivios_Error('Required data not present in request.');
        }

        // ensure the zone DN is specified.
        if (isset($form['zonesearch']) && $form['zonesearch'] != '') {
            $zonedn = strip_tags(urldecode($form['zonesearch']));
        } else {
            throw new Zivios_Error('Required data not present in request');
        }

        if (isset($form['servicedn']) && $form['servicedn'] != '') {
            // We do not decode the encoded dn string as it gets appended to the uri.
            $servicedn = $form['servicedn'];
        } else {
            throw new Zivios_Error('Required data not present in request');
        }
        
        
        // Load the zone data and generate the required JS callback.
        $zone      = Zivios_Ldap_Cache::loadDn($zonedn);
        $callback  = 'zivios.loadApp';
        $arguments = array(
                         '/dns/service/managezone/dn/'.$servicedn.'/zonedn/'.urlencode($zone->getdn()),
                         'dnssrvtabs01', // hard-coded parent tab container id.
                         'ze_' . $zone->getProperty('cn'), // dynamic tab container id (based on cn)
                         $zone->getProperty('cn'),
                     );

        $this->addCallback($callback, $arguments);
        $this->sendResponse();
    }
    
    public function managezoneAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        // Ensure the service and zone DNs have been supplied in the request.
        if ((null === ($dn     = $this->_request->getParam('dn'))) ||
             null === ($zonedn = $this->_request->getParam('zonedn'))) {
            throw new Zivios_Error('Required data missing in request.');
        } else {
            $dn     = strip_tags(urldecode($dn));
            $zonedn = strip_tags(urldecode($zonedn));
        }
        
        // Load service & zone data objects.
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $zoneEntry    = Zivios_Ldap_Cache::loadDn($zonedn);

        switch ($zoneEntry->getProperty('emsdnszonetype')) {
            case EMSDnsZone::TYPE_FORWARD : 
                $template = 'zonemanagement/forward';
                break;

            case EMSDnsZone::TYPE_REVERSE : 
                $template = 'zonemanagement/reverse';
                break;

            default:
                throw new Zivios_Error('Zone record invalid. Missing critical data (zone type undefined).');
        }

        // Assign objects to view.
        $this->view->service = $serviceEntry;
        $this->view->zone    = $zoneEntry;
        $this->render($template);
    }
    
    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry        = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
    }

    public function cfginfoAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $this->render('config/info');
    }

    public function cfgforwardersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $this->render('config/forwarders');
    }
}

