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
 * @package		mod_samba
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id$
 * @lastchangeddate $LastChangedDate$
 **/

class Samba_ServiceController extends Zivios_Controller
{



    public function _init() 
    {
        $this->_helper->layout->disableLayout(true);
    }
        
    
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
                    // @todo all control actions should go via transaction groups.
                    $this->addNotify('Ssamba Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('sambageneral'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start Samba Service. Please see system logs.');
                }
            break;

            case "stop" :
                if ($serviceEntry->stopService()) {
                    $this->addNotify('Samba Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('sambageneral'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop Samba Service. Please see system logs.');
                }
            break;

            default:
                throw new Zivios_Error('Unknown command option for service.');
        }
    }
    
    public function addserviceAction()
    {
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('You must pass the service container dn to continue.');
            }

        $serviceContainer = Zivios_Ldap_Cache::loadDn($dn);
        $serviceEntry = new SambaService();
        $serviceEntry->init();
        $serviceform = $serviceEntry->getMainForm($serviceContainer);
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

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Install Service',
            'onclick'     => "zivios.formXhrPost('addserviceform','samba/service/doaddservice'); return false;",
        ));
        
        $this->view->form = $form;
        $this->view->container = $serviceContainer;
        $this->render('addservice');
    }
    
    public function configformAction()
    {
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
                throw new Zivios_Error('You must pass the service dn to continue.');
            }

        $serviceEntry = Zivios_Ldap_Cache::loadDn($dn);
        
        $serviceform = $serviceEntry->getMainEditForm();
        $form = new Zend_Dojo_Form();
        $form->setName('editserviceform')
             ->setElementsBelongTo('editserviceform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($serviceform,'editform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Update Service',
            'onclick'     => "zivios.formXhrPost('editserviceform','samba/service/doconfigform'); return false;",
        ));
        
        $this->view->form = $form;
        $this->render('dashboard/configform');
    }
    
    public function doconfigformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $formdata = $this->getParam('editserviceform');
        $mainformdata = $formdata['editform'];
        $sambaService = Zivios_Ldap_Cache::loadDn($formdata['dn']);
        
        
        $sambaService->setMainEditForm($mainformdata);
       
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Samba Service');
        $tgroup = $handler->newGroup('Updating Samba Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $sambaService->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('sambageneral');
            $this->refreshPane('sambaconfiguration');
            $this->addNotify('Samba Service Updated Successfully');
        } 
        $this->sendResponse();
    }
    
   public function doaddserviceAction()
	{
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $formdata = $this->getParam('addserviceform');
        $mainformdata = $formdata['mainform'];
        $container = Zivios_Ldap_Cache::loadDn($formdata['dn']);
        
        $sambaService = new SambaService();
        $sambaService->init();
        Zivios_Log::debug($mainformdata);
        $sambaService->setMainForm($mainformdata,$container);
        
        $package = $sambaService->probeComputer();
        
        if ($package !== true) {
            $this->addNotify("Required Package <b>$package</b> not Installed or incorrect version on Target System");
            $this->sendResponse();
        } else {
            $handler = Zivios_Transaction_Handler::getNewHandler('Adding Samba Service');
            $tgroup = $handler->newGroup('Adding Samba Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $sambaService->add($container,$tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);
            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($container->getdn());
                $this->refreshPane('dirdata','/samba/service/dashboard/dn/'.urlencode($sambaService->getdn()));
                $this->addNotify('Samba Service successfully Initialized.');
            } 
            $this->sendResponse();
        }
    }


    public function dashboardAction()
    {
        $dn = urldecode($this->getParam('dn'));
        $service = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $service;                
        $this->render("dashboard");
    }

    public function generalAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        $this->render('dashboard/general');
    }
    
    
    public function dashviewAction()
    {
        if (isset($this->json)) {
            if ($this->json->action == 'start') {
                $this->view->obj->startService();
                $this->_createPopupReturn(0, "Samba Service Started");
            }
            if ($this->json->action == "stop") {
                $this->view->obj->stopService();
                $this->_createPopupReturn(0, "Samba Service Stopped");
            }
        }

         $this->view->masterComputer = $this->view->obj->mastercomp;
         $this->view->status = $this->view->obj->getStatus();

         $this->render('dashview');
    }

   

    public function sharesAction()
    {

        $this->_forward('addShare','share','samba');
    }



}


