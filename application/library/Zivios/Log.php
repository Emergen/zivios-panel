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

class Zivios_Log
{
    public static function info($message, $writer=null)
    {
        $logMaster = self::getLoggerDetails($writer);
        $message   = self::appendMessage($message);

        if (self::checkLogLevel(Zend_Log::INFO)) {
            $logMaster['writer']->info($message);
        }
    }

    public static function warn($message, $writer)
    {
        $logMaster = self::getLoggerDetails($writer);
        $message   = self::appendMessage($message);

        if (self::checkLogLevel(Zend_Log::WARN)) {
            $logMaster['writer']->alert($message);
        }
    }

    public static function checkLogLevel($loglevel)
    {
        $logMaster = self::getLoggerDetails();
        
        $writer    = $logMaster['writer'];
        $logMaster = $logMaster['config'];
        
        if ($logMaster->productionmode) {
            
            return($logMaster->loglevel >= $loglevel);
        }
        
        $bt        = debug_backtrace();
        if (array_key_exists('class',$bt[2]))
            $class     = $bt[2]['class'];
        else 
            $class = null;
            
        $logArray  = $logMaster->toArray();

        if ($class != "" && $class != null) {
            if ($logMaster->$class != null) {
                // specific debugging is checked, go into details
                $classlog = $logMaster->$class;
                return ($classlog >= $loglevel);
            } else if ($logMaster->childrenof != null) {
                $match = 0;
                foreach ($logArray['childrenof'] as $logclass => $loglev) {
                    $match =0;
                    $reflogclass = new ReflectionClass($logclass);
                    $refobjclass = new ReflectionClass($class);

                    if ($class == $logclass) {
                        $match = 1;
                    }

                    if ($refobjclass->isSubclassOf($reflogclass)) {
                        $match =1 ;
                    }

                    if ($match) {
                        return $logArray['childrenof'][$logclass] >= $loglevel;
                    }
                }

                return ($logMaster->loglevel >= $loglevel); //failback
            }
        }

        return ($logMaster->loglevel >= $loglevel);
    }

    public static function alert($message, $writer=null)
    {
        $logMaster = self::getLoggerDetails($writer);
        $message   = self::appendMessage($message);

        if (self::checkLogLevel(Zend_Log::ALERT)) {
            $logMaster['writer']->alert($message);
        }
    }

    private static function appendMessage($message)
    {
        $logMaster = self::getLoggerDetails();
        if ($logMaster['config']->productionmode) {
            return $message;
        }
        
        $bt = debug_backtrace();
        if (is_array($message) || is_object($message)) {
            ob_start();
            var_dump($message);
            $message = ob_get_contents();
            ob_end_clean();
        }

        if (array_key_exists('class',$bt[2])) {
            $class = $bt[2]['class'];
            $line = $bt[1]['line'];
            $message = $class . '::' . $line . "|" . $message;
        }
         
        return $message;
    }

    public static function debug($message, $writer=null)
    {
        $message = self::appendMessage($message);
        $logMaster = self::getLoggerDetails($writer);

        if (self::checkLogLevel(Zend_Log::DEBUG)) {
            $logMaster['writer']->debug($message);
            return;
        }
    }

    public static function debug_r($message) {
        $message = self::appendMessage($message);
        $logMaster = self::getLoggerDetails();

        if (self::checkLogLevel(Zend_Log::DEBUG)) {
            $logMaster['writer']->debug($message);
        }
        return;
    }

    public static function internal_error($message)
    {
        $logMaster = self::getLoggerDetails();
        $message = self::appendMessage($message);
        $logMaster['writer']->err($message);
    }

    public static function exception(Exception $e)
    {
        $heading = get_class($e)."::".$e->getMessage();
        $message = $e->getTraceAsString();
        $fullmessage = $heading . "\n" . $message;
        self::error($fullmessage);
    }
    public static function error($message, $writer=null)
    {
        $logMaster = self::getLoggerDetails($writer);
        $message = self::appendMessage($message);
        if (self::checkLogLevel(Zend_Log::ERR))
            $logMaster['writer']->err($message);
    }

    public static function getLoggerDetails($writer=null)
    {
        if (null === $writer) {
            return array(
                'writer' => Zend_Registry::get('logger'),
                'config' => Zend_Registry::get('logConfig')
            );
        } else {
            return array(
                'writer' => Zend_Registry::get($writer),
                'config' => Zend_Registry::get('logConfig')
            );
        }
    }
}
