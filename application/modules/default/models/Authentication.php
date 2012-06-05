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

class Authentication
{
    protected $_session, $_config, $_form;
    public $engine;

    public function __construct()
    {
        $this->_session = Zend_Registry::get('userSession');
        $this->_config  = Zend_Registry::get('appConfig');
    }

    /**
     * Authenticate a user against the directory service. Automatic
     * dn construction is performed with the premise that the user
     * id is unique.
     *
     * @return boolean
     */
    public function authUser($uid, $password)
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $this->engine = new Zivios_Ldap_Engine();
       
        // if the uid is zadmin, we can lookup the dn.
        if ($uid == 'zadmin') {
            $dn = $ldapConfig->admindnprefix . "," . $ldapConfig->basedn;
            Zivios_Log::info("Admin bind with zadmin - using dn " . $dn);
        } else {
             // Search for the user's DN.
            if (false === ($dn = $this->engine->searchDn($uid))) {
                // uid not found.
                return false;
            }
        }
        
        // Try to bind to the directory.
        if ($this->engine->authenticate($dn, $password)) {
            // Write session data and return true.
            Zivios_Log::info('Login successful for: ' . $uid);
            $securityConfig = Zend_Registry::get('securityConfig');
            $this->_session->password = Zivios_Security::encrypt($password);
            $this->_session->user_dn  = strtolower($dn);
            $this->_session->uid      = strtolower($uid);
            $this->_session->pwexpired = $this->engine->pwexpired;

            // Regenerate Session ID and Set Auth to true.
            Zend_Session::regenerateId();
            $this->_session->auth = 1;
            $this->_session->ppexpire = $engine->ppexpire;
            $this->_session->pperror = $engine->pperror;
            $this->_session->ppgrace = $engine->ppgrace;
            $this->_session->ppemsg = $engine->ppemsg;
            $this->_session->setExpirationSeconds($securityConfig->inactivity_timeout, 'auth');

            return true;
        }

        return false;
    }

    public function getForm($form)
    {
        if (null === $this->_form) {
            require_once dirname(__FILE__) . '/Form/'.$form.'.php';
            $form = 'Form_' . $form;
            $this->_form = new $form;
        }
        return $this->_form;
    }
}

