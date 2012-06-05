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
 * @package     mod_mail
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Mail_ServiceController extends Zivios_Controller
{
    protected function _init() 
    {
        //parent::_init();
    }
    
    public function addserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $serviceContainer = Zivios_Ldap_Cache::loadDn($dn);
        $serviceEntry = new MailService();
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
            'onclick'     => "zivios.formXhrPost('addserviceform','mail/service/doaddservice'); return false;",
        ));
        $this->view->form = $form;
        $this->view->container = $serviceContainer;
        $this->render('addservice');
    }
    
    public function doaddserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        /**
         * Validate 'add service' request and subscribe selected computers
         * to service
         */

        /**
         * Basic checks
         */
         Zivios_Log::Debug($_POST);
        $formdata = $this->getParam('addserviceform');
        $mainformdata = $formdata['mainform'];
        $container = Zivios_Ldap_Cache::loadDn($formdata['dn']);
        
        $mailService = new MailService();
        $mailService->init();
        Zivios_Log::debug($mainformdata);
        $mailService->setMainForm($mainformdata,$container);
        
        $package = $mailService->probeComputer();
        
        if ($package !== true) {
            $this->addNotify("Required Packages $package not Installed or incorrect version on Target System");
            $this->sendResponse();
        } else {
            $handler = Zivios_Transaction_Handler::getNewHandler('Adding Mail Service');
            $tgroup = $handler->newGroup('Adding Mail Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $mailService->add($container,$tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);
            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($container->getdn());
                $this->addNotify('Mail Service successfully Initialized.');
            } 
            
            $this->sendResponse();
        }
    }

    public function asavaction()
    {
        $rbls = $this->view->obj->getProperty('emsmailrbls');
        if (!is_array($rbls)) {
            $rblarray = array();
            $rblarray[] = $rbls;
        } else
            $rblarray = $rbls;

        $this->view->rbls = $rblarray;

        if (isset($this->json->action) && $this->json->action == 'update') {




            $blacklist = trim($this->json->blacklist);
            if ($blacklist != "") $blacklist = explode("\n",$blacklist);

            $whitelist = trim($this->json->whitelist);

            if ($whitelist != "") $whitelist = explode("\n",$whitelist);

            $this->view->obj->setProperty('amaviswhitelistsender',$whitelist);
            $this->view->obj->setProperty('amavisblacklistsender',$blacklist);
            $this->view->obj->setProperty('amavisspamtaglevel',$this->json->spamtagone);
            $this->view->obj->setProperty('amavisspamtag2level',$this->json->spamtagtwo);
            $this->view->obj->setProperty('amavisspamkilllevel',$this->json->spamkill);
            /**
             * Process RBLS
             */

            $rbls = $this->json->rbltext;
            $rbls = explode("\n",trim($rbls));
            $this->view->obj->setProperty('emsmailrbls',$rbls);
            /**
             * Update and process transaction
             */


            $trans = $this->view->obj->update();
            if ($this->processTransaction($trans))
                $this->_createPopupReturn(0,"AntiSpam/AntiVirus Settings updated successfully ");

        }

        $this->view->blacklist = $this->view->obj->getProperty('amavisblacklistsender');
        $this->view->whitelist = $this->view->obj->getProperty('amaviswhitelistsender');
        $this->view->spamtagone = $this->view->obj->getProperty('amavisspamtaglevel');
        $this->view->spamtagtwo = $this->view->obj->getProperty('amavisspamtag2level');
        $this->view->spamkill = $this->view->obj->getProperty('amavisspamkilllevel');
        $this->render('asav');

    }

    public function dashboardAction()
    {
        
        $this->_helper->layout->disableLayout(true);
        $this->view->entry = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        
        /*
        $this->view->masterComputer = $this->view->obj->mastercomp;
        $status = $this->view->obj->getServiceStatus();

       // Zivios_Log::debug($status);
        $problem = 0;
        $finalstatus = 1;
        foreach ($status as $statussingle) {
                $problem = $problem | $statussingle;
                $finalstatus = $finalstatus & $statussingle;
            }

        if ($problem && !$finalstatus) $finalstatus = -1;

        $this->view->status = $finalstatus;


        $this->render("manager");
        */
        $this->render('main');
    }
    

    public function dashviewAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->mastercomputer = $mailservice->getMasterComputer();
        
        $status = $mailservice->getServiceStatus();

        Zivios_Log::debug($status);
        $this->view->status = $status;

        $this->render('dashview');
    }
    
    public function stopserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Stopping Mail Service');
        $tgroup = $handler->newGroup('Stopping Mail Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $mailservice->_stopService($tgroup,'Stopping Mail Service');
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailmain');
            $this->addNotify('Mail Service Stopped Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    public function startserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Starting Mail Service');
        $tgroup = $handler->newGroup('Starting Mail Service',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $mailservice->_startService($tgroup,'Starting Mail Service');
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailmain');
            $this->addNotify('Mail Service Stared Successfully.');
        } 
        
        $this->sendResponse();
    }

    public function filterAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        
    }
    
    public function generalsettingsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $settingsform = $mailservice->getGeneralSettingsForm();
        
        $form = new Zend_Dojo_Form();
        $form->setName('filtersettingsform')
             ->setElementsBelongTo('filtersettingsform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($settingsform,'filtersubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('filtersettingsform','/mail/service/dogeneralsettings'); return false;",
        ));
        $this->view->form = $form;
        $this->render('filter/generalsettings');
    }
    
    public function dogeneralsettingsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->getParam('filtersettingsform');
        Zivios_Log::debug($form);
        $dn = urldecode($form['dn']);
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing General Settings');
        $tgroup = $handler->newGroup('Changing General Settings',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $form = $form['filtersubform'];
        $mailservice->setGeneralSettingsForm($form);
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('filtergeneralsettings');
            $this->addNotify('General Settings changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    public function rbldisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->rbls = $mailservice->getProperty('emsmailrbls',1);
        $this->render('filter/rbls');
    }
    
    public function rblformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('filter/rblform');
    }
    
    public function bldisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->blacklists = $mailservice->getProperty('amavisblacklistsender',1);
        $this->render('filter/blacklists');
    }
    
    public function blformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('filter/blform');
    }
    
    public function dochangeblAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $bl = $this->getParam('bl');
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Email to Blacklist');
        $tgroup = $handler->newGroup('Adding BL',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $blparam = $mailservice->getParameter('amavisblacklistsender');
        if ($action == 'add') 
            $blparam->addValue($bl);
        else if ($action == 'delete') 
            $blparam->removeValue($bl);
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('filterblacklistdisplay');
            $this->addNotify('Blacklists changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    
    public function wldisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->whitelists = $mailservice->getProperty('amaviswhitelistsender',1);
        $this->render('filter/whitelists');
    }
    
    public function wlformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('filter/wlform');
    }
    
    public function dochangewlAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $wl = $this->getParam('wl');
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Email to Whitelist');
        $tgroup = $handler->newGroup('Adding WL',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $wlparam = $mailservice->getParameter('amaviswhitelistsender');
        if ($action == 'add') 
            $wlparam->addValue($wl);
        else if ($action == 'delete') 
            $wlparam->removeValue($wl);
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('filterwhitelistdisplay');
            $this->addNotify('Whitelists changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    
    public function dochangerblAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $rbl = $this->getParam('rbl');
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding RBL');
        $tgroup = $handler->newGroup('Adding RBL',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $rblparam = $mailservice->getParameter('emsmailrbls');
        if ($action == 'add') 
            $rblparam->addValue($rbl);
        else if ($action == 'delete') 
            $rblparam->removeValue($rbl);
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('filterrbldisplay');
            $this->addNotify('RBLs changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
     public function routingAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        
    }
    
    public function routinggeneralAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $settingsform = $mailservice->getRoutingGeneralForm();
        
        $form = new Zend_Dojo_Form();
        $form->setName('routinggeneralform')
             ->setElementsBelongTo('routinggeneralform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($settingsform,'settingsform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('routinggeneralform','mail/service/doroutinggeneral'); return false;",
        ));
        $this->view->form = $form;
        $this->render('routing/routinggeneral');
    }
    
    public function doroutinggeneralAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        
        
        $form = $this->getParam('routinggeneralform');
        
        $dn = urldecode($form['dn']);
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $subform = $form['settingsform'];
        $mailservice->setRoutingGeneralForm($subform);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Routing General Action');
        $tgroup = $handler->newGroup('Changing Routing General Action',Zivios_Transaction_Group::EM_SEQUENTIAL );
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('routinggeneral');
            $this->addNotify('General Routing Changed successfully');
        } 
        
        $this->sendResponse();
        
    }
    
    
    
    public function relaydomainsdisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->relaydomains = $mailservice->getProperty('emsmaildomains',1);
        $this->render('routing/relaydomains');
    }
    
    public function relaydomainsformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('routing/relaydomainsform');
    }
    
    public function dochangerelaydomainsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $rd = $this->getParam('rd');
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Relay Domains');
        $tgroup = $handler->newGroup('Changing Relay Domains',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $rdparam = $mailservice->getParameter('emsmaildomains');
        if ($action == 'add') 
            $rdparam->addValue($rd);
        else if ($action == 'delete') 
            $rdparam->removeValue($rd);
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('relaydomainsdisplay');
            $this->addNotify('Relay Domains changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    public function transportdisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry = $mailservice;
        $this->view->transports = $mailservice->getProperty('emsmailtransports',1);
        $this->render('routing/transports');
    }
    
    public function transportformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('routing/transportform');
    }
    
    public function dochangetransportsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Transports');
        $tgroup = $handler->newGroup('Changing Transports',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $trparam = $mailservice->getParameter('emsmailtransports');
        if ($action == 'add') {
            //TODO: compute the transport value
            $domain = $this->getParam('domain');
            $type = $this->getParam('type');
            $dest = $this->getParam('destination');
            $transport = $domain . ":" . $type . ":" . $dest;
            $trparam->addValue($transport);
        }
        else if ($action == 'delete') {
            $transportline = $this->getParam('transportline');
            $trparam->removeValue($transportline);
        }
        
        $mailservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('transportdisplay');
            $this->addNotify('Transports changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
    public function monitorAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        //$this->render('routing/transportform');
    }
    
    public function deferredqueuemonitorAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->dn = $dn;
        $this->view->deferredqueue = $mailservice->getDeferredQueue();
        $this->render('monitor/deferredqueue');
    }
    
    public function activequeuemonitorAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->dn = $dn;
        $this->view->activequeue = $mailservice->getActiveQueue();
        $this->render('monitor/activequeue');
    }

    public function doflushqueueAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $mailservice->flushMessageQueue();
        $this->refreshPane('activelayout');
        $this->refreshPane('deferredlayout');
        $this->addNotify('Message Queue Flushed Successfully.');
        $this->sendResponse();
    }
    
    public function domanagequeueAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = urldecode($this->getParam('dn'));
        $mailservice = Zivios_Ldap_Cache::loadDn($dn);
        $ids = $this->getParam('ids');
        foreach ($ids as $id) {
            $mailservice->deleteMessageFromQueue($id);
        }
        
        $this->refreshPane('activelayout');
        $this->refreshPane('deferredlayout');
        $this->addNotify('Message(s) deleted Successfully.');
        $this->sendResponse();
    }
    
    public function generalAction()
    {
        $dn = $this->json->operate_dn;
        $action = $this->json->action;
        Zivios_Log::debug("Max size is ".$this->json->maxsize);
        $maxmailsizeparam = $this->view->obj->getParameter('emsmailmessagesizelimit');
        if ($action == "update") {
            $maxsize = $this->json->maxsize * 1024 * 1024;
            $maxmailsizeparam->setValue($maxsize);
            $handler = $this->view->obj->update();
            if ($this->processTransaction($handler))
                $this->_createPopupReturn(0,"General Settings updated successfully");
        }

        $this->view->maxmailsize = $maxmailsizeparam->getValue();
        $this->render("general");

    }
    
    
    public function securityAction()
    {
        $this->render('security');
    }


