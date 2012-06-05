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

/**
 * Generates a (right click) context menu.
 *
 * @note: Almost all debug statements have been commented out. You can
 * uncomment these as required -- however, do note that they make a lot
 * of noise in log files.
 */
class Zivios_Menu_Context
{
    private $_obj, $_menuConfigs, $_cfg=false, $_js='', $_registeredPlugins=array();
    private $_appConfig, $_seConfig=false;

    public function __construct(EMSObject $obj)
    {
        $this->_obj = $obj;

        /**
         * Check if a contextMenu for the entry in question exists.
         */
        if (!$type = $this->_obj->getProperty("emstype")) {
            /**
             * This object is not recognized by the EMS as it does not
             * specify a type. We hence ignore any additional actions and
             * simply return a formatted DN.
             */
            echo $this->_returnFormattedDn();
            return;
        } else {
            /**
             * Check existing JS generated in spawned item div -- note a
             * hidden div will not make it's js available -- hence, the process
             * or generating the menu is all we can save -- or, we redirect
             * the returned JS to the innerHTML of a separate DIV altogether.
             */
        }

        //Ecl_Log::debug("Calling Load Menu Config for:" . $this->_obj->getProperty('cn'));
        $this->_loadMenuConfigs($type);

        /**
         * Menu Configuration is missing the required section to generate
         * a context menu for this entry. We simply return a formatted DN.
         */
        if (!$this->_cfg) {
            if ($type == 'ServiceEntry') {
                if ($this->_getServiceEntryMenu()) {
                    $this->_setJsGlobal();
                } else {
                    echo $this->_returnFormattedDn();
                    return;
                }
            } else {
                echo $this->_returnFormattedDn();
                return;
            }
        } else {

            /**
             * Generate the plugin menu (if one is found) and finally wrap
             * in <script... tags>
            */
            $this->_generatePluginMenu();

            /**
              * Sort Registered Plugin Names & assign to a random div ID associated
              * with the type of emsobject we're working with.
              */
            //$this->_setRegistryEntry();
            $this->_setJsGlobal();
        }
    }

    private function _getServiceEntryMenu()
    {
        if ($this->_seConfig = $this->_obj->generateContextMenu()) {
            $c = 1;
            while (1) {
                $genLink = 'link' . $c;
                Zivios_Log::debug("Generated Link is:" .$genLink);

                if (isset($this->_seConfig->$genLink->name)) {
                    //Ecl_Log::debug("Service is: " . $this->_obj->getProperty("emsmodulename"));
                    //Ecl_Log::debug("Generating menu for link: " . $this->_seConfig->$genLink->name);
                    $this->_js .= $this->_makeJs($this->_seConfig->$genLink);
                    $this->_js .= '},{';
                    $c++;
                    continue;
                } else {
                    /**
                    * Clean up & Break out -- no more links
                    */
                    $this->_js = rtrim($this->_js, ',{');
                }
                break;
            }
            return true;
        }
        return false;
    }


    /**
     * Function to be completed -- works menu with zend registry and cache
     * to avoid duplication of js generation.
     *
     * @return boolean
     */
    private function _setRegistryEntry()
    {
        if (empty($this->_registeredPlugins)) {
            return false;
        }
    }

    /**
     * Loads menu configurations into array
     */
    private function _loadMenuConfigs($type)
    {
        /**
         * Load the menu.ini file for the relevant section.
         * @todo: implement Zend_Cache_Frontend_File.
         */
        $this->_appConfig = Zend_Registry::get('appConfig');

        /**
         * Instantiate Configuration Object
         */
        try {
            $this->_cfg = new Zend_Config_Ini($this->_appConfig->bootstrap->config .
                'defaultmenu.ini', $type);

            $c = 1;
            while (1) {
                $genLink = 'link' . $c;
                //Ecl_Log::debug("Generated Link is:" .$genLink);

                if (isset($this->_cfg->$genLink->name)) {
                    /**
                     * @todo: registry check for menu
                     */
                    //Ecl_Log::debug("Generating menu for link: " . $this->_cfg->$genLink->name);
                    $this->_js .= $this->_makeJs($this->_cfg->$genLink);
                    $this->_js .= '},{';
                    $c++;
                    continue;
                } else {
                    /**
                    * Clean up & Break out -- no more links
                    */
                    $this->_js = rtrim($this->_js, ',{');
                }
                break;
            }

        } catch (Exception $e) {
            /**
             * This simply means the section specified was not found.
             * Unfortunately Zend throws an Exception here which is caught
             * by the ErrorController. We need to silently bypass this.
             */
            $this->_cfg = 0;
            //Ecl_Log::debug("Context Menu Section Missing for EMSType: " . $type);
            return;
        }

        //Ecl_Log::debug("Section found for : " . $type);
        //Ecl_Log::debug("Generated JS is: " . $this->_js);
    }

    private function _returnFormattedDn()
    {
        return '<a href="javascript:nodeDetails(\''.$this->_obj->getdn().'\')">'
            .$this->_obj->getproperty("cn").'</a>';
    }

