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
 * @subpackage  Core
 **/

class Zivios_Security
{
    public static function encrypt($data, $key = '')
    {
        if ($key == '') {
            $sConfig = self::getSecurityConfig();
            $key_location = $sConfig->secret_key;
        } else {
            $key_location = $key;
        }

        if (file_exists($key_location) && is_readable($key_location)) {
            /**
             * Open the file, read contents into this->secret_key
             */
            if (!$handle = fopen($key_location, "r"))
                throw new Zivios_Exception('Could not open Secret Key for Reading.');

            if ($handle) {
                $key = trim(fread($handle, filesize($key_location)));
                fclose($handle);
            }
        } else
            throw new Zivios_Exception('Secret Key not found or Insufficient Permissions.');

        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $encrypted_data = mcrypt_generic($td, $data);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return base64_encode($encrypted_data);
    }

    public static function decrypt($data, $key='')
    {
        if ($key == '') {
            $sConfig = self::getSecurityConfig();
            $key_location = $sConfig->secret_key;
        } else {
            $key_location = $key;
        }

        $data = base64_decode($data);

        if (file_exists($key_location) && is_readable($key_location)) {
            /**
              * Open the file, read contents into this->secret_key
              */
            if (!$handle = fopen($key_location, "r"))
                throw new Zivios_Exception('Could not open Secret Key for Reading.');

            if ($handle) {
                $key = trim(fread($handle, filesize($key_location)));
                fclose($handle);
            }
        } else
            throw new Zivios_Exception('Secret Key not found or Insufficient Permissions.');

        $td = mcrypt_module_open('tripledes', '', 'ecb', '');
        $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $decrypted_data = mdecrypt_generic($td, $data);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        return trim($decrypted_data);
    }

    public static function getSecurityConfig()
    {
        return Zend_Registry::get('securityConfig');
    }
}

