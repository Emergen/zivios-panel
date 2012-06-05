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
 * @package		mod_mail
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Mail_UserController extends Zivios_Controller_User
{
	private $mailPlugin, $mailService;

	protected function _init()
	{
		parent::_init();
	}

	public function dashboardAction()
	{
        $this->_helper->layout->disableLayout(true);
        //$this->_helper->viewRenderer->setNoRender();
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        $this->view->user = $user;
        
	}
    
    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);
        //$this->_helper->viewRenderer->setNoRender();
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        $this->view->user = $user;
    }

    public function generalinfoAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $this->view->user = Zivios_Ldap_Cache::loadDn($dn);
        $this->render('dashboard/generalinfo');
    }
    
    public function generalsettingsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
     
        $dn = $this->getParam('dn');

        $user = Zivios_Ldap_Cache::loadDn($dn);
        
        $plugin = $user->getPlugin('MailUser');
        
        $subform =$plugin->getGeneralForm();
        
        $form = new Zend_Dojo_Form();
        $form->setName('mailusergeneralform')
             ->setElementsBelongTo('mailusergeneralform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($subform,'mailusersubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('mailusergeneralform','mail/user/dogeneralsettings'); return false;",
        ));
        
        echo $form;
    }
    
    public function dogeneralsettingsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->getParam('mailusergeneralform');
        $dn = $form['dn'];
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $user->getPlugin('MailUser');
        $subform = $form['mailusersubform'];
        
        $mailplugin->setGeneralForm($subform);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing General Mail User Settings');
        $tgroup = $handler->newGroup('Changing General Mail User Settings',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $mailplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailusergeneralsettings');
            $this->refreshPane('mailusergeneralinfo');
            //$this->refreshPane('userdataleft');
        }
        
        $this->sendResponse();
    }
    
    
    public function aliasdisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $user = Zivios_Ldap_Cache::loadDn($this->getParam('dn'));
        $this->view->aliases = $user->getProperty('mailalternateaddress',1);
        $this->view->user = $user;
        $this->render('dashboard/aliasdisplay');
    }
    
    public function aliasformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $user = Zivios_Ldap_Cache::loadDn($this->view->dn);
        $this->view->mailuser = $user->getPlugin('MailUser');
        $this->render('dashboard/aliasform');
    }
    
    public function dochangealiasesAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        Zivios_Log::debug($_POST);
        $dn = urldecode($this->getParam('dn'));
        $aliasname = $this->getParam('alias');
        $domain = $this->getParam('aliasdomain');
        $alias = $aliasname . "@" . $domain;
        $action = $this->getParam('changeaction');
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $param = $user->getParameter('mailalternateaddress');
        if ($action == 'add') {
            $param->addValue($alias);
        } else {
            $param->removeValue($aliasname);
        }
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Changing Mail User Aliases');
        $tgroup = $handler->newGroup('Changing Mail User Aliases',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailuseraliasdisplay');
            //$this->refreshPane('userdataleft');
        }
        
        $this->sendResponse();
    }
    
    public function bldisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->user = $user;
        $plugin = $user->getPlugin('MailUser');
        $this->view->blacklists = $plugin->getProperty('amavisblacklistsender',1);
        $this->render('dashboard/bldisplay');
    }
    
     public function blformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('dashboard/blform');
    }
    
    public function dochangeblAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $bl = $this->getParam('bl');
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $user->getPlugin('MailUser');
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Email to Blacklist');
        $tgroup = $handler->newGroup('Adding BL',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $blparam = $mailplugin->getParameter('amavisblacklistsender');
        if ($action == 'add') 
            $blparam->addValue($bl);
        else if ($action == 'remove') 
            $blparam->removeValue($bl);
        
        $mailplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailuserblacklistdisplay');
            $this->addNotify('Blacklists changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
     public function wldisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $user->getPlugin('MailUser');
        $this->view->user = $user;
        $this->view->whitelists = $mailplugin->getProperty('amaviswhitelistsender',1);
        $this->render('dashboard/wldisplay');
    }
    
    public function wlformAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->dn = urldecode($this->getParam('dn'));
        $this->render('dashboard/wlform');
    }
    
    public function dochangewlAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $wl = $this->getParam('wl');
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $user->getPlugin('MailUser');
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Email to Whitelist');
        $tgroup = $handler->newGroup('Adding WL',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $wlparam = $mailplugin->getParameter('amaviswhitelistsender');
        if ($action == 'add') 
            $wlparam->addValue($wl);
        else if ($action == 'remove') 
            $wlparam->removeValue($wl);
        
        $mailplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('mailuserwhitelistdisplay');
            $this->addNotify('Whitelists changed Successfully.');
        } 
        
        $this->sendResponse();
    }
    
	public function dashviewAction()
	{
		$this->view->dashboardData = $this->loadDashboardData();
		$this->render("dashview");
	}

	public function accountDetailsAction()
	{
		$this->view->dashboardData = $this->loadDashboardData();
		$this->render("accountdetails");
	}

	public function securitySettingsAction()
	{
		$this->view->dashboardData = $this->loadDashboardData();
		$this->render("securitysettings");
	}

	public function foldersAction()
	{
		$this->view->dashboardData = $this->loadDashboardData();
		$this->render("folders");
	}

	public function unsubscribeAction()
	{
		if (isset($this->json->cfmboxunsub) && $this->json->cfmboxunsub == 1) {
			/**
			 * Process unsubscribe request for user mailbox.
			 */

			$this->_createPopupReturn('processing... :(');
			return;
		}

		$this->render("unsubscribe");
	}

	public function addAliasAction()
	{
		if (isset($this->json->newalias)) {
			$parameter = $this->mailPlugin->getParameter('mailalternateaddress');
			$parameter->addValue($this->json->newalias . "@". $this->json->aliasdomain);
			$trans = $this->mailPlugin->update();
			if ($this->processTransaction($trans)) {
				$this->_createPopupReturn(0,'Alias added successfully');
			}

		}
        return $this->accountDetailsAction();
	}

	public function deleteAliasAction()
	{
		if (isset($this->json->aliastodelete)) {
			$parameter = $this->mailPlugin->getParameter('mailalternateaddress');
			$parameter->removeValue($this->json->aliastodelete);
			$trans = $this->mailPlugin->update();
			if ($this->processTransaction($trans)) {
				$this->_createPopupReturn(0,'Alias removed successfully');
			}
		}
        return $this->accountDetailsAction();
	}
	public function updateMboxQuotaAction()
	{
		if (trim(strip_tags($this->json->nquota)) == '' || !is_numeric($this->json->nquota)) {
			$this->_createPopupReturn(1, "Please entry a valid quota in digits");
			return;
		}

		/**
		 * Try and set the quota.
		 */
		$quota = $this->json->nquota * 1024;
		if ($msg = $this->mailPlugin->setProperty('emsmailmboxquota',$quota)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		/**
		 * Quota setting successful -- process transaction and update user.
		 */
		$handler = $this->mailPlugin->update();
        if ($this->processTransaction($handler))
            $this->_createPopupReturn(0, "Mail Quota updated successfully.");

        return $this->accountDetailsAction();
	}

	public function uptAccountStatusAction()
	{
		/**
		 * Flip account status.
		 */
		switch ($this->mailPlugin->getProperty('emsmailactive')) {
			case MailUser::MAIL_ACTIVE :
				$this->mailPlugin->setProperty('emsmailactive',MailUser::MAIL_INACTIVE);
				break;

			case MailUser::MAIL_INACTIVE :
				$this->mailPlugin->setProperty('emsmailactive',MailUser::MAIL_ACTIVE);
				break;

			default:
				throw new Zivios_Exception("Could not determine mailbox status. Check User Entry.");
		}

		$handler = $this->mailPlugin->update();
	
		if ($this->processTransaction($handler)) {
			$this->_createPopupReturn(0, "Mailbox Status Updated");
			$this->view->dashboardData = $this->loadDashboardData();
			$this->render("dashview");
		}
	}

	/**
	 * Update mailbox security settings
	 */
	public function updateMboxSecurityAction()
	{
		/**
		 * Set the security class
		 */
		$this->mailPlugin->setProperty('emspostfixsecurityclass',$this->json->secclass);

		$maxmailsize = trim(strip_tags($this->json->maxmailsize));

		if ($maxmailsize == "" || !is_numeric($maxmailsize)) {
			$this->_createPopupReturn(1, "Please enter a valid value for Max Message Size");
			return;
		}
		$maxmailsize = $maxmailsize * 1024 * 1024;

		if ($msg = $this->mailPlugin->setProperty("amavismessagesizelimit", $maxmailsize)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		$blacklist = trim(strip_tags($this->json->blacklist));
		if ($blacklist != "")
			$blacklist = explode("\n",$blacklist);
		else
			$blacklist = null;

        if ($blacklist == null)
            $this->mailPlugin->getParameter('amavisblacklistsender')->nullify();
        else if ($msg = $this->mailPlugin->setProperty("amavisblacklistsender", $blacklist)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		$whitelist = trim(strip_tags($this->json->whitelist));
		if ($whitelist != "")
			$whitelist = explode("\n",$whitelist);
		else
			$whitelist = null;

        if ($whitelist == null)
            $this->mailPlugin->getParameter('amaviswhitelistsender')->nullify();
		else if ($msg = $this->mailPlugin->setProperty("amaviswhitelistsender", $whitelist)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		/**
		 * Set spam properties
		 */
		$spamtagone = trim(strip_tags($this->json->spamtagone));
		$spamtagtwo = trim(strip_tags($this->json->spamtagtwo));
		$spamkill = trim(strip_tags($this->json->spamkill));

		if ($msg = $this->mailPlugin->setProperty("amavisspamtaglevel", $spamtagone)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		if ($msg = $this->mailPlugin->setProperty("amavisspamtag2level", $spamtagtwo)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		if ($msg = $this->mailPlugin->setProperty("amavisspamkilllevel", $spamkill)) {
			$this->_createPopupReturn(1, $msg);
			return;
		}

		$handler = $this->mailPlugin->update();
		if ($this->processTransaction($handler)) {
			$this->_createPopupReturn(0, "Security settings for mailbox updated successfully");
		}
        return $this->securitySettingsAction();
	}

	/**
	 * Loads user data for mail plugin and returns it as
	 * an array
	 *
	 * @return array $dashboardData
	 * @todo need to load only required sections based on caller request.
	 */
	private function loadDashboardData($requestedData='')
	{
		$dashboardData = array();
		$blacklist = $this->mailPlugin->getProperty('amavisblacklistsender',1);
		$whitelist = $this->mailPlugin->getProperty('amaviswhitelistsender',1);

		if ($blacklist != "") {
			if (!is_array($blacklist)) {
				$dashboardData['blacklist'] = $blacklist;
			} else {
				$dashboardData['blacklist'] = implode("\n",$blacklist);
			}
		} else
			$dashboardData['blacklist'] = '';

		if ($whitelist != "") {
			if (!is_array($whitelist)) {
				$dashboardData['whitelist'] = $whitelist;
			} else
		    	$dashboardData['whitelist'] = implode("\n",$whitelist);
		}
		else
			$dashboardData['whitelist'] = '';

		/**
		 * Additional data required.
		 */
		$dashboardData['email'] = $this->mailPlugin->getProperty('mail');
		$dashboardData['maildomains'] = $this->mailService->getProperty('emsmaildomains',1);
		$dashboardData['aliases'] = $this->mailPlugin->getProperty('mailalternateaddress',1);
		$dashboardData['quota'] = $this->mailPlugin->getProperty('emsmailmboxquota') / 1024;
		$dashboardData['secclasses'] = $this->mailService->getPostfixSecurityClasses();
		$dashboardData['maxmailsize'] = $this->mailPlugin->getProperty('amavismessagesizelimit') / 1024 / 1024;
		$dashboardData['active'] = $this->mailPlugin->getProperty('emsmailactive');
		$dashboardData['spamtagone'] = $this->mailPlugin->getProperty('amavisspamtaglevel');
		$dashboardData['spamtagtwo'] = $this->mailPlugin->getProperty('amavisspamtag2level');
		$dashboardData['spamkill'] = $this->mailPlugin->getProperty('amavisspamkilllevel');
		$dashboardData['quotausage'] = $this->mailService->getStorageQuota(
			$this->mailPlugin->getProperty('emscyrusmboxroot'));
		$dashboardData['mailboxes'] = $this->mailService->getChildMailboxes(
			$this->mailPlugin->getProperty('emscyrusmboxroot'));
		$dashboardData['quotapercentused'] =
			($dashboardData['quotausage']['usage'] / $dashboardData['quotausage']['limit']) * 100;

		if ($dashboardData['active'] == MailUser::MAIL_ACTIVE)
		    $dashboardData['active'] = 1;
		else
			$dashboardData['active'] = 0;

		return $dashboardData;
	}

    public function editAction()
    {
        $obj = $this->view->obj;


        if (isset($this->json->action) && $this->json->action == 'update') {
            $mailplug = $obj->getPlugin("MailUser");
            $mailplug->setProperty('mail',$this->json->email);

            $quota = $this->json->quota * 1024;

            $meslimit = $this->json->maxmailsize * 1024 * 1024;
            $mailplug->setProperty('emsmailmboxquota',$quota);
            $mailplug->setProperty('amavismessagesizelimit',$meslimit);
            $mailplug->setProperty('emspostfixsecurityclass',$this->json->secclass);
            $blacklist = trim($this->json->blacklist);
            if ($blacklist != "") $blacklist = explode("\n",$blacklist);

            $whitelist = trim($this->json->whitelist);

            if ($whitelist != "") $whitelist = explode("\n",$whitelist);

            $mailplug->setProperty('amaviswhitelistsender',$whitelist);
            $mailplug->setProperty('amavisblacklistsender',$blacklist);
            $mailplug->setProperty('amavisspamtaglevel',$this->json->spamtagone);
            $mailplug->setProperty('amavisspamtag2level',$this->json->spamtagtwo);
            $mailplug->setProperty('amavisspamkilllevel',$this->json->spamkill);
            if (isset($this->json->active)) {
                $mailplug->setProperty('emsmailactive',MailUser::MAIL_ACTIVE);
            } else $mailplug->setProperty('emsmailactive',MailUser::MAIL_INACTIVE);

            $handler = $mailplug->update();
            if ($this->processTransaction($handler)) {
	            $this->_createPopupReturn(0,"Mail Plugin Updated Successfully");
	            $this->_jsCallBack('nodeDetails', array($obj->getdn()));
            }
        } else {

            $this->view->mailplugin = $this->view->obj->getPlugin("MailUser");
            $this->view->secclasses = $this->view->mailplugin->getService()->getPostfixSecurityClasses();
            $blacklist = $this->view->mailplugin->getProperty('amavisblacklistsender');
            $whitelist = $this->view->mailplugin->getProperty('amaviswhitelistsender');

            if ($blacklist != "") {
                if (is_array($blacklist)) $this->view->blacklist = implode("\n",$blacklist);
                else $this->view->blacklist = $blacklist;
            } else $this->view->blacklist = "";
            if ($whitelist != "") {
                if (is_array($whitelist)) $this->view->whitelist = implode("\n",$whitelist);
                else $this->view->whitelist = $whitelist;
            } else $this->view->whitelist = "";

            $this->render("edit");

        }
    }

    public function activatepluginAction()
    {
        
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');

        $user = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $user->newPlugin('MailUser');
        
        $subform =$plugin->getAddPluginForm();
        
        $form = new Zend_Dojo_Form();
        $form->setName('addmailuserform')
             ->setElementsBelongTo('addmailuserform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($subform,'addpluginsubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('addmailuserform','mail/user/doactivateplugin'); return false;",
        ));
        
        $this->view->form = $form;
    }
    
    public function doactivatepluginAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->getParam('addmailuserform');
        $dn = $form['dn'];
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $mailplugin = $user->newPlugin('MailUser');
        $subform = $form['addpluginsubform'];
        
        $mailplugin->setAddPluginForm($subform);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Mail Plugin To User');
        $tgroup = $handler->newGroup('Adding Mail Plugin to User',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->addPlugin($mailplugin,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('utb01');
            //$this->refreshPane('userdataleft');
            $this->closeTab('usertabs','usrmailplugin');
        }
        
        $this->sendResponse();
                                 
    }
        
        /*
        
    	if (isset($this->json->action) && $this->json->action == "activate") {
    		
    		$mailUser = $this->view->obj->newPlugin('MailUser');
    		$mailaddress = $this->view->obj->getProperty("uid").'@'.$this->json->maildomain;
    		$mailUser->setProperty('mail',$mailaddress);
    		$mailUser->setProperty('emspostfixsecurityclass',$this->json->secclass);
 
             
			$maxmailsize = trim(strip_tags($this->json->maxmailsize));
			if ($maxmailsize == "" || !is_numeric($maxmailsize)) {
				$this->_createPopupReturn(1, "Please enter a valid value for Max Message Size");
				return;
			}
			$maxmailsize = $maxmailsize * 1024 * 1024;
			if ($msg = $mailUser->setProperty("amavismessagesizelimit", $maxmailsize)) {
				$this->_createPopupReturn(1, $msg);
				return;
			}

			if (trim(strip_tags($this->json->quota)) == '' || !is_numeric($this->json->quota)) {
				$this->_createPopupReturn(1, "Please entry a valid quota in digits");
				return;
			}

			$quota = $this->json->quota * 1024;
			if ($msg = $mailUser->setProperty('emsmailmboxquota',$quota)) {
				$this->_createPopupReturn(1, $msg);
				return;
			}

			$blacklist = trim(strip_tags($this->json->blacklist));
			if ($blacklist != '') {
				$blacklist = explode("\n",$blacklist);
				if ($msg = $mailUser->setProperty("amavisblacklistsender", $blacklist)) {
					$this->_createPopupReturn(1, $msg);
					return;
				}
			}

			$whitelist = trim(strip_tags($this->json->whitelist));
			if ($whitelist != '') {
				$whitelist = explode("\n",$whitelist);

				if ($msg = $mailUser->setProperty("amaviswhitelistsender", $whitelist)) {
					$this->_createPopupReturn(1, $msg);
					return;
				}
			}

			$spamtagone = trim(strip_tags($this->json->spamtagone));
			$spamtagtwo = trim(strip_tags($this->json->spamtagtwo));
			$spamkill = trim(strip_tags($this->json->spamkill));

			if ($msg = $mailUser->setProperty("amavisspamtaglevel", $spamtagone)) {
				$this->_createPopupReturn(1, $msg);
				return;
			}

			if ($msg = $mailUser->setProperty("amavisspamtag2level", $spamtagtwo)) {
				$this->_createPopupReturn(1, $msg);
				return;
			}

			if ($msg = $mailUser->setProperty("amavisspamkilllevel", $spamkill)) {
				$this->_createPopupReturn(1, $msg);
				return;
			}

            $handler = $this->view->obj->addPlugin($mailUser);

            if ($this->processTransaction($handler)) {
	            $this->_createPopupReturn(0,"Mail Plugin Initialized Successfully");
	            $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
    			return;
            }
    	}

        $group = $this->view->obj->getGroupWithPlugin('MailGroup');
        $groupplug = $group->getPlugin('MailGroup');
        $service = $groupplug->getService();
        $this->view->secclasses = $service->getPostfixSecurityClasses();
        $this->view->maildomains = $service->getProperty("emsmaildomains");

        if (!is_array($this->view->maildomains))
        	$this->view->maildomains = array($this->view->maildomains);

        $this->render("add");
        */
    

    public function addPluginAction()
    {

        $obj = $this->view->obj;
        


        if (isset($this->json->action) && $this->json->action == 'doinstall') {
            //Zend_Debug::dump($this->json);
            //echo "yo";
            $mailplug = new MailUser($obj);
            $mailplug->setProperty('mail',$this->json->email);

            $quota = $this->json->quota * 1024;

            $meslimit = $this->json->maxmailsize * 1024 * 1024;
            $mailplug->setProperty('emsmailmboxquota',$quota);
            $mailplug->setProperty('amavismessagesizelimit',$meslimit);
            $mailplug->setProperty('emspostfixsecurityclass',$this->json->secclass);

            $blacklist = trim($this->json->blacklist);
            if ($blacklist != "") $blacklist = explode("\n",$blacklist);

            $whitelist = trim($this->json->whitelist);

            if ($whitelist != "") $whitelist = explode("\n",$whitelist);

            $mailplug->setProperty('amaviswhitelistsender',$whitelist);
            $mailplug->setProperty('amavisblacklistsender',$blacklist);

            $mailplug->setProperty('amavisspamtaglevel',$this->json->spamtagone);
            $mailplug->setProperty('amavisspamtag2level',$this->json->spamtagtwo);
            $mailplug->setProperty('amavisspamkilllevel',$this->json->spamkill);
            $handler = $obj->addPlugin($mailplug);
            if ($this->processTransaction($handler)) {
                $this->_createPopupReturn(0,"Mail Plugin Initialized Successfully");
                $this->_jsCallBack('nodeDetails', array($obj->getdn()));
            }

        } else {

            $group = $obj->getGroupWithPlugin('MailGroup');
            $groupplug = $group->getPlugin('MailGroup');
            $service = $groupplug->getService();
            $this->view->secclasses =  $service->getPostfixSecurityClasses();
       //     $this->view->secclasses = array();
            $this->render('add');
        }
    }

    
    public function deleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        $this->view->user = $user;
    }
    
    public function dodeleteAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $user = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Removing Mail Plugin from user '.$user->getdn());
        $tgroup = $handler->newGroup('Removing Mail Plugin from user '.$user->getdn(),Zivios_Transaction_Group::EM_SEQUENTIAL );
        $user->removePlugin('MailUser',$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->closeTab('usertabs','usrmailplugin');
            $this->refreshPane('mailusercpcenter');
            $this->refreshPane('utb01');
        }
        
        $this->sendResponse();
        
    }
}

