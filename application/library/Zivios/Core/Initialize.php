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
 * @package		Zivios
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Zivios_Core_Initialize extends Zend_Controller_Plugin_Abstract
{
    protected $config, $registry, $layoutKey, $installConfig;
    public    $startts, $modulests, $registryts, $endtimestamp;

    public function __construct()
    {
        
        $this->initConfig();
        $this->initInstallConfig();
        $this->front = Zend_Controller_Front::getInstance();
        
    }

    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        $this->start = microtime(true);
        $this->initModules()
            ->initRegistryAndLog()
            ->initPhp()
            ->initSession()
            ->initView()
            ->initDb();
        $this->endtimestamp = microtime(true);

        Zivios_Log::debug("Module Init : ".($this->modulests-$this->start)*1000);
        Zivios_Log::debug("Registry Init : ".($this->registryts-$this->modulests)*1000);
        Zivios_Log::debug("PHP/SEssion/View/Db Init : ".($this->endtimestamp-$this->registryts)*1000);
        Zivios_Log::debug("Ending Timestamp :".$this->endtimestamp);
    }

    public function initPhp()
    {
        $start = microtime(true);
        //Zivios_Log::debug('Initializing php environment settings.');
        ini_set('display_errors', $this->config->phpenv->displayerrors);
        error_reporting((int) $this->config->phpenv->reporting);
        date_default_timezone_set($this->config->phpenv->timezone);
        //Zivios_Log::debug('PHP environment settings initialized succcessfully.');
        $end = microtime(true);
        Zivios_Log::debug("initPHP took :".($end - $start)*1000);
        return $this;
    }

    /**
     * Initialize Session
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initSession()
    {
        //Zivios_Log::debug('Initializing session.');
        $start = microtime(true);
        Zend_Session::setOptions($this->config->session->toArray());
        Zend_Session::start();

        $session = new Zend_Session_Namespace('userSession');

        // Till we figure out a better way of doing this...
        if (isset($session->auth) && $session->auth == 1 && !$session->pwexpired) {
            // initialize user and check administrative access
            $user = Zivios_Ldap_Cache::loadDn($session->user_dn);

            $adminGroupDn = 'cn=zadmin,ou=zgroups,ou=core control,ou=zivios,' . 
                $this->config->ldap->basedn;

            $admGroup = Zivios_Ldap_Cache::loadDn($adminGroupDn);
            
            if ($user->isMemberOf($admGroup)) {
                $this->layoutKey = $this->config->primarylayout;
                $session->baseAdmin = true;
            } else {
                $this->layoutKey = $this->config->selfservicelayout;
                $session->baseAdmin = false;
            }
        } else {
            $this->layoutKey = $this->config->loginlayout;
        }

        $this->registry->set('userSession', $session);
        $this->registry->set('layoutConfig', $this->layoutKey);

        $end = microtime(true);
        Zivios_Log::debug("initSession took :".($end - $start)*1000);
        //Zivios_Log::debug('Session initialized successfully.');
        return $this;
    }

    /**
     * Assign configuration objects to Registry
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initRegistryAndLog()
    {
        $writer = new Zend_Log_Writer_Stream($this->config->log->logfile);
        $logger = new Zend_Log($writer);
        $this->registry = new Zend_Registry(array(), ArrayObject::ARRAY_AS_PROPS);
        Zend_Registry::setInstance($this->registry);
        
        $this->registry->set('appConfig', $this->config->general);
        $this->registry->set('viewConfig', $this->config->view);
        $this->registry->set('webConfig', $this->config->web);
        $this->registry->set('logger', $logger);
        $this->registry->set('logConfig', $this->config->log);
        $this->registry->set('ldapConfig', $this->config->ldap);
        $this->registry->set('securityConfig', $this->config->security);
        $this->registry->set('cacheConfig', $this->config->cache);
        $this->registry->set('transactionConfig',$this->config->transaction);
        $this->registry->set('casystem',$this->config->casystem);
        $this->registry->set('krbMaster', $this->config->kerberosmaster);
        $this->registry->set('installConfig', $this->installConfig);

        $this->registryts = microtime(true);

        return $this;
    }

    public function initConfig()
    {
        $this->config = new Zend_Config_Ini(APPLICATION_PATH . '/config/app.config.ini');
        return $this;
    }

    public function initInstallConfig()
    {
        $this->installConfig = new Zend_Config_Ini(APPLICATION_PATH . '/config/installer.config.ini');
        return $this;
    }
    

    /**
     * Initialize modules and add model class directories to the default include path.
     *
     * @return Zivios_Install_Initialize $this
     */
    public function initModules()
    {
        
        $this->front->addModuleDirectory($this->config->general->modules);
        initLibraryPaths($this->config->general->modules);
        $this->modulests = microtime(true);
        return $this;
    }

    public function initView()
    {
        $start = microtime(true);
        $view = new Zend_View;

        Zend_Dojo::enableView($view);
        Zend_Dojo_View_Helper_Dojo::setUseDeclarative(true);

        $view->baseUrl = rtrim($this->getRequest()->getBaseUrl(), '/');
        $view->addHelperPath(APPLICATION_PATH . '/library/Zivios/View/helpers','Zivios_View_Helper');
        $view->doctype('XHTML1_STRICT');
        $view->headTitle()->setSeparator(' - ')->append('Zivios');
        $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html; charset=utf-8');

        // Simple check to determine whether dojo builds should be used.
        if (isset($this->config->view->dojo->useBuild) && $this->config->view->dojo->useBuild == 'yes') {
            Zivios_Log::debug('Using dojo build.');
            $pathToBuild  = $this->config->web->jsBase . $this->config->view->dojo->buildDirectory . 
                '/zivios-'.ZVERSION;

            $pathToDojo  = $pathToBuild . '/dojo/dojo.js';
            $ziviosLayer = $pathToBuild . '/zivios/core.js';
            $loaderLayer = $pathToBuild . '/zivios/loader.js';
            $modulePath  = $pathToBuild . '/zivios';

            Zivios_Log::debug('Dojo build path: '    . $pathToDojo);
            Zivios_Log::debug('Zivios Layer: '       . $ziviosLayer);
            Zivios_Log::debug('Zivios module path: ' . $modulePath);

            $view->dojo()->setDjConfigOption('isDebug', $this->config->view->dojo->isDebug)
                     ->setLocalPath($pathToDojo)
                     ->addLayer($loaderLayer)
                     ->addLayer($ziviosLayer)
                     ->registerModulePath($modulePath . '/zivios', 'zivios')
                     ->addStylesheetModule('zivios.base')
                     ->disable();
        } else {
            // fallback to loading dojo from source.
            Zivios_Log::debug('Dojo build not in use.');

           $view->css = '@import url(/public/scripts/devel/current/zivios/base/base.css);';

            $view->dojo()->setDjConfigOption('isDebug', $this->config->view->dojo->isDebug)
                     ->setLocalPath($this->config->web->jsBase . '/devel/current/dojo/dojo.js')
                     ->registerModulePath($this->config->web->jsBase . '/devel/current/zivios', 'zivios')
                     ->requireModule('zivios.core')
                     ->disable();
        }

        Zend_Registry::set('view', $view);

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $viewRenderer->setView($view);
        $layout = Zend_Layout::startMvc($this->layoutKey);

        $view->baseUrl = $this->config->web->baseUrl;
        $view->imgBase = $this->config->web->imgBase;
        $view->jsBase  = $this->config->web->jsBase;
        $view->zvtheme = $this->config->general->ziviosTheme;

        $end = microtime(true);
        Zivios_Log::debug("initView took :".($end - $start) * 1000);
        
        return $this;
    }
    
    public function initDb()
    {
         $start = microtime(true);
         $dbAdapters = array();
         $appDb = $this->config->database;
         $dbAdapters['menu'] = Zend_Db::factory('Pdo_Mysql', array(
                        'host'        => $appDb->host,
                        'username'    => $appDb->username,
                        'password'    => $appDb->password,
                        'dbname'      => $appDb->name,
                        'unix_socket' => $appDb->socket,
                    ));
         
         Zend_Db_Table::setDefaultAdapter($dbAdapters['menu']);
         $end = microtime(true);
         Zivios_Log::debug("initDb took : " . ($end - $start) * 1000);
         return $this;
    }
}

