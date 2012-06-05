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

class Samba_GroupController extends Zivios_Controller_Group
{

    public function _init() 
    {
        $this->_helper->layout->disableLayout(true);
        
    }
        
    public function linktoserviceAction()
	{
        $this->view->srvdn = urldecode($this->getParam('srvdn'));
        $this->view->dn = urldecode($this->getParam('dn'));
      
    }
    
    public function dolinktoserviceAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $service = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('srvdn')));
        $group = Zivios_Ldap_Cache::loadDn(urldecode($this->getParam('dn')));
        $sambaplugin = $group->newPlugin("SambaGroup");
        $sambaplugin->setProperty('sambagrouptype',2);
        $sambaplugin->linkToService($service);
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Samba Plugin To Group');
        $tgroup = $handler->newGroup('Adding Samba Plugin to Group',Zivios_Transaction_Group::EM_SEQUENTIAL );
                
        $group->addPlugin($sambaplugin,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('gtb01');
            $this->refreshPane('groupdataleft');
            $this->closeTab('grouptabs','sambaplugin');
        }
        $this->sendResponse();
    }

    public function dashboardAction()
    {
        echo '<p><br /><div class="notice" style="width: 74%;">Welcome to the Zivios Samba Group Dashboard.
        We will have many things here at some point -- however for now we dont have any. Enjoy.</div></p>';
    }

}

