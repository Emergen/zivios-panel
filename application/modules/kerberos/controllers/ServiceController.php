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
 * @package     mod_kerberos
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Kerberos_ServiceController extends Zivios_Controller_Service
{

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
            $dn = urldecode($dn);
        }
        
        $serviceEntry           = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service    = $serviceEntry;
        $this->view->mastercomp = $serviceEntry->mastercomp;

        $this->render('dashboard/servicecontrol');
    }

    public function servicectrlAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $_POST['dn']) || null === ($action = $_POST['action'])) {
            throw new Zivios_Error('Invalid call received by controller.');
        } else {
            $dn = strip_tags(urldecode($dn));
            $action = strip_tags($_POST['action']);
        }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        if ($serviceEntry->getProperty('emsmodulename') != 'kerberos') {
            throw new Zivios_Error('Invalid call received by controller.');
        }
        
        switch ($action) {
            case 'startkdc':
                if ($serviceEntry->startKdc()) {
                    $this->addNotify('KDC Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start KDC service. Please check system logs.');
                }
                break;
                
            case 'startkadmind':
                /*
                if ($serviceEntry->startKadmind()) {
                    $this->addNotify('Kadmin Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start Kadmin service. Please check system logs.');
                }
                */
                throw new Zivios_Error('Kadmind cannot be started from Zivios.');
                break;

            case 'startkpasswdd':
                if ($serviceEntry->startKpasswdd()) {
                    $this->addNotify('Kpassword Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start KDC service. Please check system logs.');
                }
                break;

            case 'stopkdc': 
                if ($serviceEntry->stopKdc()) {
                    $this->addNotify('KDC Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop KDC service. Please check system logs.');
                }
                break;

            case 'stopkadmind': 
                if ($serviceEntry->stopKadmind()) {
                    $this->addNotify('Kadmin Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop Kadmin service. Please check system logs.');
                }
                break;

            case 'stopkpasswdd':
                if ($serviceEntry->stopKpasswdd()) {
                    $this->addNotify('Kpassword Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('dsbcenter'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop Kpassword service. Please check system logs.');
                }
                break;

            default: 
                throw new Zivios_Error('Undefined call to service.');
        }
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

    public function passwdconfigAction()
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
    
    public function dopasswdconfigAction()
    {
    	$this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->processForm('krb');
        Zivios_Log::debug($form);
        $dn = $form['dn'];
        $service = Zivios_Ldap_Cache::loadDn($dn);
      
       	Zivios_Log::debug("here");

        $service->setProperty('emskrbpasslifedays',$form['passlife']);
        $service->setProperty('emskrbpassminlength',$form['minlength']);
        
        if ($form['pwexternal'] == 1) {            
        	$service->addPropertyItem('emskrbpasspolicies','external-check');
        } else {
        	$service->removePropertyItem('emskrbpasspolicies','external-check');
        }
        
	
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Kerberos Password Policies');
        $tgroup = $handler->newGroup('Changing Kerberos Password Policies',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $service->update($tgroup);
        $service->_updateCfg($tgroup,'Updating Kerberos config on Host');
        $service->_stopKdc($tgroup,"Stopping the KDC");
        $service->_startKdc($tgroup,"Starting KDC");
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->closeTab('krbsrvtabs01','krbpasswdconf');
            $this->refreshPane('dsbcenter');
        } 
        
        $this->sendResponse();    
    	
    }

}
