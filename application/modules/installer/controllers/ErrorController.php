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
 * @package     ZiviosInstaller
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class ErrorController extends Zivios_Install_Controller
{
    protected function _init()
    {}

    public function errorAction()
    {
        $renderHTMLError = false;

        if(!$this->_request->isXmlHttpRequest()) {
            $renderHTMLError = true;
        } else {
            $this->_helper->layout->disableLayout(true);
            $this->_helper->viewRenderer->setNoRender();    
        }
        
        $errors = $this->_getParam('error_handler');

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

                    Zivios_Log::error('Error message: '  . $exception->getMessage());
                    Zivios_Log::error('Offending File: ' . $exception->getFile());
                    Zivios_Log::error('Line Number: '    . $exception->getLine());
                    Zivios_Log::error('Stack Trace: '    . $exception->getTraceAsString());                    
                } else {
                    Zivios_Log::error("System exception registered: " . get_class($exception));
                    $zvExp = array();
                    $zvExp['sysExp' ] = 1;
                    $zvExp['message'] = $exception->getMessage();
                    $zvExp['file'   ] = $exception->getFile();
                    $zvExp['line'   ] = $exception->getLine();
                    $zvExp['stack'  ] = $exception->getTraceAsString();

                    Zivios_Log::error('Error message: '  . $exception->getMessage());
                    Zivios_Log::error('Offending File: ' . $exception->getFile());
                    Zivios_Log::error('Line Number: '    . $exception->getLine());
                    Zivios_Log::error('Stack Trace: '    . $exception->getTraceAsString());                    
                }
         }

        if ($zvErr !== null) {
            $content = Zend_Json::encode($zvErr);
            if ($renderHTMLError) {
                $this->view->message = $zvErr['message'];
            }
        } else {
            $content = Zend_Json::encode($zvExp);
            if ($renderHTMLError) {
                $this->view->message = $zvExp['message'];
            }
        }
        
        if (!$renderHTMLError) {
            $this->_response->clearBody();
            $this->_response->appendBody($content);
        } else {
            $this->render();
        }
    }
 }

