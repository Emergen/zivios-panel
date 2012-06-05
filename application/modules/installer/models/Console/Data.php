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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Console_Data
{
    protected $_logConfig, $_limit;

    public function __construct()
    {
        $this->_logConfig = Zend_Registry::get("logConfig");
    }

    public function getLastLog($limit = 50)
    {
        if ((int) $limit > 0) {
            $this->_limit = $limit;
        } else {
            return;
        }

        $logLines = $this->__getLogLines($this->_limit);
        $feed = $this->__parseLogs($logLines);

        $consoleData = new Zend_Dojo_Data();
        $consoleData->setIdentifier('cdata');
        $consoleData->setLabel('name');
       
        if (!empty($feed)) {
            for ($c = 0; $c < count($feed); $c++) {
                $id = 'cdata' . $c;
                $linefeed = array('cdata' => $id, 'type' => 'logline', 'name' => $feed[$c]);
                $consoleData->addItem($linefeed);
            }
        }

        // truncate file before returning data.
        $this->__truncateLog();
        return $consoleData;
    }
    
    /**
     * Function parses out data from logs for installer console.
     *
     * @todo: function needs to check data / time and better format
     * the result.
     */
    private function __parseLogs($logLines)
    {
        $c = 0;
        $feed = array();

        if (sizeof($logLines) < $this->_limit) {
            $limit = sizeof($logLines);
        } else {
            $limit = $this->_limit;
        }

        while ($c < $limit) {
            $line = trim($logLines[$c]);
            
            $feedLine = explode('|', $line);
            if (is_array($feedLine) && !empty($feedLine) && isset($feedLine[1])) {
                $feed[] = $feedLine[1];
            }
            $c++;
        }

        return $feed;
    }

    private function __truncateLog()
    {
         if (!$handle = fopen($this->_logConfig->consolelog, 'w')) {
            Zivios_Log::error("Error truncating console log file.");
            return;
        }

        fclose($handle);
    }

    private function __getLogLines($lines)
    {
        if (!$handle = fopen($this->_logConfig->consolelog, 'r')) {
            Zivios_Log::error("Could not open console log file for reading.");
            return;
        }

        $linecounter = $lines;
        $linelength  = 160;
        $pos         = -2;
        $beginning   = false;
        $text        = array();

        while ($linecounter > 0) {
            $t = ' ';
            while ($t != "\n") {
                if(fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                
                $t = fgetc($handle);
                $pos--;
            }

            $linecounter--;
                
            if($beginning) {
                rewind($handle);
            }
        
            $text[$lines - $linecounter - 1] = fgets($handle);
            
            if($beginning) {
                break;
            }
       }
       
       fclose ($handle);
       return array_reverse($text);
    }
}

