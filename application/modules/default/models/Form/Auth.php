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

class Form_Auth extends Zend_Dojo_Form
{
    public function init()
    {
        $this->setName('zvlogin')
             ->setElementsBelongTo('zv-login')
             ->setMethod('post')
             ->setAction('#');

        $this->addElement('ValidationTextBox', 'zvuser', array(
            'required'          => true,
            'label'             => 'Login ID: ',
            'value'             => '',
            'maxlength'         => 32,
            'regExp'            => '^[a-z0-9][a-z0-9.-_@]+$',
            'invalidMessage'    => 'Please enter a login ID.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/^[a-z0-9][a-z0-9.-_@]+$/i')),
                                   ),
        ));

        $this->addElement('PasswordTextBox', 'zvpass', array(
            'required'          => true,
            'label'             => 'Password: ',
            'maxlength'         => 32,
            'invalidMessage'    => 'Please enter a login password.',
            'filters'           => array('StringTrim'),
        ));

        $this->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Login',
            'onclick'     => "zivios.formXhrPost('zvlogin','/auth/dologin'); return false;",
        ));

        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'dl')),
            'Form',
        ));
    }
}
