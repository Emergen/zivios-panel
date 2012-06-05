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
 * @subpackage  Installer
 **/

abstract class Zivios_Install_Controller extends Zend_Controller_Action
{
    protected $_controller, $_module, $_action, $_request,
              $_callbacks = array(), $_xhrRes = array(),
              $_divData = array();

    final public function init()
    {
        // Log request information
        $this->_request = $this->getRequest();
        Zivios_Log::debug("Request Information: Module => "     . $this->_request->getModuleName());
        Zivios_Log::debug("Request Information: Controller => " . $this->_request->getControllerName());
        Zivios_Log::debug("Request Information: Action => "     . $this->_request->getActionName());

        $this->_controller = $this->_request->getControllerName();
        $this->_module     = $this->_request->getModuleName();
        $this->_action     = $this->_request->getActionName();
        
        $this->_init();
    }

    /**
     * As we have taken over the init call and declared it as a final
     * function, we hook an _init() call for classes extending the
     * controller
     */
    abstract protected function _init();

    public function index()
    {}

    protected function addCallback($function, $args=array())
    {
        if (!is_array($args)) {
            $args = array($args);
        }

        $this->_callbacks[] = array('func' => $function, 'args' => $args);
    }

    protected function addDivUpdate($div, $content, $append=false)
    {
        if (!isset($append) || $append === false) {
            $this->_divData[] = array ('div' => $div, 'content' => $content);
        } else {
            $this->_divData[] = array ('div' => $div, 'content' => $content, 'append' => $append);
        }
    }
    
    /**
     * Every xhr response object requires to be marked as successful or failed via the following
     * functions. If a message pop-up is not required, pass "null" as the msg.
     *
     * @param string|null msg
     * @return void
     */
    protected function setActionSuccess($msg='Your request was processed successfully.')
    {
        $this->_xhrRes['success'] = 1;
        if (null !== $msg) {
            $this->_xhrRes['msg'] = strip_tags(trim($msg));
        } else {
            // ensure no msg exists.
            if (isset($this->_xhrRes['msg'])) {
                unset($this->_xhrRes['msg']);
            }
        }
    }
    
    /**
     * Accompanying function for response success/failure. 
     * 
     * @return void
     */
    protected function setActionFailure($msg='Your request could not be processed.')
    {
        if (isset($this->_xhrRes['success'])) {
            unset($this->_xhrRes['success']);
        }
        
        if (null !== $msg) {
            $this->_xhrRes['msg'] = strip_tags(trim($msg));
        } else {
            if (isset($this->_xhrRes['msg'])) {
                unset($this->_xhrRes['msg']);
            }
        }
    }
    
    /**
     * Test if the response object has a message.
     *
     * @return boolean
     */
    protected function checkResponseMessage()
    {
        if (isset($this->_xhrRes['msg'])) {
            return true;
        }

        return false;
    }

    protected function prepareResponseData()
    {
        if (!empty($this->_callbacks)) {
            $this->_xhrRes['callbacks'] = $this->_callbacks;
        }

        if (!empty($this->_divData)) {
            $this->_xhrRes['divdata'] = $this->_divdata;
        }
    }

    protected function sendResponse($ret=0)
    {
        $this->prepareResponseData();

        if ($ret == 1) {
            return Zend_Json::encode($this->_xhrRes);
        } else {
            $this->_response->appendBody(Zend_Json::encode($this->_xhrRes));
        }
    }

    /**
     * Immediately send the response object created.
     *
     * @return JSON
     */
    protected function flushResponse()
    {
        $this->prepareResponseData();
        
        // Send response object to client
        echo Zend_Json::encode($this->_xhrRes);

        // ensure all request processing ends here.
        exit();
    }
}

