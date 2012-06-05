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
 * @version		$Id: GroupController.php 902 2008-08-25 06:39:02Z gmustafa $
 **/

class Asterisk_GroupController extends Zivios_Controller
{
	public function indexAction() {}

    protected function _init() {}
    

	public function dashboardAction()
	{
 		$this->render('dashboard');
	}

	
	 public function linktoserviceAction()
	{
         $this->_helper->layout->disableLayout(true); 
        $this->view->srvdn = urldecode($this->getParam('srvdn'));
        $dn = urldecode($this->getParam('dn'));
        $this->view->entry = Zivios_Ldap_Cache::loadDn($dn);
    }
    
    public function dolinktoserviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $form = $this->processForm('ast');
        Zivios_Log::debug($form);
        
        $srvobj = Zivios_Ldap_Cache::loadDn($form['srvdn']);
        $group = Zivios_Ldap_Cache::loadDn($form['dn']);
        
        $plugin = $group->newPlugin("AsteriskGroup");
        

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Asterisk Plugin To Group');
        $tgroup = $handler->newGroup('Adding Asterisk Plugin to Group',Zivios_Transaction_Group::EM_SEQUENTIAL );
        
        $plugin->linkToService($srvobj);
        
        $group->addPlugin($plugin,$tgroup);
        $tgroup->commit();
        
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('gtb01');
            $this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs','grpastplugin');
        }
        
        $this->sendResponse();
        
    }

}

