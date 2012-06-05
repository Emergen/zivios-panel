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
 * @package		mod_installer
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class IndexController extends Zivios_Install_Controller
{
    protected $_installer, $_session;
    protected function _init() {}

    public function preDispatch()
    {
        // Ensure the session is valid.
        $installer = $this->getModel();

        if (!$installer->checkInstallSession()) {
            throw new Zivios_Error("An Install session is already in progress." . 
            " To reset the install session, remove all files in the folder: <ul>" .
            " <li/>/opt/zivios/zivios-panel/application/status/</ul>");
        }
        
        // retrieve install session
        $this->_session = Zend_Registry::get('installSession');
        
        // check for existing installation session
        if(!$this->_request->isXmlHttpRequest()) {
            $nextStepDetails = $installer->getNextStep();
            if ($nextStepDetails['nextActionId'] > 2) {
                $this->_helper->layout()->resumeInstall = true;
                $this->_helper->layout()->nextAction = $nextStepDetails['nextAction'];
            }
        } else {
            $this->_helper->layout->disableLayout(true);
        }
    }
    
    public function indexAction()
    {}

    public function mainAction()
    {}

    public function probeserverAction()
    {
        $installer = $this->getModel();
        $this->view->probeResults = $installer->runLocalSystemTests();
    }

    public function initializedbAction()
    {}

    public function processinidbAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        
        $formData = $this->processForm('dbsetup');

        // process DB setup
        $installer = $this->getModel();
        $installer->runDbSetup($formData->cleanValues);
        $this->setActionSuccess('Database initialized successfully.');

        // define callback to load next page.
        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/initializeca", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);

        // send response
        $this->sendResponse();
    }

    public function initializecaAction()
    {
        $this->view->localSysInfo = $this->_session->localSysInfo;
    }

    public function processinicaAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $formData = $this->processForm('casetup');

        $installer = $this->getModel();
        $installer->runCaSetup($formData->cleanValues);
        
        // set success action & callback
        $this->setActionSuccess('Certificate Authority initialized successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/initializewebssl", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }

    public function initializewebsslAction()
    {
        $this->view->localSysInfo = $this->_session->localSysInfo;
    }

    public function processiniwebsslAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        
        // process form data
        $formData = $this->processForm('websslsetup');

        $installer = $this->getModel();
        $installer->initializeWebssl();
        
        $this->setActionSuccess('Web SSL initialized successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/restartapache", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }

    public function restartapacheAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
            $installer = $this->getModel();
            $installer->sslInitialized();
            $nextStepDetails = $installer->getNextStep();
            $this->_forward($nextStepDetails['nextAction'], 'index', 'installer');
        } else {
            $this->render();
        }
    }

    public function initializeldapAction()
    {
        $this->view->localSysInfo = $this->_session->localSysInfo;
    }

    public function processinildapAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        
        // process form data
        $formData = $this->processForm('ldapsetup');

        $installer = $this->getModel();
        $installer->runLdapSetup($formData->cleanValues);
        
        $this->setActionSuccess('Directory Service initialized successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/initializekrb5", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }


    public function initializekrb5Action()
    {
        $this->view->localSysInfo = $this->_session->localSysInfo;
    }

    public function processinikrb5Action()
    {
        $this->_helper->viewRenderer->setNoRender();
        
        $formData = $this->processForm('krb5setup');
        $installer = $this->getModel();
        $installer->runKerberosSetup($formData->cleanValues);

        $this->setActionSuccess('Heimdal Kerberos Service initialized successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/initializebind", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }

    public function initializebindAction()
    {
        $this->view->localSysInfo = $this->_session->localSysInfo;

    }

    public function processinibindAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $formData = $this->processForm('bindsetup');
        $installer = $this->getModel();
        $installer->runBindSetup($formData->cleanValues);

        $this->setActionSuccess('Bind DNS initialized successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/initializezv", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }

    public function initializezvAction()
    {}

    public function processinizvAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        
        $formData = $this->processForm('zvconfig');

        $installer = $this->getModel();
        $installer->runZiviosSetup($formData->cleanValues);

        $this->setActionSuccess('Zivios configuration completed successfully.');

        $call = 'installer.getxhr';
        $args = array(
            "/installer/index/welcomescreen", "primaryContent", "", "text", "",
            "['loadingmsg']", "['loadingmsg']", "", "true", "false"
        );

        $this->addCallback($call, $args);
        $this->sendResponse();
    }

    public function finalizeinstallAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($_POST['zvconfig']) || !is_array($_POST['zvconfig'])) {
            throw new Zivios_Exception("Could not find form data in request.");
        }

        $installer = $this->getModel();
        $installer->runZiviosSetup($_POST['zvconfig']);

        $content = array('loadaction' => '/index/welcomescreen');
        $content = Zend_Json::encode(array('content' => $content));
        $this->_response->appendBody($content);
    }

    public function welcomescreenAction()
    {
        $installer = $this->getModel();
        $installer->writeOptimalStamp();
    }

    public function getconsoledataAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $model = $this->getModel();
        $cdata = $model->getConsoleData();
        $cdata = (string) $cdata;

        // Return response to caller
        $this->_response->appendBody($cdata);
        return;
    }

    public function getModel()
    {
        if (null === $this->_installer) {
            $this->_installer = new Installer();
        }
        
        return $this->_installer;
    }
    
    protected function _getForm($formName)
    {
        $installer = $this->getModel();
        $osDetails = $installer->getOsDetails();
        $release   = $osDetails['release'];

        $skippers = Array('Ca', 'Bind', 'Webssl', 'Bind', 'Kerberos');

        if ((strtolower($release) == 'unsupported') && in_array($formName, $skippers))
            $formName = $formName . '_Generic';

        $form = $this->getModel()->getForm($formName);
        return $form;
    }

    /**
     * Process a form found in global _POST array.
     *
     * @param string $formId (must exist in _POST array)
     * @return Zivios_ValidateForm $formData
     */
    private function processForm($formId)
    {
        if (!Zivios_Util::isFormPost($formId)) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST[$formId]);

        if ($formData->err !== false) {

            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            $this->setActionFailure(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
            $this->flushResponse();
        }

        return $formData;
    }
}
