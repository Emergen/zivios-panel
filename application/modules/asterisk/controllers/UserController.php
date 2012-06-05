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
 * @version		$Id: UserController.php 1019 2008-09-08 07:26:34Z gmustafa $
 **/

class Asterisk_UserController extends Zivios_Controller_User
{
	public function indexAction() {}

	public function dashboardAction()
	{
        $this->_helper->layout->disableLayout(true);

        $dn = $this->getParam('dn');
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->user = $user;
        
		$this->render("dashboard");
	}
	
	public function dodashboardAction() 
	{
		$this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout(true);
        Zivios_Log::debug($_POST);
        $form = $this->processForm('ast');
        Zivios_Log::debug($form);
        $dn = $form['dn'];
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $astplugin = $user->getPlugin('AsteriskUser');
        $astplugin->setProperty('emsastexten',$form['astexten']);
        $astplugin->setProperty('astaccounthost','dynamic');
        $astplugin->setProperty('astaccountsecret',$form['password']);
        $astplugin->setProperty('astaccountcontext',$form['context']);
        $astplugin->setProperty('astaccountallowedcodec',$form['codecs']);
        $astplugin->setProperty('emsastroutesallowed',$form['routes']);
        $astplugin->setProperty('emsastphonelockcode',$form['plock']);
        $astplugin->setProperty('emsastdisable',$form['disabled']);
        $astplugin->setProperty('astaccountqualify',$form['qualify']);
        $astplugin->setProperty('emsastvoicemailenable',$form['vmenabled']);
        
        if ($form['vmenabled'] == 1) {
            $astplugin->setProperty('astvoicemailmailbox',$form['astexten']);
            $astplugin->setProperty('astvoicemailpassword',$form['vmpassword']);
            $astplugin->setProperty('astcontext','default');
        }
        
        if ($form['canreinvite'] == 1)
            $canreinvite = "yes";
        else
            $canreinvite = "no";
            
        $astplugin->setProperty('astaccountcanreinvite',$canreinvite);
            
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Asterisk Plugin');
        $tgroup = $handler->newGroup('Updating Asterisk Plugin',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $astplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        $this->sendResponse();    
	}
	
	public function activatepluginAction()
	{
		$this->_helper->layout->disableLayout(true);
		$dn = $this->getParam('dn');
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->user = $user;
        $this->view->plugin = $user->newPlugin('AsteriskUser');
        
	}
	
	public function doactivatepluginAction()
	{
		$this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout(true);
        Zivios_Log::debug($_POST);
        $form = $this->processForm('ast');
        Zivios_Log::debug($form);
        $dn = $form['dn'];
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $astplugin = $user->newPlugin('AsteriskUser');
        $astplugin->setProperty('emsastexten',$form['astexten']);
        $astplugin->setProperty('astaccounthost','dynamic');
        $astplugin->setProperty('astaccountsecret',$form['password']);
        $astplugin->setProperty('astaccountcontext',$form['context']);
        $astplugin->setProperty('astaccountallowedcodec',$form['codecs']);
        $astplugin->setProperty('emsastroutesallowed',$form['routes']);
        $astplugin->setProperty('emsastphonelockcode',$form['plock']);
        $astplugin->setProperty('emsastdisable',$form['disabled']);
        $astplugin->setProperty('astaccountqualify',$form['qualify']);
        
		$astplugin->setProperty('emsastvoicemailenable',$form['vmenabled']);
		if ($form['vmenabled'] == 1) {
		    $astplugin->setProperty('astvoicemailmailbox',$form['astexten']);
		    $astplugin->setProperty('astvoicemailpassword',$form['vmpassword']);
		    $astplugin->setProperty('astcontext','default');
		}
        
        if ($form['canreinvite'] == 1)
            $canreinvite = "yes";
        else
            $canreinvite = "no";
            
        $astplugin->setProperty('astaccountcanreinvite',$canreinvite);
            
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Activating Asterisk Plugin');
        $tgroup = $handler->newGroup('Activating Asterisk Plugin',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->addPlugin($astplugin,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
             $this->refreshPane('utb01');
            $this->closeTab('usertabs','usrastplugin');
        } 
        
        $this->sendResponse();    
        

	}

	public function addSipAction()
	{

		if (isset($this->json->action) && $this->json->action == 'doinstall') {

			$exten = $this->json->extennum;
			$oexten = $this->json->oexten;
			$secret = $this->json->extenpass;
			$allow = $this->json->allowed;
			$email = $this->view->obj->getProperty('mail');
			$name = $this->view->obj->getProperty('cn');
			$vmpass = $this->json->vmpass;
			$routes = $this->json->routes;

			if ($this->json->voicemail) {
				$vmpass = $this->json->vmpass;
			}

			$astservice = $this->view->obj->newPlugin("AsteriskUser");
			$agent = $astservice->_initCommAgent();

			/**
			 * comm agent
			 */
			$dict2send = array( 'codecs' => $allow,
								'exten'  => $exten,
								'oexten' => $oexten,
								'secret' => $secret,
								'vmpass' => $vmpass,
								'email'  => $email,
								'name'   => $name,
								'routes' => $routes
								);


			try
			{
				$agent->adduser($dict2send);
				$astservice->setProperty('emsastname',$exten);

				$handler = $this->view->obj->addPlugin($astservice);
				$handler->process();
			}
			catch (Exception $e)
			{
				$this->_createPopupReturn(1,$e->getMessage());
			}
			$this->_createPopupReturn(0,'Asterisk plugin added successfully');
		}
	}

	public function updateSipAction()
	{

		if (isset($this->json->action) && $this->json->action == 'dpupdate') {

			$exten = $this->json->extennum;
			$oexten = $this->json->oexten;
			$secret = $this->json->extenpass;
			$allow = $this->json->allowed;
			$email = $this->view->obj->getProperty('mail');
			$name = $this->view->obj->getProperty('cn');
			$vmpass = $this->json->vmpass;
			$routes = $this->json->routes;

			$astservice = $this->view->obj->newPlugin("AsteriskUser");
			$agent = $astservice->_initCommAgent();

			/**
			 * comm agent
			 */
			$dict2send = array( 'codecs' => $allow,
								'exten'  => $exten,
								'oexten' => $oexten,
								'secret' => $secret,
								'vmpass' => $vmpass,
								'email'  => $email,
								'name'   => $name,
								'routes' => $routes
								);


			try
			{
				$agent->adduser($dict2send);
				$astservice->setProperty('emsastname',$this->json->extennum);
				$handler = $astservice->update(null);
				$handler->process();
			}
			catch (Exception $e)
			{
				$this->_createPopupReturn(1,$e->getMessage());
			}

			$this->_createPopupReturn(0,"User's extensions updated, returned with Success");
		}
	}

	public function removeSipAction()
	{
			$astservice = new AsteriskUser($this->view->obj);

			$astservice->setProperty('emsastname','');

			$handler = $this->view->obj->removePlugin($astservice);
			//$handler = $astservice->delete($handler);

			$handler->process();
			$this->_createPopupReturn(0,"User's extensions deleted, returned with Success");
	}



}

