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

class Ntp_ServiceController extends Zivios_Controller_Service
{
    protected function _init()
    {}

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }

        // Initialize service & master server in view.
        $serviceEntry           = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service    = $serviceEntry;
    }

    public function serversdisplayAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->servers = $this->view->service->getProperty('emsntpserver',1);
        $this->render('dashboard/ntpservers');
    }

    public function syncstatusAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        if($this->requireAgent($this->view->service,'NTP Stats cannot be displayed')) {
            $this->view->syncstatus = $this->view->service->getSyncStatus();
            $this->render('dashboard/syncstatus');
        }
    }
    
    public function ntpgeneralAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = $this->getParam('dn');
        $this->view->service = Zivios_Ldap_Cache::loadDn($dn);
        $this->render('dashboard/general');
    }

    public function dochangesrvAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $dn = urldecode($this->getParam('dn'));
        $action = $this->getParam('changeaction');
        $srv = $this->getParam('srv');
        $ntpservice = Zivios_Ldap_Cache::loadDn($dn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Server as Sync Source to NTP');
        $tgroup = $handler->newGroup('Adding Sync Souirce',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $srvparam = $ntpservice->getParameter('emsntpserver');
        if ($action == 'add')
            $srvparam->addValue($srv);
        else if ($action == 'delete')
            $srvparam->removeValue($srv);

        $ntpservice->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('ntpserversdisplay');
            $this->refreshPane('ntpsyncstatus');
            $this->addNotify('NTP Servers changed Successfully.');
        }

        $this->sendResponse();
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
                    $this->addNotify('NTP Service started successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('ntpgeneral'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not start NTP Service. Please see system logs.');
                }
            break;

            case "stop" :
                if ($serviceEntry->stopService()) {
                    $this->addNotify('NTP Service stopped successfully.');
                    $this->addCallback('zivios.cpaneRefresh', array('ntpgeneral'));
                    $this->sendResponse();
                } else {
                    throw new Zivios_Error('Could not stop NTP Service. Please see system logs.');
                }
            break;

            default:
                throw new Zivios_Error('Unknown command option for service.');
        }
    }

    public function addServiceAction()
    {
        if (!isset($this->json->doinstall)) {
            $this->view->computers = $this->_getCompatiableComputers();

            if (!is_array($this->view->computers)) {
                /**
                 * Display Appropriate Help Screen
                 */
                echo '
                <div class="info">
                No Compatible computers were found for the NTP service. Please
                see a list of compatible computers systems below:
                <ul>
                    ';
                $computers = explode(",", $this->view->computers);

                for($c = 0; $c < sizeof($computers) -1; $c++) {
                    echo '<li />' . $computers[$c];
                }
                echo '
                </ul>
                </div>
                ';
            } else
                $this->render('addservice');
        } else {
            $this->_doLdapBind();
            $comp = new Zivios_LdapObject($this->_dirConnection, $this->json->mastercompdn);
            $comp = $comp->getObject();

            /**
             * Clean up user submitted data
             */
            if (trim(strip_tags($this->json->defntpservers)) == "") {
                $this->_createPopupReturn(1, "Please specify at least one External NTP server");
                return;
            }

            $skipBroadCastCheck = false;
            if (trim(strip_tags($this->json->broadcastto)) == "") {
                $skipBroadCastCheck = true;
            }

            $defntpservers = explode("\n", trim((strip_tags($this->json->defntpservers))));

            /**
             * Initialize communication with NTP module on server.
             */
            $commAgent = new Zivios_Comm_Agent($comp->getProperty("cn"), "ntp");

            if (!$commAgent->serviceStatus()) {
                $this->_createPopupReturn(1, "NTP Service does not appear to be running. " .
                    "Please start the service and try again");
                return;
            }

            /**
             * Instantiate Service
             */
            $masterpc = $this->json->mastercompdn;
            $ntpService = new NtpService($this->view->obj->newLdapObject(null));
            $ntpService->setProperty('emsmastercomputerdn',$masterpc);
            $ntpService->setProperty('emsdescription','Zivios NTP Service');

            /**
             * Set Params based on User submitted Data.
             */
            foreach ($defntpservers as $globalNtpServer) {
                /**
                 * Ensure that the supplied ntp server names are valid hostnames
                 * or ip addresses.
                 */
                if ($msg = $ntpService->addPropertyItem("emsntpserver", $globalNtpServer)) {
                    $this->_createPopupReturn(1, $msg);
                    return;
                }
            }

            if (!$skipBroadCastCheck) {
                $broadcastToSubnets = explode("\n", trim((strip_tags($this->json->broadcastto))));

                if (!empty($broadcastToSubnets)) {
                    foreach ($broadcastToSubnets as $subnet) {
                        if ($msg = $ntpService->addPropertyItem("emssubnetbroadcast", $subnet)) {
                            $this->_createPopupReturn(1, $msg);
                            return;
                        }
                    }
                }
            }

            if (isset($this->json->enbstats)) {
                $ntpService->setProperty('emsstatisticsenable', 1);
                if (isset($this->json->clstats))
                    $ntpService->addPropertyItem("emsstatistics", "clockstats");
                if (isset($this->json->lostats))
                    $ntpService->addPropertyItem("emsstatistics", "loopstats");
                if (isset($this->json->prstats))
                    $ntpService->addPropertyItem("emsstatistics", "peerstats");
            }

            $trans = $ntpService->add($this->view->obj);
            $trans->process();

            /**
             * Update master configuration file.
             */
            if ($ntpService->updateMasterConfig(true)) {
                $this->_createPopupReturn(0,"Service Initialized Successfully on System.");
                Zivios_Log::info("NTP Service initialized successfully on System." .
                    " Configuration File Updated and Service restarted");
            } else {
                $this->_createPopupReturn(1,"The service has been initialized, however, configuration" .
                    " update may not have taken place as desired. Please check service status manually.");

                Zivios_Log::error("NTP::Configuration Update procedure failed." .
                    " Please check prior Log messages");
            }

            $this->_jsCallBack('nodeDetails', array($ntpService->getdn()));
            $this->_refreshTreeView($this->view->obj->getdn());
        }
    }

    /*
    public function dashboardAction()
    {
        $this->view->dashboardData = $this->view->obj->loadDashboardData();
        $this->view->masterComputer = $this->view->obj->mastercomp;
        $this->render("dashboard");
    }

    public function dashviewAction()
    {
        $this->view->dashboardData = $this->view->obj->loadDashboardData();
        $this->view->masterComputer = $this->view->obj->mastercomp;
        $this->render("dashview");
    }
    */

    public function serviceConfigAction()
    {
        /**
         * Load all configuration settings to populate form.
         */
        if (!isset($this->json->process)) {
            $this->view->serviceSettings = $this->view->obj->getServiceSettings();
            $this->render("serviceconfig");
            return;
        }

        /**
         * Check user data and update service config.
         */
        if (trim(strip_tags($this->json->defntpservers)) == "") {
            $this->_createPopupReturn(1, "Please specify at least one External NTP server");
            return;
        }

        $skipBroadCastCheck = false;
        if (trim(strip_tags($this->json->broadcastto)) == "") {
            $skipBroadCastCheck = true;
        }

        $defntpservers = explode("\n", trim((strip_tags($this->json->defntpservers))));

        /**
         * Get current NTP Server settings and compare updates.
         */
        if ($msg = $this->view->obj->setProperty("emsntpserver", $defntpservers)) {
            $this->_createPopupReturn(1, $msg);
            return;
        }

        if (!$skipBroadCastCheck) {
            $broadcastToSubnets = explode("\n", trim((strip_tags($this->json->broadcastto))));

            if (!empty($broadcastToSubnets)) {
                if ($msg = $this->view->obj->setProperty("emssubnetbroadcast", $broadcastToSubnets)) {
                    $this->_createPopupReturn(1, $msg);
                    return;
                }
            }
        } else {
            /**
             * Remove any existing subnets the NTP service is broadcasting to.
             */
            $param = $this->view->obj->getParameter("emssubnetbroadcast");
            $param->nullify();
        }

        if (isset($this->json->enbstats)) {
            $this->view->obj->setProperty('emsstatisticsenable', 1);
            $enStats = array();
            if (isset($this->json->clstats))
                $enStats[] = 'clockstats';
            if (isset($this->json->lostats))
                $enStats[] = 'loopstats';
            if (isset($this->json->prstats))
                $enStats[] = 'peerstats';

            if (!empty($enStats)) {
                $this->view->obj->setProperty("emsstatistics", $enStats);
            } else {
                /**
                 * Remove stat monitoring.
                 */
                $rmStatMonitoring = true;
            }
        } else {
            $rmStatMonitoring = true;
        }

        /**
         * We remove any stat monitoring as directed by user.
         */
        if (isset($rmStatMonitoring)) {
            $param = $this->view->obj->getParameter("emsstatisticsenable");
            $param->nullify();
            $param = $this->view->obj->getParameter("emsstatistics");
            $param->nullify();
        }

        $trans = $this->view->obj->update();
        $trans->process();
        $this->view->obj->updateMasterConfig(1);
        $this->_createPopupReturn(0, "NTP service configuration updated and service restarted.");
        $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
        return;
    }

    public function stopServiceAction()
    {
        if ($this->view->obj->stopService()) {
            $this->_createPopupReturn(0, "NTP Service Stopped");
        } else {
            $this->_createPopupReturn(1, "NTP Service could not be stopped. Please check logs");
        }

        $this->view->dashboardData = $this->view->obj->loadDashboardData();
        $this->view->masterComputer = $this->view->obj->mastercomp;
        $this->render("dashview");
    }

    public function startServiceAction()
    {
        if ($this->view->obj->startService()) {
            $this->_createPopupReturn(0, "NTP Service Started");
        } else {
            $this->_createPopupReturn(1, "NTP Service could not be started. Please check logs");
        }

        $this->view->dashboardData = $this->view->obj->loadDashboardData();
        $this->view->masterComputer = $this->view->obj->mastercomp;
        $this->render("dashview");
    }
}
