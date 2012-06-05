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
 * @package     mod_package
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Package_ComputerController extends Zivios_Controller_Computer
{
    protected function _init()
    {}

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $computer = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->computer = $computer;
    }
    
    public function searchpackagesAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $computer = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $computer->getPackagePlugin();
        $searchname = $this->getParam('searchname');
        $this->view->packages = $plugin->searchInstalledPackages($searchname);
        $this->render('resultsdisplay');
    }
    
    public function repopulatepackagesAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = urldecode($this->getParam('dn'));
        $computer = Zivios_Ldap_Cache::loadDn($dn);
        $plugin = $computer->getPackagePlugin();
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Repopulating Computer Packages for dn '.$dn);
        $tgroup = $handler->newGroup('Repopulating Computer Packages',Zivios_Transaction_Group::EM_SEQUENTIAL);
        
        $plugin->_refreshPackages($tgroup,'Package Refresh for dn : '.$dn);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('packagedisplay');
        }
        
        $this->sendResponse();
    }
}

