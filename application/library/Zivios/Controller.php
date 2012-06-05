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

abstract class Zivios_Controller extends Zend_Controller_Action
{
    protected $_controller, $_module, $_action, $_session, $_request;
    public    $_callbacks, $_divdata, $_sysnotify;

    final public function init()
    {
        $this->_request = $this->getRequest();

        Zivios_Log::debug('Request Information: Module => ' . $this->_request->getModuleName() . 
            ' | Controller => ' . $this->_request->getControllerName() . ' | Action => ' . 
            $this->_request->getActionName());

        $this->_controller = $this->_request->getControllerName();
        $this->_module     = $this->_request->getModuleName();
        $this->_action     = $this->_request->getActionName();

        $this->_session = Zend_Registry::get('userSession');
        $this->_session->transinprocess = 0;
        unset($this->_session->transinprocess);

        $this->_callbacks = array();
        $this->_divdata = array();
        $this->_sysnotify = array();
        $this->_init();
    }

    /**
     * The preDisptach override ensures that all controllers which extend the
     * Zivios_Controller automatically go through Zivios authentication. The
     * preDisptach action will hence forward the request to the Authentication
     * module if a valid session is not found.
     */
    public function preDispatch()
    {
        if (!isset($this->_session->auth) || $this->_session->auth != 1) {
            Zivios_Log::debug('Expired / unauthenticated session. Redirecting.');

            if ('' == WEB_ROOT) {
                $webroot = '/';
            } else {
                $webroot = WEB_ROOT;
            }

            if($this->_request->isXmlHttpRequest()) {
                Zivios_Log::debug('XHR request detected.');
                // Check if a xhr response type header was sent.
                if (null === ($errRequestHandler = $this->_request->getHeader('XHR_ERROR_HANDLEAS'))) {
                    $errRequestHandler = 'text';
                }

                switch ($errRequestHandler) {
                    case "json" :
                        Zivios_Log::debug('Getting set to flush response.');
                        $this->addCallback('zivios.redirectUrl', array($webroot));
                        $this->addNotify('Your session has expired, redirecting...');
                        $this->flushResponse();
                        break;
                    default:
                        Zivios_Log::debug('Forwarding xhr request to auth controller redirect.');
                        $this->_forward('redirecttologin','auth','default');
                        break;
                }

            } else {
                // A header redirect will do.
                Zivios_Log::debug('Forwarding request to auth controller login');
                $this->_forward('index','auth','default');
            }

            return;
        } else {
            // refresh session data.
            // @todo: additional security checks.
            $securityConfig = Zend_Registry::get('securityConfig');
            $this->_session->setExpirationSeconds($securityConfig->inactivity_timeout, 'auth');
        }
    }

    /**
     * As we have taken over the init call and declared it as a final
     * function, we hook an _init() call for classes extending the
     * controller
     */
    abstract protected function _init();

    protected function iniDashboardLoader($serviceEntry)
    {
        $modelClass = $serviceEntry->getProperty('emsmodelclass');
        $modelClassType = substr($modelClass, -14);

        if ($modelClassType == 'ReplicaService') {
            $this->render('replicadashboard');
        } else {
            $this->render();
        }
    }

    protected function addCallback($function,$args)
    {
        $this->_callbacks[] = array ('func' => $function, 'args' => $args);
    }

    protected function refreshTreeNode($dn)
    {
        $this->addCallBack('zivios.refreshTreeNode',array($dn));
    }

    protected function refreshPane($paneid, $href=null)
    {
        if ($href !== null) {
            $this->addCallback('zivios.cpaneRefresh', array($paneid, $href));
        } else {
            $this->addCallback('zivios.cpaneRefresh', array($paneid));
        }
    }

    protected function closeTab($tabhandlerid,$tabid)
    {
        $this->addCallBack('zivios.closeTab',array($tabhandlerid,$tabid));
    }

    protected function addNotify($message)
    {
        $this->_sysnotify = $message;
    }

    protected function addDivData($div,$content,$append=false)
    {
        if (!isset($append) || $append=='') {
            $this->_divdata[] = array ('div' => $div, 'content' => $content);
        } else {
            $this->_divdata[] = array ('div' => $div, 'content' => $content, 'append' => $append);
        }
    }

    protected function getResponseData()
    {
        $responseData = array();

        if (!empty($this->_callbacks)) {
            $responseData['callbacks'] = $this->_callbacks;
        }

        if (!empty($this->_sysnotify)) {
            $responseData['sysNotify'] = $this->_sysnotify;
        }

        if (!empty($this->_divdata)) {
            $responseData['divdata'] = $this->_divdata;
        }

        return $responseData;
    }

    /**
     * Function appends data to response object.
     *
     * @return json
     */
    protected function sendResponse($return=0)
    {
        $responseData = $this->getResponseData();
        $content = Zend_Json::encode($responseData);
        Zivios_Log::debug($content);
        if ($return)
            return $content;
        else
            $this->_response->appendBody($content);
    }

