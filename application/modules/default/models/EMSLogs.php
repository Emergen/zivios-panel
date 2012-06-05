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
 * @package     mod_installer
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

/**
 * EMSLogs class
 */
class EMSLogs 
{
    protected $_consoleData;

    public function __construct()
    {
        $this->_config  = Zend_Registry::get("appConfig");
    }

    /**
     * Returns formatted output of the last 15 lines.
     */
    public function getConsoleData($log)
    {
        require_once dirname(__FILE__) . '/Console/Data.php';
        $cData = new Console_Data();
        return $cData->getLastLog(15, $log);
    }
}
?>
