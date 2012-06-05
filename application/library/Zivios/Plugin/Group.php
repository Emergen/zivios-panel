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

abstract class Zivios_Plugin_Group extends Zivios_Plugin
{
    protected $_groupobj, $_serviceobj, $_groupConfig;

    abstract public function generateContextMenu();

    public function init(EMSPluginManager $groupobj)
    {
        parent::init($groupobj);
        $this->_groupobj = $this->_pmobj;
    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();

        $attrs[] = 'emsservicemap';
        return $attrs;
    }

    public function getService()
    {
        if (!isset($this->_serviceobj)) {
            $array = $this->searchMap();
            if ($array == null) {
                throw new Zivios_Exception("No services found for group: " . $this->_pmobj->getdn());
            }
            Zivios_Log::debug("Search map returned array : " . implode(",",$array));
            $this->_serviceobj = Zivios_Ldap_Cache::loadDn($array[1]);
        }

        return $this->_serviceobj;
    }

    public function unlinkService()
    {
        $serventry = $this->searchMap();
        $serventry = implode(":",$serventry);
        $this->removePropertyItem('emsservicemap',$serventry);
    }

    public function linkToService(EMSService $service)
    {
        $classname = get_class($service);
        $pluginclass = get_class($this);
        $dn = $service->getdn();
        if ($this->searchMap() != null) {
            throw new Zivios_Exception("Plugin Already linked!!!");
        } else {
             //No Entry found, begin linking
            $parameter = $this->getParameter('emsservicemap');
            $str = "$pluginclass:$dn:$classname";
            $parameter->addValue($str);
            $this->_serviceobj = $service;
        }
    }

    private function searchMap()
    {
        $pluginclass = get_class($this);
        $map = $this->getProperty('emsservicemap');
        $maparray = array();

        if ($map != null) {
            if (!is_array($map)) {
                $maparray[0] = $map;
            } else {
                $maparray = $map;
            }

            foreach ($maparray as $entry) {
                $array = explode(":",$entry);
                if (trim($array[0]) == $pluginclass) {
                    return $array;
                }
            }
        }
        return null;
    }

    public function addToGroup(EMSUser $user,Zivios_Transaction_Group $group)
    {

        $titem = $group->newTransactionItem('Adding User ' . $user->getdn() . ' to group ' . $this->_groupobj->getdn());
        $titem->addObject('emsgroupplugin',$this);
        $titem->addObject('emsuser',$user);
        $titem->addCommitLine('$this->emsgroupplugin->postAddToGroup($this->emsuser);');
        $titem->commit();
        return $group;
    }

    public function postAddToGroup(EMSUser $user) {}

    public function removeFromGroup(EMSUser $user,Zivios_Transaction_Group $tgroup)
    {

        Zivios_Log::debug(get_class($this) . "::: removing user ." . $user->getdn() . " from group "
            . $this->_groupobj->getdn());

        $tgroup = $this->removeUserPluginConditional($tgroup,$user);
        $titem = $tgroup->newTransactionItem("Removing Group Plugin " . get_class($this) .
        		" for user ." . $user->getdn() . " in group ". $this->_groupobj->getdn());
        $titem->addObject('emsgroupplugin',$this);
        $titem->addObject('emsuser',$user);
        $titem->addCommitLine('$this->emsgroupplugin->postRemoveFromGroup($this->emsuser);');
        $titem->commit();
        return $tgroup;
    }

    public function postRemoveFromGroup(EMSUser $user) {}

    public function removeUserPluginConditional(Zivios_Transaction_Group $tgroup,$user=null)
    {
        /**
        * This method is called when a group is deleted, when a user is unsubscribed from the group
        * or when a plugin is removed from a group. Its job is to check if a member user has
        * other groups providing simliar plugins or if this was the only group providing this plugin
        *
        * If the user has another group exporting this module, do not remove the plugin from the user.
        * If this is the LAST group providing this module, deprovision the user for this service
        */

        if ($user == null) {
            $users = $this->_groupobj->getAllUsers();
        } else {
            $users = array();
            $users[] = $user;
        }

        $this->_iniGroupConfig();
        $userplugin = $this->_groupConfig->libraries->user;
        $groupplugin = $this->_groupConfig->libraries->group;

        foreach ($users as $user) {
            Zivios_Log::debug("Processing User from plugin ". $userplugin ." remove : " . $user->getdn());

            if ($user->hasPlugin($userplugin)) {
                $groups = $user->getAllGroupsWithPlugin($groupplugin);

                Zivios_Log::debug("sizeof groups subscribed is :" . sizeof($groups));

                if (sizeof($groups) == 1) {
                    //This group is the only group providing this plugin, remove plugin from user!
                    Zivios_Log::info("Auto removing plugin $userplugin from user :".$user->getdn());
                    $tgroup = $user->removePlugin($userplugin,$tgroup);
                } else
                    Zivios_Log::info("User ".$user->getdn()." has more groups, not removing");
            }
        }
        return $tgroup;
    }

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        Zivios_Log::debug("Delete Called! on " . get_class($this));


        $tgroup = $this->removeUserPluginConditional($tgroup);
        $this->unlinkService();
        $tgroup = $this->update($tgroup,$description);
        return $tgroup;
    }

    public function returnDisplayName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->general->displayname;
    }

    public function returnModuleName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->general->modulename;
    }

    public function getUserPluginName()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->libraries->user;
    }

    /**
     * Returns the general section of the configuraton file.
     * 
     * @return Zend_Config_Ini
     */
    public function getConfigSectionGeneral()
    {
        $this->_iniGroupConfig();
        return $this->_groupConfig->general;
    }

    protected function _iniGroupConfig()
    {
        if (!$this->_groupConfig instanceof Zend_Config_Ini) {
            if (!isset($this->_module) || $this->_module == '')
                throw new Zivios_Exception("Variable _module MUST be set by your calling class.");

            $this->appConfig = Zend_Registry::get('appConfig');
            if (file_exists($this->appConfig->modules . '/' .
                $this->_module . '/config/group.ini')) {
                $this->_groupConfig = new Zend_Config_Ini($this->appConfig->modules . '/' .
                    $this->_module . '/config/group.ini');
            } else {
                throw new Zivios_Exception('Module: ' . get_class($this) .
                    ' does not provide a group.ini.');
            }
        }
    }
    
}
