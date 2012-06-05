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

class Zivios_Util
{
    const LDAP_DATE_OFFSET = "+00:00";
    const TS_DATE_OFFSET = 18000;
    
    public static function htmlClean($var)
    {
        return trim(chop(strip_tags($var)));
    }

    public static function makeLowerCase($value)
    {

        if (is_array($value)) {
            $retarray= array();
            foreach ($value as $val) {
                $retarray[] = trim(strtolower($val));
            }
            return $retarray;
        }
        else {
            return trim(strtolower($value));
        }
    }

    public static function formatSize($size, $unit=null, $retstring=null, $si=true)
    {
        if ($si === true) {
            $sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB');
            $mod   = 1000;
        } else {
            $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
            $mod   = 1024;
        }

        $ii = count($sizes) - 1;
        $unit = array_search((string) $unit, $sizes);

        if ($unit === null || $unit === false)
            $unit = $ii;

        if ($retstring === null)
            $retstring = '%01.2f %s';

        $i = 0;
        while ($unit != $i && $size >= 1024 && $i < $ii) {
            $size /= $mod;
            $i++;
        }
        return sprintf($retstring, $size, $sizes[$i]);
    }

    public static function parseQuotedValue($str,$name)
    {
        $matches = array();
        $hello = preg_match_all("/ $name \'(.+?)\'/",$str,$matches);
        return $matches[1][0];

    }
    public static function parseValue($str,$name)
    {
        $matches = array();
        $boo = preg_match_all("/ $name (.+?) /",$str,$matches);
        return $matches[1][0];
    }

    public static function magicParseValue($str,$name)
    {
        $matches = array();
        $return = preg_match_all("/ $name \'(.+?)\'| $name (\w.+?) /",$str,$matches);
        if ($matches[1][0] == '') {
            return $matches[2][0];
        } else return $matches[1][0];

    }

    public static function renderTmplToCfg($template, $valarray)
    {
        $file = file_get_contents($template);
        $matches = array();

        preg_match_all("/\%\%\%(.+?)\%\%\%/", $file, $matches);

        foreach ($matches[1] as $match) {
            $tosubs = strtolower($match);
            $value = $valarray[$tosubs];
            $file = preg_replace("/\%\%\%$match\%\%\%/", $value, $file);
        }

        return $file;
    }

    /**
     * Generates a random string of specified length.
     *
     * NOTE: if you call this function outside the Zivios bootloader process,
     * ensure your seed the randomizer.
     *
     * @param int $len
     * @return string $str
     */
    public static function randomString($len)
    {
        $str = '';
        $i=0;
        while($i<$len) {
            $str.=chr((rand()%26)+97);
            $i++;
        }
        return $str;
    }

    public static function autoPopulateFromRows($obj,$rows)
    {
        foreach ($rows as $key => $value) {
            $obj->$key = $value;
        }
    }
    
    /**
     * Initializes the regex library and returns to caller
     *
     * @return object
     */
    public static function getRegexLibrary()
    {
        return Zivios_Regex::loadLibrary();
    }
    
    /**
     * Queries the _POST global array for form key.
     *
     * @param string form
     * @return boolean
     */
    public static function isFormPost($form)
    {
        if (!isset($_POST[$form]) || !is_array($_POST[$form]) || empty($_POST[$form])) {
            return false;
        }

        return true;
    }

    public static function getRoot()
    {
        $lconf = Zend_Registry::get('ldapConfig');
        return $lconf->basedn;
        
    }
    
    public static function ldapDateRender($timestamp)
    {
        if ($timestamp != null && $timestamp != "") {
            $displaydate = self::ldaptoZdate($timestamp);
            return self::tsRender($displaydate->getTimestamp());
        } else 
            return null;
        
    }
    
    public static function ldaptoZdate($ldapdate)
    {
        if ($ldapdate != null && $ldapdate != '') {
            $timestamp = substr($ldapdate,0,-1);
            $timestamp .= self::LDAP_DATE_OFFSET;
            return new Zend_Date($timestamp,Zend_Date::ISO_8601);
        } else
            return null;
        
    }
    public static function tsRender($timestamp)
    {
        $timestamp += self::TS_DATE_OFFSET;
        return gmdate("M-d Y g:i a", $timestamp);
    }
    
    public static function ldapDateOnlyRender($timestamp)
    {
        $timestamp = substr($timestamp,0,-1);
        $timestamp .= self::LDAP_DATE_OFFSET;
        
        if ($timestamp != null && $timestamp != "") {
            $displaydate = new Zend_Date($timestamp,Zend_Date::ISO_8601);
            return $displaydate->toString("EEE MMM d yyyy");
        } else 
            return "N/A";
    }
    
    public static function ldapDateRenderFormat($timestamp,$format)
    {
        $timestamp = substr($timestamp,0,-1);
        $timestamp .= self::LDAP_DATE_OFFSET;
        
        if ($timestamp != null && $timestamp != "") {
            $displaydate = new Zend_Date($timestamp,Zend_Date::ISO_8601);
            return $displaydate->toString($format);
        } else 
            return "N/A";
    }
    
    public static function rmTmpFolder($dir)
    {
        if(!$dh = @opendir($dir)) {
            return;
        }

        while (false !== ($obj = readdir($dh))) {

            if($obj == '.' || $obj == '..') {
                continue;
            }

            if (!@unlink($dir . '/' . $obj)) {
                Zivios_Util::rmTmpFolder($dir.'/'.$obj);
            }
        }

        @closedir($dh);
        @rmdir($dir);
        return;
    }
}

