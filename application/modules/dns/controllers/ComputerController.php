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
 * @package		mod_dns
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: ComputerController.php 912 2008-08-25 11:18:59Z fkhan $
 **/

class Dns_ComputerController extends Zivios_Controller_Computer
{
    
    protected function _init()
    {
    }
    
    public function dashboardAction()
	{
		$this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo "<div class='info'> The DNS Plugin does not provide any configuration options </div>";
        
	}
    
    public function linkComputerToServiceAction()
	{
		if (!isset($this->json->process)) {
			/**
			 * Instantiate the Service.
			 */

             $service = Zivios_Ldap_Cache::loadDn($this->json->srvSelect);
            $this->view->service = $service;
			unset($service);

			$this->render("subscribetoservice");
			return;
		}

        $DnsService = Zivios_Ldap_Cache::loadDn($this->json->service_dn);
		//$ntpService = E$ntpService->getObject();

        $DnsClient = $this->view->obj->newPlugin("DnsComputer");
        $DnsClient->linkToService($DnsService);
        $trans = $this->view->obj->addPlugin($DnsClient);
        $trans->process();

        /**
         * Update configuration file.
         */


        $this->_createPopupReturn(0, "System subscribed successfully to the Dns Service");


        /**
         * Refresh computer object.
         */
        $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
		return;
	}


}
