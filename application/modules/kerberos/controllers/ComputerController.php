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
 * @package		mod_kerberos
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: ComputerController.php 913 2008-08-25 11:24:35Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:24:35 +0600 (Mon, 25 Aug 2008) $
 **/

class Kerberos_ComputerController extends Zivios_Controller_Computer
{
    
    protected function _init()
    {
    }
    
    public function dashboardAction()
	{
		$this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo "<div class='info'> The Kerberos Plugin does not provide any configuration options </div>";
        
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

        $krbService = Zivios_Ldap_Cache::loadDn($this->json->service_dn);
        $krbClient = $this->view->obj->newPlugin("KerberosComputer");
        $krbClient->linkToService($krbService);
        $trans = $this->view->obj->addPlugin($krbClient);
        $trans->process();

        /**
         * Update configuration file.
         */
        //if ($ntpClient->updateClientConfig(1))

        $this->_createPopupReturn(0, "System subscribed successfully to the Kerberos Service");

        /**
         * Refresh computer object.
         */
        $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
		return;
	}
}

