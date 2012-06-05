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
class Rpc_SmdController extends Zivios_Controller
{
    protected function _init()
    {}

    public function iniAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $module = $this->_getParam('m');
        $class  = $this->_getParam('c');

        Zivios_Log::debug("Module: " . $module);
        Zivios_Log::debug("Class: " . $class);
        
        $server = new Zend_Json_Server();
        $server->getServiceMap()->setTransport('POST')
                                ->setEnvelope(Zend_Json_Server_Smd::ENV_JSONRPC_2)
                                ->setDojoCompatible(true)
                                ->setTarget($this->getHelper('url')->url(array('module' => 'rpc', 'controller'=>'smd', 'action'=>'service')))
                                ->setId($this->getHelper('url')->url(array('module' => 'rpc', 'controller'=>'smd', 'action'=>'service')));
 
        $server->setClass($class);
        $smd = $server->getServiceMap();
        header('Content-Type: application/json');
        echo $smd;
        return;
    }

    public function serviceAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $class = $this->_getParam('c');
        $server = new Zend_Json_Server();
        $server->setClass($class);
        $server->setAutoEmitResponse(true);
        $server->handle();
    }
}
