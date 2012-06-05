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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class ErrorController extends Zend_Controller_Action
{
    protected $_errRequestHandler = null;
    protected function _init()
    {}

    public function errorAction()
    {
    	Zivios_Log::error("Exception happened");
        if (null === ($this->_errRequestHandler = $this->_request->getHeader('XHR_ERROR_HANDLEAS'))) {
            $this->_errRequestHandler = 'json';
        }
        

        $errors = $this->_getParam('error_handler');
        Zivios_Log::exception($errors->exception);
        $zvErr  = null;
        $zvExp  = null;

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
            //404 Error. Requested Controller or Action not found.
            $this->getResponse()->setHttpResponseCode(404);
            $zvErr = array();
            $zvErr['sysErr']        = 1;
            $zvErr['message']       = '404 Error. Requested action/controller not found.';
            $zvErr['file']          = 'NA';
            $zvErr['line']          = 'NA';
            $zvErr['stack']         = 'NA';
            break;

            default:
            $exception = $errors->exception;
            if ($exception instanceof Zivios_Error) {
                Zivios_Log::error("System error registered.");
                $zvErr = array();
                $zvErr['sysErr' ] = 1;
                $zvErr['message'] = $exception->getMessage();
                $zvErr['file'   ] = $exception->getFile();
                $zvErr['line'   ] = $exception->getLine();
                $zvErr['stack'  ] = $exception->getTraceAsString();
            } else {
                Zivios_Log::error("System exception registered: " . get_class($exception));
                $zvExp = array();
                $zvExp['sysExp' ] = 1;
                $zvExp['message'] = $exception->getMessage();
                $zvExp['file'   ] = $exception->getFile();
                $zvExp['line'   ] = $exception->getLine();
                $zvExp['stack'  ] = $exception->getTraceAsString();
            }

            Zivios_Log::error('Error message: '  . $exception->getMessage());
            Zivios_Log::error('Offending File: ' . $exception->getFile());
            Zivios_Log::error('Line Number: '    . $exception->getLine());
            Zivios_Log::error('Stack Trace: '    . $exception->getTraceAsString());
        }

        /**
         * All xhr requests specify the context of expected output via additional
         * headers sent. We hence ensure that the ouput of error messages is handled
         * appropriately.
         */
         switch ($this->_errRequestHandler) {
            case "json" : 
            Zivios_Log::debug('error handler output requested: json');
            $this->_helper->layout->disableLayout(true);
            $this->_helper->viewRenderer->setNoRender();

            if ($zvErr !== null) {
                $content = Zend_Json::encode($zvErr);
            } else {
                $content = Zend_Json::encode($zvExp);
            }
            
            Zivios_Log::debug_r($content);

            $this->_response->clearBody();
            $this->_response->appendBody($content);

            break;
            
            default : 
            Zivios_Log::debug('error handler output requested: text');
            $this->_helper->layout->disableLayout(true);
            $this->view->code = 500;

            if ($zvErr !== null) {
                $this->view->errInfo = $zvErr;
            } else {
                $this->view->errInfo = $zvExp;
            }
         }
    }
    
    public function agentofflineAction()
    {
        Zivios_Log::debug("Rendering Agent offline template");
        $this->_helper->layout->disableLayout(true);
        $this->view->hostdn = $this->_getParam('hostdn');
        $this->view->shortmsg = $this->_getParam('shortmsg');
    }
}