    private function _getPluginMenu()
    {
        /**
         * First and foremost we get all plugins this object subscribes to. We
         * then further check for the provision of a plugin menu provided
         * by the plugin in question. All menus found for related plugins will
         * be saved for JS creation.
         */
        $availableMenus = array();

        if (!is_subclass_of($this->_obj, 'EMSPluginManager')) {
            //Ecl_Log::debug($this->_obj->getproperty("cn") . " Not a subclass".
            //    " of EMSPluginManager. No plugins possible");
            return array();
        }

        /**
         * Check if the plugin provides a menu configuration file.
         */
        foreach ($this->_obj->getAllPlugins() as $plugin) {
            Zivios_Log::Debug("Object: " . $this->_obj->getproperty("cn") .
                "has plugin" . $plugin->returnDisplayName());
            /**
             * Each plugin needs to populate it's own menu
             */
            if ($pm = $plugin->generateContextMenu()) {
                $availableMenus[] = $pm;
                $this->_registeredPlugins[] = $plugin->returnPluginName();
            }
        }

        return $availableMenus;
    }

    private function _generatePluginMenu()
    {
        /**
         * Get all plugin Menus
         */
        //Ecl_Log::debug("Getting Plugins for: " . $this->_obj->getproperty("cn"));
        $pluginMenus = $this->_getPluginMenu();

        /**
         * Generate the required JS for the plugin menu.
         */
        if (!empty($pluginMenus)) {

            foreach ($pluginMenus as $pgmenu) {
                $c = 1;
                while (1) {
                    $genLink = 'link' . $c;
                    if (isset($pgmenu->$genLink->name)) {
                        if ($c == 1) {
                            /**
                             * Force separator
                             */
                            $this->_js .= ',{seperator: true},{';
                        }

                        $this->_js .= $this->_makeJs($pgmenu->$genLink);
                        $this->_js .= '},{';
                        $c++;
                    } else {
                        /**
                         * Break out -- no more links
                         */
                        $this->_js = rtrim($this->_js, ',{');
                        break;
                    }
                }
            }
        } else {
            //Ecl_Log::debug("No Plugins found for: " . $this->_obj->getproperty("cn"));
        }
    }

    /**
     * Create the JS required for RC
     *
     * @param Zend_Config_Ini $menuConf
     * @return string $js
     */
    private function _makeJs($menuConf)
    {
        $js =   'name: \'' . $menuConf->name . '\',' .
                'className: \'' . $menuConf->style . '\',' .
                'callback: function(e) { name = e.element(),' .

        /**
         * Eval and populate defined menu entries.
         */
        $menuConf->callbackfunc . '(';
        $args = explode (',', $menuConf->callbackargs);

        if (!empty($args)) {
            $c = 0;
            foreach ($args as $argument) {
                /**
                 * Check arguments which need to be looked up.
                 */
                if (!isset($menuConf->$argument)) {
                    throw new Zivios_Exception("Error in RC Menu Generation." .
                        " Check your Menu Configuration File.");
                }

                $call = explode (':', $menuConf->$argument);
                switch ($call[0]) {
                    case "p" :
                        //ecl_log::debug("evaluating code: " . $call[1]);
                        if (eval('$p_return = '.$call[1].';') === FALSE) {
                            throw new Zivios_Exception("Evaluation of RC Menu" .
                                " Failed.");
                        }
                        //Ecl_Log::debug("eval output: " . $p_return);

                        /**
                         * Encode the return value as specified.
                         */
                        $js .= "'".$p_return."'";

                    break;

                    case "t" :
                        $t_return = $call[1];
                        //Ecl_Log::debug("text output: " . $t_return);
                        $js .= "'".$t_return."'";
                    break;
                }

                if ($c < count($args)-1) {
                    $js .= ',';
                }
                $c++;
            }
        }
        $js .= ')}';

        //Ecl_Log::debug('Generated JS is :' . $js);
        return $js;
    }

    /**
     * Sets the global tags to generated JS.
     *
     */
    private function _setJsGlobal()
    {
        /**
         * Generate a random ID for the menu.
         *
         * @todo: Get emstype and all registered plugins (sort plugins in array)
         * and generate an md5sum of a data-structure dump.
         *
         * The md5sum will serve as a registry entry for the plugin in question,
         * allowing bypass of JS generation.
         */
        $rstr = $this->_randomstring(10);
        $rdiv = $this->_randomstring(6);
        $rjs =
        '<script type="text/javascript">var '.$rstr.' = [{' .
        $this->_js . ']' .
        '
        new Proto.Menu({selector: \'#'.$rdiv.'\',className: \'rcmenu rcems\', menuItems: '.$rstr.'})
        ' .
        '</script>';

        //Ecl_Log::debug($rjs);
        echo $rjs . "<div id='".$rdiv."'>".$this->_returnFormattedDn()."</div>";
    }

    /**
     * Just a temp function to generate a random string.
     *
     * @param integer $len
     * @return string $str
     */
    private function _randomstring($len)
    {
        $str = '';
        $i=0;
        while($i<$len) {
            $str.=chr((rand()%26)+97);
            $i++;
        }
        return $str;
    }
}
