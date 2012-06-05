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
class Form_Ldap extends Zend_Dojo_Form
{
    public function init()
    {
        $session = Zend_Registry::get("installSession");

        $this->setName('ldapsetup')
             ->setElementsBelongTo('ldap-setup')
             ->setMethod('post')
             ->setAction('#');

        $this->addElement('ValidationTextBox', 'scompany', array(
            'required'          => true,
            'label'             => 'Short Company Name: ',
            'maxlength'         => 16,
            'regExp'            => '^[A-Za-z0-9][A-Za-z0-9.-_@]+$',
            'invalidMessage'    => 'Please enter a "short" company name (no spaces!).',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9][a-z0-9.-_@]+$/')),
                                   ),
         ));


        $this->addElement('ValidationTextBox', 'basedn', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Base DN ',
            'value'             => $session->localSysInfo["basedn"], 
        ));             

        $this->addElement('ValidationTextBox', 'zdbuser', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Zivios Admin User: ',
            'value'             => 'zadmin', 
        ));

        $this->addElement('PasswordTextBox', 'zadminpass', array(
            'required'          => true,
            'label'             => 'zadmin Password: ',
            'maxlength'         => 32,
            'regExp'            => '\w{6,}',
            'invalidMessage'    => 'Password must be at least 6 characters long. (no white spaces!)',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       'NotEmpty',
                                       array('StringLength', true, array(6, 32)),
                                       array('Regex', true, array('/\w+/i'))
                                   ),
        ));

        $this->addElement('PasswordTextBox', 'czadminpass', array(
            'required'          => true,
            'label'             => 'Confirm zadmin Password: ',
            'maxlength'         => 32,
            'regExp'            => '\w{6,}',
            'invalidMessage'    => 'Password must be at least 6 characters long. (no white spaces!)',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       'NotEmpty',
                                       array('StringLength', true, array(6, 32)),
                                       array('Regex', true, array('/\w+/i'))
                                   ),
        ));

        $this->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Import LDAP data ',
            'onclick'     => "installer.postForm('ldapsetup','/index/setupldap','pcontent','callRemoteRender'); return false;",
            
        ));
    }
}
