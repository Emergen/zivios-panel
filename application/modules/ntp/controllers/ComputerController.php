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
 * @package     mod_ntp
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Ntp_ComputerController extends Zivios_Controller_Computer
{
    protected function _init()
    {
       // parent::_init();
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo "<div class='info'> The NTP Plugin does not provide any configuration options </div>";
        
        /*$ntpClient = new NtpComputer();
        $ntpClient->init($this->view->obj);
        $this->view->dashboardData = $ntpClient->getDashboardData();
        $this->render('dashboard');
        */
    }

    public function dashviewAction()
    {
        $ntpClient = new NtpComputer();
        $ntpClient->init($this->view->obj);
        $this->view->dashboardData = $ntpClient->getDashboardData();
        $this->render("dashview");
    }

    public function serviceConfigAction()
    {
        $this->render('serviceconfig');
    }

    /**
     * Subscribes computer object to the NTP Service.
     */
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

        /**
         * Linking computer to service.
         */
        $ntpService = Zivios_Ldap_Cache::loadDn($this->json->service_dn);
        //$ntpService = $ntpService->getObject();

        $ntpClient = $this->view->obj->newPlugin("NtpComputer");
        $ntpClient->linkToService($ntpService);
        $trans = $this->view->obj->addPlugin($ntpClient);
        $trans->process();

        /**
         * Update configuration file.
         */
        if ($ntpClient->updateClientConfig(1))
            $this->_createPopupReturn(0, "System subscribed successfully to the NTP Service");
        else
            $this->_createPopupReturn(0, "System subscribed successfully to the NTP Service." .
                " Service restart appears to have failed. Please check manually.");

        /**
         * Refresh computer object.
         */
        $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
        return;
    }

    public function stopServiceAction()
    {
        $ntpClient = $this->view->obj->getPlugin("NtpComputer");

        if ($ntpClient->stopService()) {
            $this->_createPopupReturn(0, "NTP Service Stopped");
        } else {
            $this->_createPopupReturn(1, "NTP Service could not be stopped. Please check logs");
        }

        $this->view->dashboardData = $ntpClient->getDashboardData();
        $this->render("dashview");
    }

    public function startServiceAction()
    {
        $ntpClient = $this->view->obj->getPlugin("NtpComputer");

        if ($ntpClient->startService()) {
            $this->_createPopupReturn(0, "NTP Service Started");
        } else {
            $this->_createPopupReturn(1, "NTP Service could not be started. Please check logs");
        }

        $this->view->dashboardData = $ntpClient->getDashboardData();
        $this->render("dashview");
    }

    public function updateConfigAction()
    {
        $ntpClient = $this->view->obj->getPlugin("NtpComputer");

        if ($ntpClient->updateClientConfig(1))
            $this->_createPopupReturn(0, "Configuration Update Successful. Client Restarted.");
        else
            $this->_createPopupReturn(0, "There was a problem restarting or updating the "
                . "client configuration. Please check system logs");
    }
}
