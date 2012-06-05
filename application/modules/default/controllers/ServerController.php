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
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class ServerController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function mainctrlAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!$this->getRequest()->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }
        
        if (null === ($dn = $_POST['dn']) || 
            null === ($action = $_POST['action'])) {
            throw new Zivios_Error('Required data not present in request');
        } else {
            $dn = strip_tags(urldecode($dn));
            $serverEntry = Zivios_Ldap_Cache::loaddn($dn);
        }      

        switch ($action) {
            case 'shutdown':
                $request = array('action' => 'shutdown');
                if ($serverEntry->serverCtrl($request)) {
                    $this->addNotify('Server shutdown command executed.');
                }
                break;
            
            case 'reboot': 
                $request = array('action' => 'reboot');
                if ($serverEntry->serverCtrl($request)) {
                    $this->addNotify('Server reboot command executed.');
                }

            case 'probe':
                $request = array('action' => 'probe');
                if ($serverEntry->serverCtrl($request)) {
                    // Server details updated -- creating transaction:
                    
                    $handler = Zivios_Transaction_Handler::getNewHandler('Updating server hardware details for: '
                        . $serverEntry->getProperty('cn'));
                    $tgroup = $handler->newGroup('Updating server hardware details', 
                        Zivios_Transaction_Group::EM_SEQUENTIAL);
        
                    // Run transaction
                    $serverEntry->update($tgroup);
                    $tgroup->commit();
                    $status = $this->processTransaction($handler);

                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $this->addNotify('Server probe complete. Refreshing details...');
                        $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                           'default/server/view/dn/' . $serverEntry->getdn()));
                    } else {
                        throw new Zivios_Error('Error during transaction write. Please check system logs.');
                    }
                } else {
                    throw new Zivios_Error('Error during hardware probe. Please check system logs.');
                }
        }

        $this->sendResponse();
    }

    public function startagentAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->getRequest()->isPost()) {
            throw new Zivios_Error('Invalid call received by controller.');
        }
        
        if (null === ($dn = $_POST['dn']) || 
            null === ($rpass = $_POST['rpass'])) {
            throw new Zivios_Error('Required data not present in request');
        } else {
            $dn = strip_tags(urldecode($dn));
            $serverEntry = Zivios_Ldap_Cache::loaddn($dn);
        }

        $serverEntry->restartZiviosAgent('root', $rpass);
        $this->addNotify('Zivios Agent restart <b>attempted</b>. Refreshing entry...');
        $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                           'default/server/view/dn/' . $serverEntry->getdn()));

        $this->sendResponse();
    }

    public function viewAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }
        
        // Load server entry from cache.
        $serverEntry = Zivios_Ldap_Cache::loadDn($dn);

        // Based on Server OS, set the required views.
        switch (strtolower($serverEntry->getProperty('emscomputersystem'))) {

            case "linux" : 
                // Load active client services and scan for available services.
                $serviceData = $serverEntry->initializeServiceScan();

                // Check if the server is reachable via the agent.
                // The template selected will load relevant options based on whether
                // the agent is online or not.
                if ($serverEntry->pingAgent()) {
                    $this->view->agenttemplate = 'server/entry/linux/dashboard/aon.phtml';
                } else {
                    $this->view->agenttemplate = 'server/entry/linux/dashboard/aoff.phtml';
                }
            
                // Assign service data to view
                $this->view->availableServices = $serviceData['availableServices'];
                $this->view->existingServices  = $serviceData['existingServices'];

                // Set toolbar and dashboard file.
                $this->view->entry      = $serverEntry;
                $this->view->toolbar    = 'server/entry/linux/toolbar/tb01.phtml';
                $this->view->dataview   = 'server/entry/linux/dashboard.phtml';
                $this->view->tabheading = 'Linux Server Dashboard';
                break;

            default: 
                throw new Zivios_Error('Server operating system unrecognized by Zivios.');
        }
        
        Zivios_Log::debug("Server index action ended : ".microtime(true));
    }

    public function viewcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $serverContainer  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry   = $serverContainer;
        $this->view->toolbar = "server/container/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "server/container/dashboard/main.phtml";
    }

    public function deletecontainerAction()
    {
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $serverContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $containerParent = $serverContainer->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this container
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting ServerContainer');
            $tgroup = $handler->newGroup('Deleting a Server Container',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $serverContainer->delete($tgroup);
            $tgroup->commit();
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($containerParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Server Container (<em>".
                                  $serverContainer->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('Server Container deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Server Container. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $serverContainer;
            $this->view->tabheading = "Delete Server Container";
            $this->view->dataview = "server/container/delete/delete.phtml";
            $this->render('deletecontainer');
        }
    }

    public function doaddcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('servercontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['servercontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // set parentdn
        $parentdn = $formData->cleanValues['dn'];

        $serverContainer = new EMSOrganizationalUnit();
        $serverContainer->init();

        // Initialize container with suppplied data.
        $serverContainer->setAddServerContainerForm($formData->cleanValues);
        
        // Load parent
        $containerParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Server Container');
        $tgroup = $handler->newGroup('Creating New Server Container', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $serverContainer->add($containerParent, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($containerParent->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                'default/server/viewcontainer/dn/'.$serverContainer->getdn()));
            $this->addNotify('Server Container added successfully');
        } else {
            throw new Zivios_Error('Error adding Server Container. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function addcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($this->_request->getParam('dn')));
        }
    }

    public function deleteserverAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($this->_request->getParam('dn')));
        }

        $this->view->server = Zivios_Ldap_Cache::loadDn($dn);
    }

    public function dodeleteserverAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($this->_request->getParam('dn')));
            $serverEntry = Zivios_Ldap_Cache::loadDn($dn);
            $serverContainer = $serverEntry->getParent();
        }

        if (null === ($confirm = $this->_request->getParam('confirm')) &&
            $confirm != true) {
            throw new Zivios_Error('Missing data in request.');
        }

        // Delete server.
        $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Server');
        $tgroup = $handler->newGroup('Deleting Server', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $serverEntry->delete($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($serverContainer->getdn());
            $this->addDivData('dirdata',
                              "<div class='note'>Server (<em>".
                              $serverEntry->getProperty('cn')."</em>) deleted successfully.</div>");
            $this->addNotify('Server deleted successfully');
        } else {
            throw new Zivios_Error('Error deleting Server. Please check system logs.');
        }

        $this->sendResponse();
    }

    /**
     * Function handles multiple post requests to render required forms before
     * generating the final form for server add.
     */
    public function addserverAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if ($this->_request->isPost()) {

            if (!Zivios_Util::isFormPost('addserver')) {
                throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
            }

            $formData = new Zivios_ValidateForm($_POST['addserver']);
            if ($formData->err !== false) {
                if (null === ($appendData = $formData->errMsg)) {
                    $appendData = false;
                }

                throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
            }

            $osselect = $formData->cleanValues['osselect'];
            $dn = $formData->cleanValues['dn'];

            switch ($osselect) {
                case "linux" :
                    // Get Linux server add form.
                    $args = array('dirdata','/default/server/probelinuxsystem/dn/'.urlencode($dn));
                    Zivios_Log::debug($args);
                    $this->addCallback('zivios.cpaneRefresh', $args);
                    $this->sendResponse();
                    break;

                default:
                    throw new Zivios_Error('Invalid data in request / OS Support unavailable');
            }
        } else {
            if (null === ($dn = $this->_request->getParam('dn'))) {
                throw new Zivios_Error('Specified entry not found in system.');
            } else {
                $this->view->parentdn = strip_tags(urldecode($this->_request->getParam('dn')));
            }

            $this->render();
        }
    }

    public function probelinuxsystemAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_request->isPost()) {
            if (null === ($dn = $this->_request->getParam('dn'))) {
                throw new Zivios_Error('Invalid data received in request.');
            } else {
                $this->view->parentdn = strip_tags(urldecode($dn));
            }

            $this->render();
        } else {
        
            // Set server details in session with a registered callback and probeId
            // Call cpaneRefresh (for linuxprobe contentpane) in callback with probeId.

            if (!Zivios_Util::isFormPost('addserverprobedata')) {
                throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
            }

            $formData = new Zivios_ValidateForm($_POST['addserverprobedata']);
            if ($formData->err !== false) {
                if (null === ($appendData = $formData->errMsg)) {
                    $appendData = false;
                }

                throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
            }
        
            $probeId = Zivios_Util::randomString(8);
            $this->_session->addsystem = array();
            $this->_session->addsystem[$probeId]['user']     = 'root';
            $this->_session->addsystem[$probeId]['password'] = Zivios_Security::encrypt($formData->cleanValues['password']);
            $this->_session->addsystem[$probeId]['iphost']   = $formData->cleanValues['iphostnumber'];
            $this->_session->addsystem[$probeId]['parentdn'] = $formData->cleanValues['dn'];

            $args = array('linuxprobe','default/server/linuxprobe/probeid/'.$probeId);
            $this->addCallback('zivios.cpaneRefresh', $args);
            $this->sendResponse();       
        }
    }

    public function linuxprobeAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (null === ($probeid = $this->_request->getParam('probeid'))) {
            $this->render('addserver/linuxprobe');
        } else {
             // Ensure required probeid is present in request and exists in user session data.
            if (isset($this->_session->addsystem) && is_array($this->_session->addsystem)) {
                if (array_key_exists($probeid, $this->_session->addsystem)) {
                    $sysDetails = $this->_session->addsystem[$probeid];

                    // Probe Linux system & get results
                    // Make ready form to add system to Zivios network.
                    $serverEntry = new EMSLinuxComputer();
                    $serverEntry->init();

                    $serverEntry->setProperty('iphostnumber', $sysDetails['iphost']);
                    $serverEntry->setSshHandler(0, 'root', 
                        Zivios_Security::decrypt($sysDetails['password']));
                    
                    $distroDetails = $serverEntry->probeDistributionDetails(true);
                    $systemDetails = $serverEntry->probeSystemDetails(true);
                    $reqPackages   = $serverEntry->probeRequiredPackages();

                    // Check if Linux distribution is compatible with Zivios.
                    if (!$serverEntry->checkCompatibility($distroDetails)) {
                        $systemDetails['zvcompatible'] = 0;
                        $this->view->distroDetails = $distroDetails;
                        $this->view->systemDetails = $systemDetails;

                    } else {
                        $systemDetails['zvcompatible'] = 1;
                        // Get server configuration form & assign to view.
                        // Incompatible systems will see a "non-supported" messsage
                        // instead of the form.
                        // @todo: optional 'template' based form lookups can be hooked in here.
                        $parentDn = $sysDetails['parentdn'];
                        $serverEntry->setProperty('emstype', EMSObject::TYPE_SERVER);

                        // get available (core) services for the server
                        $this->view->coreServices = $serverEntry->getAvailableCoreServices($parentDn);
                        $this->view->probeId = $probeid;
                        $this->_session->addsystem[$probeid]['systemDetails'] = $systemDetails;
                        $this->_session->addsystem[$probeid]['distroDetails'] = $distroDetails;
                    }

                    // Assign probe details to view.
                    $this->view->distroDetails = $distroDetails;
                    $this->view->systemDetails = $systemDetails;
                    $this->render('addserver/linuxproberesults');

                } else {
                    throw new Zivios_Error('Invalid / missing data in request.');
                }
            } else {
                throw new Zivios_Error('Invalid / missing data in request.');
            }
        }
    }

    public function doaddlinuxserverAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($probeid = $this->_request->getParam('probeid'))) {
            throw new Zivios_Error('Probe ID absent from request.');
        }

        if (isset($this->_session->addsystem) && is_array($this->_session->addsystem) &&
            array_key_exists($probeid, $this->_session->addsystem)) {

            // get distro and system details.
            $allSysDetails = $this->_session->addsystem[$probeid];
            $distroDetails = $allSysDetails['distroDetails'];
            $systemDetails = $allSysDetails['systemDetails'];

            $distro     = ucfirst(strtolower($distroDetails['distro']));
            $codename   = ucfirst(strtolower($distroDetails['codename']));
            $release    = $distroDetails['release'];
            $distrodesc = $distro . ' ' . $codename . '-' . $release;
            $distrorel  = $codename . '-' . $release;
            
            // root user login & pass.
            $user = $allSysDetails['user'];
            $pass = Zivios_Security::decrypt($allSysDetails['password']);
            
        } else {
            throw new Zivios_Error('Invalid / missing data in request.');
        }
        
        /*
        Zivios_Log::debug($_POST);
        Zivios_Log::debug($allSysDetails);
        Zivios_Log::debug($systemDetails);
        Zivios_Log::debug($distroDetails);
        */
        //$serviceSubForm = $_POST['linuxserviceform'];
        //$serviceOptions = $serviceSubForm['serviceoptions'];

        $formData = $this->processForm('zvaddserverdata');
        $parentDn = $allSysDetails['parentdn'];
        $parentOu = Zivios_Ldap_Cache::loadDn($parentDn);
        
        // initialize new distribution specific linux comp object and set required params.
        $distroClass = ucfirst(strtolower($distroDetails['distro']));
        $distroClass = 'Linux_' . $distroClass;
        $serverEntry = new $distroClass();
        $serverEntry->init();

        // set all server details.
        $serverEntry->setProperty('iphostnumber',             $allSysDetails['iphost']);
        $serverEntry->setProperty('cn',                       $systemDetails['hostname']);
        $serverEntry->setProperty('emstype',                  EMSObject::TYPE_SERVER);
        $serverEntry->setProperty('emscomputersystem',        'Linux');
        $serverEntry->setProperty('emscomputerdistro',        $distro);
        $serverEntry->setProperty('emsdistrocodename',        $codename);
        $serverEntry->setProperty('emscomputerdistrorelease', $distrorel);
        $serverEntry->setProperty('emsdistrodesc',            $distrodesc);
        $serverEntry->setProperty('emscomputercpumhz',        $systemDetails['cpumhz']);
        $serverEntry->setProperty('emscomputerarch',          $systemDetails['arch']);
        $serverEntry->setProperty('emscomputercpucount',      $systemDetails['cpucount']);
        $serverEntry->setProperty('emscomputerram',           $systemDetails['ram']);
        $serverEntry->setProperty('emscomputerswap',          $systemDetails['swap']);
        $serverEntry->setProperty('emscomputervendormodel',   $systemDetails['cpu']);

        // create transaction group for server addition
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding a Linux Computer.');
        $tgroup = $handler->newGroup('Adding a Linux Computer',Zivios_Transaction_Group::EM_SEQUENTIAL);

        // add server to defined parent.
        $serverEntry->add($parentOu, $tgroup);

        // Add the required plugin for CA.
        // @todo: front-end should allow specification of CA server -- in-turn
        // enabling subordinate CAs to generate & sign cert requests.
        $caPlugin  = $serverEntry->newPlugin('CaComputer');
        $caService = $caPlugin->getMasterService();
        $caService->init();
        $caPlugin->linkToService($caService);

        // Register plugin add to transaction group.
        $serverEntry->addPlugin($caPlugin, $tgroup);
        
        // Register certificate generation
   		$capabilities = array('https-server');
		$props = array();
		$props['pubfilename'] = $serverEntry->getProperty('cn') . '.crt';
		$props['prvfilename'] = $serverEntry->getProperty('cn') . '.key';
		$props['subject']     = $serverEntry->getdn();
		$props['hostname']    = $serverEntry->getProperty('cn');
		$props['certtype']    = 'service'; 
        
        // Using magic function wrapper for transaction based execution.
        $caService->_genCert($tgroup, 'Generating host certificate for new Linux server', $capabilities, $props);
        $caPlugin->_scpCertsToHost($tgroup, 'Securing copying certificate files to new Linux system', $user, $pass);
		$serverEntry->_configureZiviosAgent($tgroup, 'Generating Zivios agent configuration file.', $user, $pass);
		$serverEntry->_restartZiviosAgent($tgroup, 'Restarting Zivios agent on remote server.', $user, $pass);

        // Add package management plugin to Linux server being added.
        $appConfig = Zend_Registry::get('ldapConfig');
        $basedn = $appConfig->basedn;
        $pkgPlugin = $serverEntry->getPkgManagerLib();
        $pkgPlugin = $serverEntry->newPlugin($pkgPlugin);

        $srv = Zivios_Ldap_Cache::loadDn('cn=Zivios Package Service,ou=master services,ou=core control,ou=zivios,'
            . $basedn);

        $pkgPlugin->linkToService($srv);
        $serverEntry->addPlugin($pkgPlugin, $tgroup);

        // add core services where replica subscription is possible
        $ldapProvider = Zivios_Ldap_Cache::loadDn($formData['ldapprovider']);
        $dnsProvider  = Zivios_Ldap_Cache::loadDn($formData['dnsprovider']);
        $krbProvider  = Zivios_Ldap_Cache::loadDn($formData['krbprovider']);
        $ntpProvider  = Zivios_Ldap_Cache::loadDn($formData['ntpprovider']);

        $ldapPlugin = $serverEntry->newPlugin('OpenldapComputer');
        $krbPlugin  = $serverEntry->newPlugin('KerberosComputer');
        $ntpPlugin  = $serverEntry->newPlugin('NtpComputer');
        $dnsPlugin  = $serverEntry->newPlugin('DnsComputer');

        $ldapPlugin->linkToService($ldapProvider);
        $serverEntry->addPlugin($ldapPlugin, $tgroup);

        $krbPlugin->linkToService($krbProvider);
        $serverEntry->addPlugin($krbPlugin, $tgroup);
        
        $ntpPlugin->linkToService($ntpProvider);
        $serverEntry->addPlugin($ntpPlugin, $tgroup);

        $dnsPlugin->linkToService($dnsProvider);
        $serverEntry->addPlugin($dnsPlugin, $tgroup);
        
        // Check all services the client selected for initialization and create transaction items accordingly.
        /*
        foreach ($serviceOptions as $service => $option) {
            if (!strstr($service, '__')) {
                // module.
                if ($serviceOptions[$service] == 1) {
                    $serviceSelect = $service . '__masterService';
                    if (!isset($serviceOptions[$serviceSelect])) {
                        Zivios_Log::error('Possible Bug::Accompanying service may be incorrectly defined in form generation.');
                        throw new Zivios_Error('Could not find service selection for module.');
                    } else {
                        $serviceDn = urldecode($serviceOptions[$serviceSelect]);
                        Zivios_Log::debug('Service Enabled: ' . $service);
                        Zivios_Log::debug('Service DN select: ' . $serviceDn);
                        $pluginClass     = ucfirst(strtolower($service)) . 'Computer';
                        $plugin          = $serverEntry->newPlugin($pluginClass);
                        $serviceInstance = Zivios_Ldap_Cache::loadDn($serviceDn);
                        $plugin->linkToService($serviceInstance);
                        $serverEntry->addPlugin($plugin, $tgroup);
                    }
                }
            }
        }
        */

        // Ready to process transaction. A rollback would be imperative in case of failure.
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            // Delete probeId from session & redirect to server view.
            $this->refreshTreeNode($parentDn);
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                               'default/server/view/dn/'.$serverEntry->getdn()));
            $this->addNotify('Linux Server added successfully.');
        } else {
            // Forceful roleback?
            throw new Zivios_Error('Error adding Linux server. Please check Zivios logs.');
        }

        // Send response to front-end.
        $this->sendResponse();
    }
}

