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

class Zivios_Validate
{
    public static function errorCode2Array($e_code, $var)
    {
        switch ($e_code) {
            case "alpha" :
            return array('notAlpha' => "The field " . $var .
                        " requires alphabets only",
                        'stringEmpty' => "The field " . $var .
                        " cannot be empty");
            break;

            case "alnum" :
            return array('notAlnum' => "The field " . $var .
                        " must be alpha numeric",
                        'stringEmpty' => "The field " . $var .
                        " cannot be empty");
            break;

            case "digits" :
            return array('notDigits' => "The field <b>" . $var .
                        "</b> must be all digits.",
                        'stringEmpty' => "The field " . $var .
                        " cannot be empty");
            break;

            case "stringlength" :
            return array('stringLengthTooShort' => "The field " . $var .
                        " length is too short",
                        'stringLengthTooLong' => "The field " . $var .
                        " length is too long");
            break;

            case "ip" :
            return array('notIpAddress' => "The field " . $var .
                        " does not appear to be a valid IP");
            break;

            case "hostname" :
            return array('hostnameInvalidHostname' => "The field " . $var .
                        " does not appear to be a valid hostname");
            break;

            case "regex" :
            return array ('regexNotMatch' => "The field " . $var .
                        " does not appear to be valid");
            break;

            case "email" :
            return array ('emailAddressInvalid' => "The filed " . $var . " does not appear to contain a valid email");
            break;

            default:
                return array();
        }
    }
}