    /**
     * Function sends response back to client instantly and exits.
     *
     * @return json
     */
    protected function flushResponse()
    {
        $responseData = $this->getResponseData();
        $content = Zend_Json::encode($responseData);
        echo $content;
        exit();
    }

    protected function _getForm($formname,$section='default')
    {
        $formconfigdir = APPLICATION_PATH . '/modules/' . $this->_module . '/config/Forms';
        $formini = new Zend_Config_Ini($formconfigdir . '/'.$formname.'.ini',$section);
        return new Zend_Dojo_Form($formini);
    }

    protected function getParam($paramname)
    {
        return $this->_request->getParam($paramname);
    }

    protected function processTransaction(Zivios_Transaction_Handler $transaction)
    {
        if (sizeof($transaction->getAllGroups()) < 1) {
            $this->addNotify('No groups found in transaction handler. This <b>does not</b> mean' .
                ' an error has occurred; simply, your request did not warrant any action take place.');

            $this->flushResponse();

        } else {
            $appConfig = Zend_Registry::get('appConfig');
            $transConfig = Zend_Registry::get('transactionConfig');

            $approot = APPLICATION_PATH;
            $alwaysdefer = $transConfig->deferalltransactions;

            if ($transaction->getRefreshDn() == null || $transaction->getRefreshDn() == '') {
                //$transaction->onSuccessRefreshDn($this->_requestoperate_dn);
            }

            if ($transaction->isDeferred() || $alwaysdefer) {
                $transaction->commit();
                Zivios_Log::debug("Deferring Transaction execution for id :".$transaction->getId());
                $cmd = $approot ."/scripts/runTransaction.sh ". $transaction->getId() . " >/tmp/transdebug.log &";
                Zivios_Log::info("Defer Command : ".$cmd);
                system($cmd);
                return 0;
            } else {
                try {
                    $error = null;
                    $transaction->process();
                } catch (Exception $e) {
                    $error = $e;
                    Zivios_Log::exception($e);
                }

                $status = $transaction->getStatus();

                if ($status == Zivios_Transaction_Handler::STATUS_FAILED) {
                    $sysnot = '<span id="transnotif'.$transaction->getId().'">'
                    . $transaction->getLastExceptionMessage().' : Transaction ' 
                        . $transaction->getId() . ':::'.$transaction->getDescription()
                        . '<font color="red"> Failed </font> 
                        <!--a href="#"onClick="zivios.callAction(\'/transaction/showtransaction\',{ id: '
                        . $transaction->getId().'},\'transnotif'
                        . $transaction->getId().'\')">Click Here </a> for details --> </span>';

                } else if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                    $sysnot = '<span id="transnotif'.$transaction->getId().'">Transaction '
                        . $transaction->getId() . ':::'.$transaction->getDescription() . 
                        ' <font color="green"> Successful </font>
                        <!-- a href="#" onClick="zivios.callAction(\'/transaction/showtransaction\',{ id: '
                        .$transaction->getId().'},\'transnotif'.$transaction->getId().'\')">'
                        .'Click Here </a> for details --> </span>' ;

                    Zivios_Log::debug('Notification send: ' . $sysnot);

                } else if ($status == Zivios_Transaction_Handler::STATUS_PARTIAL) {

                    $sysnot = '<span id="transnotif'.$transaction->getId().'">Transaction '
                        . $transaction->getId() . ':::'.$transaction->getDescription()
                        . ' <font color="orange"> Partially Successful </font>
                        <!-- a href="#" onClick="zivios.callAction(\'/transaction/showtransaction\',{ id: '
                        . $transaction->getId().'}, \'transnotif'.$transaction->getId().'\')">'
                        . 'Click Here </a> for details --> </span>' ;
                } else {
                    $sysnot = 'Unknown Error';
                }

                $this->addNotify($sysnot);
                return $status;
            }
        }
    }

    public function requireAgent(EMSService $service,$shortmsg)
    {
        if($service->pingZiviosAgent()) {
            return true;
        } else {
            Zivios_Log::debug("Agent is offline, forwarding request.");
            $this->_forward('agentoffline','error', 'default', 
                array('hostdn' => $service->getMasterComputer()->getdn(),
                                 'shortmsg' => $shortmsg));
            return false;
        }
    }
    
    public function indexAction()
    {}
    
    protected function _getRegexLibrary()
    {
        if (null === $this->_regexLib) {
            $this->_regexLib = Zivios_Regex::loadLibrary();
        }

        return $this->_regexLib;
    }
    
    
    protected function processForm($formId)
    {
        if (!Zivios_Util::isFormPost($formId)) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST[$formId]);

        if ($formData->err !== false) {

            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
            $this->flushResponse();
        }

        return $formData->cleanValues;
    }
}

