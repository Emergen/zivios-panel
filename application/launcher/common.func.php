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

function initZiviosSystem()
{
     // Initialize randomizer
    srand((double)microtime()*1000000);

    $start = microtime();
     // Define application base path and establish web root.
    defined('BASE_PATH')
        or define('BASE_PATH', realpath(dirname(__FILE__) . '/../../'));

    defined('APPLICATION_PATH')
        or define('APPLICATION_PATH', BASE_PATH . '/application');
    
    $droot = $_SERVER['DOCUMENT_ROOT'];
    $wroot = BASE_PATH . '/web';

    if ($droot != $wroot) {
        $path = preg_replace($droot, '', $wroot);
    } else {
        $path = '';
    }

    defined('WEB_ROOT') 
        or define('WEB_ROOT', strip_slashes($path));

    // Check Installation Status and set required initialization class.
    checkZiviosInstall() ? $initClass = 'Zivios_Core_Initialize' :
        $initClass = 'Zivios_Install_Initialize';

     // Set include paths
    $paths = array(APPLICATION_PATH . '/library', APPLICATION_PATH . '/library/xmlrpc');
    setIncludePath($paths);
    
    // Initialize the Zend autoloader.
    // require_once 'Zend/Loader.php';
    // Zend_Loader::registerAutoload();
    
    // Get front controller instance.
    $front = Zend_Controller_Front::getInstance();
    $front->registerPlugin(new $initClass());
    $end = microtime();

    // Dispatch request
    $front->dispatch();
}

function __autoload($className) {
    include_once $className = str_replace('_', '/', $className) . '.php';
}

/**
 * Function checks if a install stamp file exists.
 *
 * @return boolean
 */
function checkZiviosInstall()
{
    if (file_exists(APPLICATION_PATH . "/status/optimal.install.stamp")) {
        /**
         * Currently we simply return true. Correct would be to check a 'lastTest.timestamp'
         * and perform sanity checks on the installation. This could be a configurable process
         * which is auto-run on administrative login (or otherwise?).
         *
         * Any discrepancies found can be dealt with on a case by case basis.
         */
        return 1;
    } else {
        return 0;
    }
}

/**
 * Override autoloader to use Zend_Loader
 *
 * return void
function __autoload($class)
{
    Zend_Loader::autoload($class);
}
*/

function setIncludePath($paths=array())
{
    // Modify the include path and add passed directories
    if (empty($paths))
        return;

    $existingPaths = explode(PATH_SEPARATOR, get_include_path());
    foreach ($paths as $path) {
        // Ensure the path exists and the dir is not already included
        if (is_dir($path) && !in_array($path, $existingPaths)) {
            set_include_path(
                '.' . PATH_SEPARATOR . $path . PATH_SEPARATOR .
                get_include_path());
        }
    }
}

/**
 * Initialize module libaries (library & models) to be part of the
 * default include path.
 *
 * @return void
 */
function initLibraryPaths($moduleBase, $modules=array(), $forceScan=false)
{
    if (!is_array($modules)) {
        throw new Zivios_Exception("Expecting modules to be an array");
    }

    // Instantiate the array we will pass to setIncludePath()
    $includePath = array();

    // If modules array is not empty, add listed module model directories
    if (!empty($modules)) {
        foreach ($modules as $module) {
            $includePath[] = $moduleBase . '/' . $module . '/models';
            $includePath[] = $moduleBase . '/' . $module . '/models/Service';
            $includePath[] = $moduleBase . '/' . $module . '/models/Form';
        }
    } else {
        // Loop through all modules and set include path where model's directory
        if (!$dir = dir($moduleBase)) {
            throw new Zivios_Exception('Modules directory not found.');
        }

        // Look for models & library directories and add to default include
        // @todo: individual bootstraps for modules with an api as per zf 1.8.
        while (false !== ($entry = $dir->read())) {
            if($entry != '.' && $entry != '..') {
                if(is_dir($moduleBase . '/' . $entry . '/library')) {
                    $includePath[] = $moduleBase . '/' . $entry . '/library';
                }

                if(is_dir($moduleBase . '/' . $entry . '/models')) {
                    $includePath[] = $moduleBase . '/' . $entry . '/models';
                }

                if(is_dir($moduleBase . '/' . $entry . '/models/Form')) {
                    $includePath[] = $moduleBase . '/' . $entry . '/models/Form';
                }

                if(is_dir($moduleBase . '/' . $entry . '/models/Service')) {
                    $includePath[] = $moduleBase . '/' . $entry . '/models/Service';
                }
            }
        }
    }
    
    if (!empty($includePath)) {
        setIncludePath($includePath);
    }
}

/**
 * Check version & possible updates
 *
 * @return void
 */
function checkBaseSystem()
{
    srand((double)microtime()*1000000);

    defined('BASE_PATH')
        or define('BASE_PATH', realpath(dirname(__FILE__) . '/../../'));

    defined('APPLICATION_PATH')
        or define('APPLICATION_PATH', BASE_PATH . '/application');
    
    $droot = $_SERVER['DOCUMENT_ROOT'];
    $wroot = BASE_PATH . '/web';

    if ($droot != $wroot) {
        $path = preg_replace($droot, '', $wroot);
    } else {
        $path = '';
    }

    defined('WEB_ROOT') 
        or define('WEB_ROOT', $path);

     // Set include paths
    $paths = array(APPLICATION_PATH . '/library', APPLICATION_PATH . '/library/xmlrpc');
    setIncludePath($paths);
    
     // Initialize the Zend autoloader.
    require_once 'Zend/Loader.php';
    Zend_Loader::registerAutoload();

    if (is_dir(APPLICATION_PATH . '/config/_internal')) {

        define('INTCONFIG_PATH', APPLICATION_PATH . '/config/_internal');

        $cVersion  = INTCONFIG_PATH . '/current_release';
        $pVersion  = INTCONFIG_PATH . '/prior_release';

        if (file_exists($cVersion) && is_readable($cVersion)) {
            $version = trim(file_get_contents($cVersion));
            
            if (!isValidVersion($version)) {
                exit('Invalid version number detected.<br/>Please check the file: ' . $cVersion);
            }

            define('ZVERSION', $version);

        } else {
            exit('Could not determine Zivios version. Internal configuration data missing.');
        }

        if (file_exists($pVersion) && is_readable($pVersion)) {
            $pversion = trim(file_get_contents($pVersion));
            
            if (!isValidVersion($pversion)) {
                exit('Invalid version number detected.<br/>Please check the file: ' . $pVersion);
            }
            
            define('PVERSION', $pversion);

            if (!require_once(APPLICATION_PATH . '/updater/update.php')) {
                exit('Could not run update process. Please check system logs.');
            }
            exit();
        }
    } else {
        exit('Could not find required configuration data for Zivios.');
    }
}

/**
 * Validate version.
 *
 * @return boolean
 */
function isValidVersion($version)
{
    $pattern = '/^[\d\.]+$/';

    if (!preg_match($pattern, $version)) {
        return false;
    } else {
        return true;
    }
}
