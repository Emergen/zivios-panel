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
 * @package     mod_posix
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class PosixUser extends Zivios_Plugin_User
{
    protected $_module = 'posix';

    public function init(EMSPluginManager $userobj)
    {
        parent::init($userobj);
        //$param = $this->getParameter('homedirectory');
        //$param->addValidator(new Zend_Validate_Regex('/^[\.\w\s\-\_\/]+$/'),
        //    Zivios_Validate::errorCode2Array('regex', "Home Directory"));
    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();
        $attrs[] = 'uidnumber';
        $attrs[] = 'gidnumber';
        $attrs[] = 'homedirectory';
        $attrs[] = 'loginshell';
        return $attrs;
    }

    public function add(Zivios_Transaction_Group $group,$description=null)
    {
        $uidnumber = $this->autocalculateuid();

        if ($this->getProperty('uidnumber') == "") {
            $this->setProperty('uidnumber', $this->autocalculateuid());
        }

        if ($this->getProperty('gidnumber') == "") {
            $this->setProperty('gidnumber',$this->_userobj->getGidnumber());
        }

        $this->addObjectClass('posixAccount');

        return parent::add($group,$description);
    }

    public function autocalculateuid()
    {
        $minusr_id = $this->ldapConfig->ldap_uid_min;
        $maxusr_id = $this->ldapConfig->ldap_uid_max;

        while ($minusr_id <= $maxusr_id) {
            $filter = "(&(objectClass=posixAccount)(uidnumber={$minusr_id}))";
            $return = array('uid');
            $result = $this->_pmobj->search($filter, $return);
            if ($result['count'] > 0) {
                $minusr_id++;
            } else {
                return $minusr_id;
            }
        }

        throw new Zivios_Exception('Ran out of UID numbers! Increase range.');
    }

    public function setUid($uid)
    {
        if ($uid <= 0) {
            $uid = $this->autocalculateuid();
            Zivios_Log::info("got autocalculated uid : $uid");
        }

        $this->setProperty('uidnumber',$uid);
    }

    public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function generateContextMenu()
    {
        return false;
    }

    public function getAvailableShells()
    {
        return iterator_to_array($this->_userConfig->shells, 1);
    }

    public function getPosixUserForm()
    {
        $regexLib     = $this->_getRegexLibrary();
        $form         = new Zend_Dojo_Form_SubForm();
        $shells       = iterator_to_array($this->_userConfig->shells, 1);
        $primaryGroup = $this->_userobj->getPrimaryGroup();

        $form->addElement('ValidationTextBox','primarygroup', 
                    array(
                        'disabled'       => true,
                        'label'          => 'Primary Group: ',
                        'value'          => $primaryGroup->getProperty('cn'),
                    ));


        $form->addElement('TextBox','gidnumber',
                    array(
                        'disabled'       => true,
                        'label'          => 'Group ID Number: ',
                        'value'          => $this->getProperty('gidnumber'),
                    ));        

        $form->addElement('TextBox','uidnumber', 
                    array(
                        'disabled'       => true,
                        'label'          => 'User ID Number: ',
                        'value'          => $this->getProperty('uidnumber'),
                    ));

        
        $form->addElement('ValidationTextBox','homedirectory', 
                    array(
                        'required'       => true,
                        'regExp'         => $regexLib->exp->homedirectory,
                        'label'          => 'Home Directory: ',
                        'invalidMessage' => 'Invalid directory name specified',
                        'value'          => $this->getProperty('homedirectory'),
                    ));

        $form->addElement(
            'FilteringSelect',
            'loginshell',
            array(
                'label'        => 'Login Shell: ',
                'value'        => $this->getProperty('loginshell'),
                'autocomplete' => true,
                'multiOptions' => $shells,
            )
        );

        $hf = new Zend_Form_Element_Hidden('userdn');
        $hf->setValue(urlencode($this->_userobj->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $form->addElement($hf);
        return $form;
    }
    
    public function import($entry)
    {
        $this->setUid($entry['uidnumber'][0]);
        $this->setProperty('homedirectory',$entry['homedirectory'][0]);
        $this->setProperty('loginshell',$entry['loginshell'][0]);
    }
    
}
