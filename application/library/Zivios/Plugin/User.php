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

abstract class Zivios_Plugin_User extends Zivios_Plugin
{
    protected $_userConfig, $_userobj,$_groupobj;

    abstract public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup);
    abstract public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup);
    abstract public function generateContextMenu();

    public function init(EMSPluginManager $userobj)
    {
        parent::init($userobj);
        $this->_iniUserConfig();
        
        // For legacy compatibility
        $this->_userobj = $this->_pmobj;
        $groupplugname = $this->getGroupPluginName();
        $group = $userobj->getGroupWithPlugin($groupplugname);

        // we ignore the setGroup call if the user plugin cannot find a group subscription
        // to link against. This is done to ensure that addPlugin calls to user plugin objects
        // from, for example, group plugins, do not raise an exception.
        if (null !== $group) {
            $this->setGroup($group);
        }
    }

    public function getAttrs()
    {
        return parent::getAttrs();
    }

    public function getGroupPluginName()
    {
        return $this->_userConfig->libraries->group;
    }

    public function returnDisplayName()
    {
        return $this->_userConfig->general->displayname;
    }

    public function returnModuleName()
    {
        return $this->_userConfig->general->modulename;
    }

    function setGroup(EMSGroup $group)
    {
        $this->_groupobj = $group;
    }

    public function getGroupPlugin()
    {
        return $this->_groupobj->getPlugin($this->getGroupPluginName());
    }

    public function getService()
    {
        $plug = $this->getGroupPlugin();
        return $plug->getService();
    }
    
    public function getConfigSectionGeneral()
    {
        $this->_iniUserConfig();
        return $this->_userConfig->general;
    }

    protected function _iniUserConfig()
    {
        if (!$this->_userConfig instanceof Zend_Config_Ini) {
            if (!isset($this->_module) || $this->_module == '') {
                throw new Zivios_Exception("Variable _module MUST be set by your calling class: " .
                    get_class($this));
            }

            $this->appConfig = Zend_Registry::get('appConfig');

            // Instantiate cfg object for plugin
            $this->_userConfig = new Zend_Config_Ini($this->appConfig->modules . '/' .
                                                     $this->_module . '/config/user.ini');
        }
    }
}
