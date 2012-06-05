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

class Service_Ldap
{
    protected static $_ldapResource, $_ldapConfig;

    public static function getRoot()
    {
        $ldapConfig = self::getLdapConfig();
 		$dnObj      = Zivios_Ldap_Cache::loadDn($ldapConfig->basedn,'NOMODEL');
		$dnChildren = $dnObj->getImmediateChildren(null, true,'NOMODEL');

        usort($dnChildren,array('Zivios_Ldap_Engine','compare'));

        $ret = '[';
        for ($c = 0; $c < sizeof($dnChildren); $c++) {
            $entry = $dnChildren[$c];
            $view  = self::getViewDirector($entry);

            $ret  .= "{ key: '".$entry->getdn()."', type: '".$entry->getProperty('emstype').
                "', name: '" . $entry->getProperty("cn") . "', module: '" . $view['module'].
                "', controller: '" . $view['controller'] . "', action: '". $view['action'] . "' },";
        }
        $ret .= ']';

        return $ret;
    }
    
    public static function getBase()
    {
        $ldapConfig = self::getLdapConfig();
 		$dnObj      = Zivios_Ldap_Cache::loadDn($ldapConfig->basedn,'NOMODEL');
        $ret = "";
        $entry = $dnObj;
        $view  = self::getViewDirector($entry);

        $ret  .= "{ key: '".$entry->getdn()."', type: 'DirectoryContainer', name: '" . 
            $entry->getProperty("cn") . "', module: '" . $view['module'].
            "', controller: '" . $view['controller'] . "', action: '". $view['action'] . 
            "', root: true }";
        
        return $ret;
    }
    

    public static function getChildren($dn)
    {
 		$dnObj      = Zivios_Ldap_Cache::loadDn($dn);
		$dnChildren = $dnObj->getImmediateChildren(null, true,'NOMODEL');
        
        usort($dnChildren,array('Zivios_Ldap_Engine','compare'));

        $ret = '[';
        for ($c = 0; $c < sizeof($dnChildren); $c++) {
            $entry = $dnChildren[$c];
            $view  = self::getViewDirector($entry);

            $ret  .= "{ key: '".$entry->getdn()."', type: '".$entry->getProperty('emstype').
                "', name: '" . $entry->getProperty("cn") . "', module: '" . $view['module'] .
                "', controller: '" . $view['controller'] . "', action: '". $view['action'] . "' },";
        }
        $ret .= ']';
        return $ret;
    }

    public static function getViewDirector($entry)
    {
        if (!is_subclass_of($entry, 'Zivios_Ldap_Engine')) {
            throw new Zivios_Exception("Could not determine entry type in tree.");
        }

        $viewDirector = array();

        Zivios_Log::debug(strtolower($entry->getProperty('emstype')));

        switch (strtolower($entry->getProperty('emstype'))) {
            case "userentry" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'user';
                $viewDirector['action']     = 'view';
            break;

            case "groupentry" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'group';
                $viewDirector['action']     = 'view';

            break;

            case "serviceentry" :
                $viewDirector['module']     = $entry->getProperty('emsmodulename');
                $viewDirector['controller'] = 'service';
                $viewDirector['action']     = 'dashboard';
            break;

            case "desktopentry" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'desktop';
                $viewDirector['action']     = 'view';
            break;

            case "serverentry" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'server';
                $viewDirector['action']     = 'view';
            break;

            case "localityentry" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'locality';
                $viewDirector['action']     = 'view';
            break;

            case "localitycontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'locality';
                $viewDirector['action']     = 'view';
            break;
            
            case "branchcontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'locality';
                $viewDirector['action']     = 'viewbranch';
            break;
            
            case "servicecontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'service';
                $viewDirector['action']     = 'viewcontainer';
            break;
            
            case "servercontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'server';
                $viewDirector['action']     = 'viewcontainer';
            break;
            
            case "usercontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'user';
                $viewDirector['action']     = 'viewcontainer';
            break;
            
            case "groupcontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'group';
                $viewDirector['action']     = 'viewcontainer';
            break;
            
            case "desktopcontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'desktop';
                $viewDirector['action']     = 'viewcontainer';
            break;

            case "customcontainer" :
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'index';
                $viewDirector['action']     = 'viewcustomcontainer';
            break;
            
            case "policyentry" :
                $viewDirector['module']     = 'openldap';
                $viewDirector['controller'] = 'ppolicy';
                $viewDirector['action']     = 'dashboard';
            break;

            default:
                $viewDirector['module']     = 'default';
                $viewDirector['controller'] = 'index';
                $viewDirector['action']     = 'index';
        }

        return $viewDirector;
    }

    /**
     * Checks if an existing resource handler exists for LDAP before
     * initializing an instance of Zivios_Ldap_Engine
     *
     * @return object $_ldapResource
     */
    public static function getLdapResource()
    {
        if (!self::$_ldapResource instanceof Zivios_Ldap_Engine) {
            self::$_ldapResource = new Zivios_Ldap_Engine();
        }

        return self::$_ldapResource;
    }

    /**
     * Checks if an existing resource exists for ldap configuration
     * before initializing an instance of Zend_Config_Ini
     *
     * @return object $_ldapConfig
     */
    public static function getLdapConfig()
    {
        if (!self::$_ldapConfig instanceof Zend_Config_Ini) {
            self::$_ldapConfig = Zend_Registry::get('ldapConfig');
        }

        return self::$_ldapConfig;
    }
}