/*    public function routingAction()
    {
        $dn = $this->json->operate_dn;
        $action = $this->json->action;
        $relayhost = $this->view->obj->getParameter('emsmailrelayhost');
        $mydestination = $this->view->obj->getParameter('emsmaildomains');

        if ($action == "update") {
            $relayhostparam = $this->json->relayhost;
            $suppressmx = $this->json->suppressmx;

            if ($suppressmx == 1) {
                Zivios_Log::debug("Supressing MX");
                $relayhostparam = "[$relayhostparam]";
            }
            Zivios_Log::debug("Setting relayhost to $relayhostparam");
            $relayhost->setValue($relayhostparam);

            //Handle relay domains

            $relaydomains = $this->json->relaydomains;
            $relaydomains = explode("\n",trim($relaydomains));
            $mydestination->setValue($relaydomains);


            $handler = $this->view->obj->update();
            if ($this->processTransaction($handler)) {
                $this->_createPopupReturn(0,"Routing Information Updated Successfully");
            }
        }

        $this->view->relayhost = $relayhost->getValue();
        $this->view->relaydomains = $mydestination->getValue(1);
        $this->view->transports = $this->view->obj->getProperty('emsmailtransports');
        $this->render("routing");

    }
    */

    public function transportAction()
    {
        $action = $this->json->action;
        if ($action == 'add') {
            $this->view->obj->addTransport($this->json->adddomain,$this->json->addserver,$this->json->addtype);
            $handler = $this->view->obj->update();
            $handler->process();

        } else if ($action == 'delete') {
            $this->view->obj->removeTransport($this->json->transportname);
            $handler = $this->view->obj->update();
            $handler->process();
        }


        $this->json->action = "";
        $this->routingAction();

    }



    public function secclassAction()
    {
        $dn = $this->json->operate_dn;
        $action = $this->json->action;
        $secclass = $this->view->obj->getPostfixSecurityClasses();
        if ($action == "update") {

        } else {
            $this->view->secclasses = $secclass;
            $this->view->secclassdetails = array();
            foreach ($this->view->secclasses as $secclass) {
                $details = $this->view->obj->getPostfixParam($secclass);
                $this->view->secclassdetails[$secclass] = $details;
            }
            $this->render("secclass");
        }
    }

    public function queueAction()
    {
        if (isset($this->json->action) && $this->json->action == 'delete') {
            $allgood = 1;
            if (is_array($this->json->idselect)) {

                foreach ($this->json->idselect as $id) {
                    $resp = $this->view->obj->deleteMessageFromQueue($id);
                    $allgood = $allgood * $resp;

                }
            } else {
                $resp = $this->view->obj->deleteMessageFromQueue($this->json->idselect);
                Zivios_Log::debug("delete returned : $resp");
                $allgood = $allgood * $resp;
            }

            if ($allgood) $this->_createPopupReturn(0,"Messages Deleted Successfully");
            else $this->_createPopupReturn(1,"Error Deleting Messages");

        } else if (isset($this->json->action) && $this->json->action == 'flush') {
            $resp = $this->view->obj->flushMessageQueue();

            if ($resp) $this->_createPopupReturn(0,"Queue Flushed Successfully");

            else $this->_createPopupReturn(1,"Error Flushing Queue");
        }
        $this->view->activequeueobj = $this->view->obj->getActiveQueue();
        $this->view->deferqueueobj = $this->view->obj->getDeferredQueue();

        $this->render('queue');
    }

    public function statusAction()
    {
        //NOt Implemented!
        //$this->view->status = $this->view->obj->getServiceStatus();

        if (isset($this->json->action)) {
            if ($this->json->action == 'stop') {
                $status = $this->view->obj->stopService();
                $this->view->statusfor = 'Stop';

            }
            else if ($this->json->action == 'start') {
                $status = $this->view->obj->startService();
                $this->view->statusfor = 'Start';
            }
            else if ($this->json->action == 'restart') {
                $transaction = $this->view->obj->restartService();
                $this->processTransaction($transaction);
                $this->view->statusfor  = 'Restart';
            }

            $this->view->status = $status;

            $statusobj = new StdClass();

            $statusstr = array();
            foreach ($status as $single)
            {
                if ($single == 1) $statusstr[] = "<font color=green>Success!</font>";
                else $statusstr[] = "<font color=red>Failed!</font>";
            }



            $statusobj->spamassassin = $statusstr[0];
            $statusobj->clamav = $statusstr[1];
            $statusobj->amavis = $statusstr[2];
            $statusobj->postfix = $statusstr[3];
            $statusobj->cyrus = $statusstr[4];

            $this->view->statusobj = $statusobj;






            $finalstatus = 1;
            foreach ($status as $statussingle) {
                $finalstatus = $finalstatus * $statussingle;
            }

            if ($finalstatus) $this->_createPopupReturn(0,"Service control action successful");
            else $this->_createPopupReturn(1,"Command Failed");

        }


        $pidstatus = new StdClass();

        $servicestatus = $this->view->obj->getServiceStatus();

        Zivios_Log::debug("Got service status :");
        Zivios_Log::debug($servicestatus);
        $finalstatus = 1;
        foreach ($servicestatus as $single)
        {
            if ($single == 1) $servstatstr[] = "<font color=green>Running</font>";
            else {
                $servstatstr[] = "<font color=red>Stopped</font>";
                $finalstatus = 0;
            }
        }

        $pidstatusobj->spamassassin = $servstatstr[0];
        $pidstatusobj->clamav = $servstatstr[1];
        $pidstatusobj->amavis = $servstatstr[2];
        $pidstatusobj->postfix = $servstatstr[3];
        $pidstatusobj->cyrus = $servstatstr[4];

        $this->view->pidstatusobj = $pidstatusobj;
        $this->view->finalstatus = $finalstatus;

        $this->render("status");
    }













}
