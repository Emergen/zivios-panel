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

// Required function library
$file = dirname(__FILE__) . '/../application/launcher/common.func.php';

if (file_exists($file) && is_readable($file)) {
    if (!include_once($file)) {
        exit('Error: Zivios could not initialize the common loader function file.');
    }
} else {
    exit('Error: Zivios could not find the common loader function file.');
}

// Check base system version & possible updates.
checkBaseSystem();

// Initialize system
initZiviosSystem();
