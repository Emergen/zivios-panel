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
class Form_Db extends Zend_Dojo_Form
{
    public function init()
    {
        $this->setName('dbsetup')
             ->setElementsBelongTo('db-setup')
             ->setMethod('post')
             ->setAction('#');

        $this->addElement('ValidationTextBox', 'dbhost', array(
            'required'          => true,
            'label'             => 'Database Host: ',
            'value'             => 'localhost',
            'maxlength'         => 61,
            'regExp'            => '^[a-z0-9][a-z0-9.-]+$',
            'invalidMessage'    => 'Invalid Database Hostname',
            'filters'           => array('StringTrim','StringToLower'),
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9][a-z0-9.-]+$/i')),
                                   ),
        ));

        $this->addElement('ValidationTextBox', 'dbuser', array(
            'required'          => true,
            'label'             => 'Admin User: ',
            'value'             => 'root',
            'maxlength'         => 32,
            'regExp'            => '^[a-z0-9][a-z0-9.-_@]+$',
            'invalidMessage'    => 'Please enter a valid database admin user login.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9][a-z0-9.-_@]+$/i')),
                                   ),
        ));

        $this->addElement('PasswordTextBox', 'dbpass', array(
            'required'          => true,
            'label'             => 'Admin Password: ',
            'maxlength'         => 32,
            'filters'           => array('StringTrim'),
        ));

        $this->addElement('ValidationTextBox', 'dbname', array(
            'required'          => true,
            'label'             => 'Zivios DB Name: ',
            'value'             => 'zivios',
            'maxlength'         => 32,
            'regExp'            => '^[a-z0-9]+$',
            'invalidMessage'    => 'Invalid database name specified',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9]+$/i')),
                                   ),
        ));

        $this->addElement('ValidationTextBox', 'zdbuser', array(
            'required'          => true,
            'label'             => 'Zivios DB User: ',
            'value'             => 'zdbadmin', 
            'regExp'            => '^[a-z0-9][a-z0-9_-]+$',
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9_][a-z0-9_-]+$/i')),
                                   ),
        ));

        $this->addElement('PasswordTextBox', 'zdbpass', array(
            'required'          => true,
            'label'             => 'Zivios DB Password: ',
            'maxlength'         => 32,
            'filters'           => array('StringTrim'),
        ));

        $this->addElement('PasswordTextBox', 'czdbpass', array(
            'required'          => true,
            'label'             => 'Confirm Password: ',
            'maxlength'         => 32,
            'filters'           => array('StringTrim'),
        ));

        $hf = new Zend_Form_Element_Hidden('dbtype');
        $hf->setValue('mysql')
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');
        $this->addElement($hf);

        $this->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Setup Database',
            'onclick'     => "installer.postForm('dbsetup','/index/setupdb/format/ajax','pcontent','callRemoteRender'); return false;",
        ));
    }
}

