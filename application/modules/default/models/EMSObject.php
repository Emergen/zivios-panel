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
 * @package     mod_default
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSObject extends Zivios_Ldap_Cache
{
    const TYPE_OU           = 1;
    const TYPE_USERC        = 'UserContainer';
    const TYPE_GROUPC       = 'GroupContainer';
    const TYPE_SERVERC      = 'ServerContainer';
    const TYPE_DESKTOPC     = 'DesktopContainer';
    const TYPE_GPOC         = 'PolicyContainer';
    const TYPE_LOCALITY     = 'LocalityContainer';
    const TYPE_BRANCH       = 'BranchContainer';
    const TYPE_USER         = 'UserEntry';
    const TYPE_GROUP        = 'GroupEntry';
    const TYPE_SERVER       = 'ServerEntry';
    const TYPE_DESKTOP      = 'DesktopEntry';
    const TYPE_SUDOC        = 'SudoContainer';
    const TYPE_SUDOI        = 'SudoItem'; // <-- FK: should be sudoEntry -mh.
    const TYPE_PPOLICY      = 'PolicyEntry';
    const TYPE_EMSCONTROL   = 'EmsControl';
    const TYPE_SERVICEC     = 'ServiceContainer';
    const TYPE_COMPGROUP    = 'ComputerGroup';
    const TYPE_ACCESSOBJECT = 'AccessObject';
    const TYPE_SERVICE      = 'ServiceEntry';
    const TYPE_SCHEMA       = 'Schema';
    const TYPE_IGNORE       = 'EmsIgnore';
    const TYPE_CUSTOM       = 'CustomContainer';

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs ==null) {
            $attrs = array();
        }

        $attrs[] = 'emstype';
        $attrs[] = 'emsdescription';
        $attrs[] = 'cn';
        $attrs[] = 'emsmodulename';

        parent::__construct($dn,$attrs,$acls);
    }

    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $group, $description=null)
    {
        if ($this->getProperty('emsdescription') == null) {
            $this->setProperty('emsdescription','None');
        }

        return parent::add($parent,$group,$description);
    }

    /**
     * From a given DN to the set Base DN in LDAP configuration, the method
     * goes over all groupContainers (moving straight up without transversing
     * over non-GroupContainer children), returning an array of EMSGroup objects
     * to the caller.
     *
     * @return array of EMSGroup objects $availableGroups
     */
    public function getAllPossibleGroups($filter='',$nomodel=null)
    {
        $lconf = Zend_Registry::get('ldapConfig');
        $containerFilter = "(|(emstype=GroupContainer)(emstype=CustomContainer))";

        if ($filter == '') {
            $groupFilter = "(&(objectClass=EMSGroup)(!(objectclass=emsIgnore)))";
        } else {
            $groupFilter = $filter;
        }
        
        $root = Zivios_Util::getRoot();

        $availableGroups = array();
        $availableGroups = $this->getAllChildren($groupFilter,null,null,$root,$nomodel);
        
        /*
        * I fail to understand why we did this:
        
        $parent = $this;
        $c=0;
        while (true) {
            $result = $parent->getAllChildren($containerFilter,'ONE',null,null,$nomodel);

            if (!empty($result)) {
                foreach ($result as $groupContainer) {
                    foreach($groupContainer->getAllChildren($groupFilter, 'ONE',null,null,$nomodel)
                        as $groupEntry) {
                        $availableGroups[] = $groupEntry;
                    }
                }
            }

            if ($parent->getdn() != $lconf->basedn) {
                $parent = $parent->getParent();
                continue;
            }

            break;
        }
        */

        return $availableGroups;
    }

    public function getAllSubtreeUsers()
    {
        $filter = "(objectclass=EMSUser)";
        return $this->getAllChildren($filter);
    }

    public function getAllSubtreeGroups()
    {
        return $this->getAllChildren("(objectclass=EMSGroup");
    }

    /**
     * Dynamically generate a transaction item for the given call.
     * if you wish to cause delayed execution of a method call postUpdate() you
     * only need to call _postUpdate($transaction_group,$description,$args)
     *
     * The First two parameters MUST be a transaction handler and a description, followed
     * by an arbitrary number of args
     *
     * @param unknown_type $method - name of the method to call
     * @param unknown_type $args
     * @return unknown
     */
     

    public function setForm(Zend_Form $form,$vals,$ignore=array())
    {
        Zivios_Log::info("DEPRECATED: Use updateViaForm().");
        $this->updateViaForm($form, $vals, $ignore);
    }

    /**
     * Update EMS type objects from validated form data. Do note that the form field 
     * "id" must match the property being updated. An optional array 'ignore' can be
     * specified to ignore certain form fields for
     *
     * @params Object $form
     * @params Array $vals, $ignoreFields, $ignoreSet
     * @return void
     */
    public function updateViaForm(Zend_Form $form, $vals, $ignoreFields=array())
    {
        if (!is_array($ignoreFields)) {
            throw new Zivios_Exception('Incorrect parameter type detected for ignoreFields');
        }

        if ($form->isValid($vals)) {
            foreach ($vals as $key => $val) {
                if (!in_array($key, $ignoreFields)) {
                    $this->setProperty($key, $val);
                }
            }
        } else {
            $errors = print_r($form->getErrors(), 1);
            $errmsgs = print_r($form->getMessages(), 1);
            Zivios_Log::error($errors);
            Zivios_Log::error($errmsgs);
            throw new Zivios_Error("Invalid form submitted. Check logs for errors");
        }
    }
    
    /**
     * Update a given EMSObject as per the defined formData. Form "keys" 
     * must match the attribute being updated.
     *
     * @return void
     */
    public function setViaForm($formData, $ignoreVals=array())
    {
        foreach ($formData as $key => $val) {
            if (!in_array($key, $ignoreVals)) {
                Zivios_Log::debug('Calling setProperty for key: ' . $key . ' with value: ' . $val);
                $this->setProperty($key, $val);
            } else {
                Zivios_Log::debug('Ignore key: ' . $key);
            }
        }
    }
}

