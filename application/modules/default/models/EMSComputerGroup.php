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
 * @package		mod_default
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSComputer.php 967 2008-08-29 13:43:24Z fkhan $
 **/

class EMSComputerGroup extends EMSPluginManager
{
    public function __construct($dn=null,$attrs=null)
	{
        if ($attrs == null)
            $attrs = array();

		$attrs[] = 'member';
        $attrs[] = 'cn';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();

    }

    public function getrdn()
    {
    	return 'cn';
    }

    public function addComputerToGroup(EMSComputer $computer,Zivios_Transaction_Group $tgroup)
    {
        $this->addPropertyItem('member',$computer->getdn());

        // Check current package list and update packages according to manifest if
        // not found
        return $this->update($tgroup);
    }

    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->addObjectClass('emscomputergroup');
        $this->addObjectClass('groupofnames');
        $this->setProperty('member',$this->ldapConfig->placeholder);
        return parent::add($parent,$tgroup,$description);
    }

    public function getAllComputers()
    {
        $comparray = array();
        $this->getAllURecurse($this->getdn(),$comparray);
        return $comparray;
    }

    private function getAllURecurse($dn,&$comparray)
    {
        /** get all immediate groups */
        Zivios_Log::debug("Finding all Computers for Computer group :$dn");
        $currgroup = Zivios_Ldap_Cache::loadDn($dn);
        $members = $currgroup->getProperty('member');
        //$memberarr = print_r($members,1);
        //Zivios_Log::debug("group $dn has member list :$memberarr");

        for ($i=1;$i<sizeof($members);$i++) {
            $objdn = $members[$i];
            Zivios_Log::debug("Processing member : $objdn");
            if ($objdn != $this->ldapConfig->placeholder) {
                $emsobjdn = Zivios_Ldap_Cache::loadDn($objdn);

                if ($emsobjdn instanceof EMSComputer) {
                    $comparray[] = $emsobjdn;
                } else if ($emsobjdn instanceof EMSComputerGroup ) {
                    Zivios_Log::debug("Computer group {$objdn} is a member of {$dn}");
                    $this->getAllURecurse($objdn,$comparray);
                }
            }
        }
    }
}

