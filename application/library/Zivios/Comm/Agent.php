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
 * @subpackage  Core
 **/

include_once(APPLICATION_PATH . "/library/xmlrpc/xmlrpc.inc");
include_once(APPLICATION_PATH . "/library/xmlrpc/xmlrpcs.inc");

class Zivios_Comm_Agent
{
    const AGENT_PORT = 7080;

    public  $xmlrpcclient,$plugin;
    private $debug = 0;

    public $connectStatus = null;

    /**
     * Primary constructor for Zivios Agent Communication.
     *
     * @param string  $host
     * @param string  $plugin
     * @param integer $debug
     */
    public function __construct($host, $plugin=null, $debug=0)
    {
        // Get certificate details
        $caSystem = Zend_Registry::get('casystem');
        $this->xmlrpcclient = new xmlrpc_client('/',$host,self::AGENT_PORT);

        $this->xmlrpcclient->setDebug($this->debug);
        $this->plugin = $plugin;
        $credarray = Zivios_Ldap_Util::getUserCreds();

        $this->xmlrpcclient->setCredentials($credarray["dn"],$credarray["password"]);
        $this->xmlrpcclient->setCaCertificate($caSystem->CA_pubkey);
    }

    public function agentStatus()
    {
        if (null === $this->connectStatus) {
            $savePlugin   = $this->plugin;
            $this->plugin = 'zvcore';

            try {
                $this->connectStatus = $this->testConnect();
                $this->plugin = $savePlugin;
                return $this->connectStatus;
            } catch (Exception $e) {
                $this->connectStatus = false;
                $this->plugin = $savePlugin;
                return false;
            }
        } else {
            return $this->connectStatus;
        }
    }

    function __call($method,$args)
    {
        Zivios_Log::debug("Method : $method called");

        if (function_exists($method)) {
            Zivios_Log::debug('existing function ');
            return call_user_func_array($method,$args);

        } else {

            if (isset($this->plugin)) {
                $functocall = $this->plugin.".$method";
            } else  {
                $functocall = $method;
            }

            $xmlrpcvalarray = array();

            if (is_array($args)) {
                $i=0;
                foreach ($args as $arg) {
                    $xmlrpcvalarray[$i++] = php_xmlrpc_encode($arg);
                }
            } else {
                $xmlrpcvalarray[0] = php_xmlrpc_encode($arg);
            }

            $xmlrpc_msg = new xmlrpcmsg($functocall,$xmlrpcvalarray);
            $xmlrpc_resp = $this->xmlrpcclient->send($xmlrpc_msg,0,'https');

            // check for successful transaction
            if ($xmlrpc_resp == false) {
                throw new Zivios_Exception("Zivios_Comm_Agent: No XML RPC Response code recieved.");
            }

            // Add special handling for the SHUTDOWN function (It will return nothing!)
            if (!$xmlrpc_resp->faultCode()) {
                $value = $xmlrpc_resp->value();
                $response = php_xmlrpc_decode($value);
                //Zivios_Log::debug("XML RPC Response is $response");
//                Zivios_Log::debug($response);

                if (is_array($response)) {
                    if (isset($response['errcode'])) {
                        throw new Exception($response['message'], $response['code']);
                    }
                }

                return $response;

            } else {
                Zivios_Log::debug($xmlrpc_resp);
                $e = new Zivios_Comm_Exception("XML RPC Error:  ".$xmlrpc_resp->faultCode().
                    " :::".$xmlrpc_resp->faultString());
                $e->setFaultCode($xmlrpc_resp->faultCode());;
                throw $e;
            }
        }
    }
}
