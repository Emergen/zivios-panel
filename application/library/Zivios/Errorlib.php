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

class Zivios_Errorlib
{
    protected static $_errorLib = null;

    public static function loadLibrary()
    {
        if (null === self::$_errorLib) {
            $libfile = dirname(__FILE__) . '/Errorlib/library.ini';
            self::$_errorLib = new Zend_Config_Ini($libfile);
        }

        return self::$_errorLib;
    }

    public static function errorCode($err, $append=false)
    {
        $library = self::loadLibrary();
        
        if (isset($library->errlib->$err)) {
            $msg = $library->errlib->$err;
        } else {
            Zivios_Log::error('Could not lookup errorCode: ' . $err);
            $msg = 'An error occurred during your request. Please check system logs.';
        }

        if ($append !== false) {
            $msg .= ' ' . $append;
        }

        return $msg;
    }
}

