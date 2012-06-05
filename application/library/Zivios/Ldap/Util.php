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

class Zivios_Ldap_Util
{
    public static function get_icon($ems_id)
    {
        switch ($ems_id) {
            case "ControlContainer":
                return "core.png";

            case "LdapBase" :
                return "dc.png";

            case "ZiviosContainer" :
                return "star.png";

            case "ServiceContainer" :
                return "serviceicn.png";

            case "LocalityContainer" :
                return "globe.gif";

            case "LocalityEntry":
                return "branch1.png";

            case "GroupContainer" :
                return "group.png";

            case "UserContainer" :
                return "user.png";

            case "ServerContainer" :
                return "server.png";

            case "DesktopContainer" :
                return "desktop.jpeg";

            case "ServerEntry" :
                return "server.png";

            case "DesktopEntry" :
                return "desktop.jpeg";

            case "UserEntry" :
                return "uid.png";

            case "GroupEntry" :
                return "group.png";

            case "KerberosContainer" :
                return "lock2.gif";

            case "ServiceEntry" :
                return "plugin.png";

            default:
                return "unknown.png";
        }
    }

    public static function explode_dn($dn, $with_attributes=0)
    {
        $var = preg_replace("/\\\,/","\\\\\\\\2C",$dn);
        $result = explode(",",$var);
        foreach( $result as $key => $value ) {
            $result[$key] = preg_replace("/\\\([0-9A-Fa-f]{2})/e",
                "''.chr(hexdec('\\1')).''", $value);
        }

        return $result;
    }

    public static function rc_dn_orig($dn)
    {
        /**
         * Translate a dn back to it's original
         * format.
         */
        return false;
        return rtrim(ereg_replace('\+', ',', ereg_replace('___','=', $dn)), ',');
    }

    public static function rc_dn_format($dn_array,$exp=0)
    {
        return false;
        /**
         * The DN for the user entry is used to generate
         * a unique CSS ID link, further used by remote
         * calls. The DN is in the following format:
         * equal to (=) signs are converted to "_"
         * a plus is appended to the end of rdns as exploded
         * to signify commas (,).
         *
         * Note, by default, the function expects a dn
         * formatted by explode_dn -- this option can be
         * forced by passing exp.
         */
        if ($exp == 1) {
            $dn_array = self::explode_dn($dn_array);
        }

        $rc_div_id = '';

        foreach ($dn_array as $value) {
            $rc_div_id .= ereg_replace('=', '___', $value) . '+';
        }

        return $rc_div_id;
    }




    public static function getUserCreds()
    {
        /**
         * @return array (with user credentials)
         */
         return Zivios_Ldap_Engine::getUserCreds();
    }

    public static function getUserObject()
    {
        $userSession = new Zend_Session_Namespace("userSession");
        return Zivios_Ldap_Cache::loadDn($userSession->user_dn);

    }

    public static function getUserDn()
    {
        $userSession = new Zend_Session_Namespace("userSession");
        return $userSession->user_dn;
    }

    public static function getCnConfig($dn)
    {
        return null;
        /*
        Zivios_Log::debug("CN Config Model ignoring supplied LdapObject");
        $bind_info["password"] = "carr0t9";
        $bind_info["dn"] = "cn=config";
        $bind_info["base_dn"] = "cn=config";
        $directory = new Zivios_Ldap($bind_info);
        $lobj = new Zivios_LdapObject($directory,$dn,1);
        return $lobj;
        */

    }
}
