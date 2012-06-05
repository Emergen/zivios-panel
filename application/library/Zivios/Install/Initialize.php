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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Zivios_Install_Initialize extends Zend_Controller_Plugin_Abstract
{
    protected $config, $registry;

    public function __construct()
    {
        $this->initConfig();
        date_default_timezone_set($this->config->phpenv->timezone);
        $this->front = Zend_Controller_Front::getInstance();
    }

    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        $this->initModules()
            ->initRegistryAndLog()
            ->initPhp()
            ->initSession()
            ->initView();
    }

    public function initPhp()
    {
        // Setup PHP Environment.
        Zivios_Log::debug('Initializing php environment settings.');
        ini_set('display_errors', $this->config->phpenv->displayerrors);
        error_reporting((int) $this->config->phpenv->reporting);
        Zivios_Log::debug('PHP environment settings initialized succcessfully.');
        return $this;
    }

    /**
     * Initialize Session
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initSession()
    {
        Zivios_Log::debug("Initializing session.");
        Zend_Session::setOptions($this->config->session->toArray());
        Zend_Session::start();
        $installSession = new Zend_Session_Namespace('ZIVIOS_INSTALL');
        $this->registry->set('installSession', $installSession);
        Zivios_Log::debug("Session initialized successfully.");
        return $this;
    }

    /**
     * Assign configuration objects to Registry
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initRegistryAndLog()
    {
        // Initialize Install Log writer
        $writer = new Zend_Log_Writer_Stream($this->config->log->logfile);
        $logger = new Zend_Log($writer);

        // Initialize Console Log writer
        $cwriter = new Zend_Log_Writer_Stream($this->config->log->consolelog);
        $clogger = new Zend_Log($cwriter);

        $this->registry = new Zend_Registry(array(), ArrayObject::ARRAY_AS_PROPS);
        Zend_Registry::setInstance($this->registry);
        $this->registry->set('appConfig', $this->config->general);
        $this->registry->set('viewConfig', $this->config->view);
        $this->registry->set('layoutConfig', $this->config->layout);
        $this->registry->set('webConfig', $this->config->web);
        $this->registry->set('logger', $logger);
        $this->registry->set('clogger', $clogger);
        $this->registry->set('logConfig',$this->config->log);

        Zivios_Log::debug("Initialized Zivios Log.");
        Zivios_Log::debug("Initialized system runtime registry.");
        return $this;
    }

    public function initConfig()
    {
        $this->config = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini');
        return $this;
    }

    /**
     * Initialize modules and add model class directories to the default
     * include path.
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initModules()
    {
        $this->front->addModuleDirectory($this->config->general->modules);

        // Ensure the default route is set to the installer module.
        $this->front->setDefaultModule('installer');

        // We selectively add the installer module's models directory
        // to the include path.
        initLibraryPaths($this->config->general->modules, $modules = array('installer'));
        return $this;
    }

    public function initView()
    {
        $view = new Zend_View;
        $view->addHelperPath('Zivios/Install/View/Helper/', 'Zivios_Install_View_Helper');

        Zend_Dojo::enableView($view);
        Zend_Dojo_View_Helper_Dojo::setUseDeclarative(true);

        $view->baseUrl = rtrim($this->getRequest()->getBaseUrl(), '/');
        $view->doctype('XHTML1_TRANSITIONAL');
        $view->headTitle()->setSeparator(' - ')->append('Zivios Installer');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');

        // Simple check to determine whether dojo builds should be used.
        if (isset($this->config->view->dojo->useBuild) && $this->config->view->dojo->useBuild == 'yes') {

            Zivios_Log::debug('Using dojo build.');

            $pathToBuild  = $this->config->web->jsBase . $this->config->view->dojo->buildDirectory . '/' .
                $this->config->view->dojo->buildVersion;

            $pathToDojo  = $pathToBuild . '/dojo/dojo.js';
            $ziviosLayer = $pathToBuild . '/installer/layer.js';
            $modulePath  = $pathToBuild . '/installer';

            Zivios_Log::debug('Dojo build path: '    . $pathToDojo);
            Zivios_Log::debug('Zivios Layer: '       . $ziviosLayer);
            Zivios_Log::debug('Zivios module path: ' . $modulePath);

            $view->css = '@import url(/public/scripts/release/zvinstaller-'.ZVERSION.'/installer/base/base.css);';

            $view->dojo()->setDjConfigOption('isDebug', $this->config->view->dojo->isDebug)
                 ->setDjConfigOption('preventBackButtonFix', false)
                 ->setLocalPath($pathToDojo)
                 ->addLayer($ziviosLayer)
                 ->disable();
        } else {
            // fallback to loading dojo from source.
            Zivios_Log::debug('Dojo build not in use.');

            // set css location in view object
            $view->css = '@import url(/public/scripts/devel/current/installer/base/base.css);';

            $view->dojo()->setDjConfigOption('isDebug', $this->config->view->dojo->isDebug)
                ->setDjConfigOption('preventBackButtonFix', false)
                ->setLocalPath($this->config->web->jsBase . '/devel/current/dojo/dojo.js')
                ->registerModulePath('../installer', 'installer')
                ->requireModule('installer.layer')
                ->disable();
        }

        Zend_Registry::set('view', $view);
        
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $viewRenderer->setView($view);

        $layout = Zend_Layout::startMvc($this->config->layout);
        $layout->baseUrl = $this->config->web->baseUrl;
        $layout->imgBase = $this->config->web->imgBase;
        $layout->jsBase  = $this->config->web->jsBase;

        return $this;
    }
}

