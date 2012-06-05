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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Asterisk_ServiceController extends Zivios_Controller_Service
{
	/**
	 * The Asterisk Module ServiceController Class.
	 */
	protected $_pluginConfig, $_dirConnection;

	protected function _init()
	{

			Zivios_Log::debug("Calling Asterisk Controller Init");
			/**
			 * Instantiate the plugin object and attach to view
			 */
			parent::_init();
			$this->_initServiceConfig();
			$this->view->displayname = $this->_serviceConfig->general->displayname;
	}


    public function addserviceAction()
	{
		$this->_helper->layout->disableLayout(true);
        if (!$_POST['addserviceform']['doinstall']) {

            if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('Specified entry not found in system.');
            }

            $serviceParent = Zivios_Ldap_Cache::loadDn($dn);
            //get compatible computers
            $computers = $this->_getCompatiableComputers($serviceParent);

            if (!is_array($computers)) {
                Zivios_Log::debug('No comaptible computers found.');
                $this->view->container = $serviceParent->getProperty('cn');
                $this->view->nocompat = "No Compatible Opereating Systems were found for the " .
                                        $this->_serviceConfig->general->displayname . " service.";
                $this->view->computers =  explode(",", $computers);

            } else {
                $compArray ='';
                foreach ($computers as $computer) {
                    $compArray[$computer->getdn()] = $computer->getProperty('cn');
                }

                $serviceEntry = new AsteriskService();
                $serviceEntry->init();
                $serviceform = $serviceEntry->getMainForm($compArray);
                $form = new Zend_Dojo_Form();
                $form->setName('addserviceform')
                     ->setElementsBelongTo('addserviceform')
                     ->setMethod('post')
                     ->setAction('#');

                $form->addSubForm($serviceform,'mainform');

                $hfdn = new Zend_Form_Element_Hidden('dn');
                $hfdn->setValue($dn)
                     ->removeDecorator('label')
                     ->removeDecorator('HtmlTag');
                $form->addElement($hfdn);

                $hfdoinstall = new Zend_Form_Element_Hidden('doinstall');
                $hfdoinstall->setValue(1)
                            ->removeDecorator('label')
                            ->removeDecorator('HtmlTag');
                $form->addElement($hfdoinstall);

                $form->addElement('submitButton', 'submit', array(
                    'required'    => false,
                    'ignore'      => true,
                    'label'        => 'Install Service',
                    'onclick'     => "zivios.formXhrPost('addserviceform','asterisk/service/addservice'); return false;",
                ));

                $this->view->container = $serviceParent->getProperty('cn');
                $this->view->form = $form;
            }
        } else {

            $this->_helper->viewRenderer->setNoRender();
            $mainform = $_POST['addserviceform']['mainform'];

            if (!$this->getRequest()->isPost()) {
                throw new Zivios_Error('Invalid call received by controller.');
            }

            if (null === ($dn = $_POST['addserviceform']['dn'])) {
                throw new Zivios_Error('Required data not present in request');
            }

            if ('-1' == ($comp = $mainform['emsmastercomputerdn'])) {
                throw new Zivios_Error('Please select a valid server.');
            }

            $comp = Zivios_Ldap_Cache::loadDn($comp);
            $comp = $comp->getObject();

            /**
             * Initialize communication with asterisk module on server.
             */
             
             /**
            $commAgent = new Zivios_Comm_Agent($comp->getProperty("cn"), "asterisk");

            if (!$commAgent->getstatus()) {
                    throw new Zivios_Error("Asterisk Service does not appear to be running. " .
                                           "Please start the service and try again");
                    return;
            }
            */
            
            $serviceParent = Zivios_Ldap_Cache::loadDn($dn);
            //Get compatible computers, setMainForm requires in order to validate form.
            $computers = $this->_getCompatiableComputers($serviceParent);
            $compArray ='';
            foreach ($computers as $computer) {
                $compArray[$computer->getdn()] = $computer->getProperty('cn');
            }

            $serviceEntry = new AsteriskService();
            $serviceEntry->init();
            $serviceEntry->setMainForm($mainform, $compArray);
            $serviceEntry->setProperty('emsmastercomputerdn',$mainform['emsmastercomputerdn']);
            $serviceEntry->setProperty('cn',$this->_serviceConfig->general->displayname);
            $serviceEntry->setProperty('emsdescription','Zivios Asterisk Service');

            $handler = Zivios_Transaction_Handler::getNewHandler('Adding Asterisk Service');
            $tgroup = $handler->newGroup('Adding Asterisk Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $serviceEntry->add($serviceParent,$tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);
            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($serviceParent->getdn());
                $this->addNotify('Asterisk Service added successfully.');
            } else {
                throw new Zivios_Error('Error adding Asterisk Service. Please check system logs.');
            }
            $this->sendResponse();
        }
    }

    public function dashboardAction()
	{
		$this->_helper->layout->disableLayout(true);
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        $this->view->dn = $dn;
	}

    public function loaddashboardAction()
    {
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $this->_helper->layout->disableLayout(true);
		$serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

		$this->view->dashboardData = $serviceEntry->loadDashboardData();
		$mastercompdn = $serviceEntry->getProperty('emsmastercomputerdn');
		$mastercompEntry = Zivios_Ldap_Cache::loadDn($mastercompdn);
		$this->view->masterComputer = $mastercompEntry->getProperty('cn');
		$this->view->dn = $serviceEntry->getdn();

        $this->render('dashboard/main');
    }


	public function stopserviceAction()
	{
		$this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

		if ($serviceEntry->stopService()) {
			$this->addNotify("Asterisk Service Stopped");
		} else {
			throw new Zivios_Error("Asterisk Service could not be stopped. Please check logs");
		}

        $this->addCallback('zivios.cpaneRefresh', array('asteriskservicedashboardlayout'));
        $this->sendResponse();
	}
	
	public function callmonitorAction()
	{
	    $this->_helper->layout->disableLayout(true);
	    Zivios_Log::debug("Here");
	     if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        
        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->view->chanstatus = $serviceEntry->channelStatus();
	}
	
	public function docallmonitorAction()
	{
	    $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $srvdn = urldecode($this->getParam('srvdn'));
        $channel = urldecode($this->getParam('channel'));
        $command = $this->getParam('command');
        
        if ($command == 'hangup') {
            $srv = Zivios_Ldap_Cache::loadDn($srvdn);
            $srv->Hangup($channel);
            Zivios_Log::debug('Here');
            $this->addNotify('Channel '.$channel.' successfully hungup');
            
        }
        Zivios_Log::debug("Hello");
        $this->refreshPane('astmonitor','/asterisk/service/callmonitor/dn/'.$srvdn);
        $this->sendResponse();
        
	}

	public function startserviceAction()
	{
		$this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);

		if ($serviceEntry->startService()) {
			$this->addNotify("Asterisk Service Started");
		} else {
			throw new Zivios_Error("Asterisk Service could not be started. Please check logs");
		}

        $this->addCallback('zivios.cpaneRefresh', array('asteriskservicedashboardlayout'));
        $this->sendResponse();
	}

}
