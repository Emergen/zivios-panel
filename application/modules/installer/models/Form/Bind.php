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
class Form_Bind extends Zend_Dojo_Form
{
    public function init()
    {
        $session = Zend_Registry::get("installSession");


        $this->setName('bindsetup')
             ->setElementsBelongTo('bind-setup')
             ->setMethod('post')
             ->setAction('#');

        $this->addElement('ValidationTextBox', 'zone', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Master Zone: ',
            'value'             => $session->localSysInfo["bindzone"], 
        ));

        $this->addElement('ValidationTextBox', 'forwarder1', array(
            'required'          => false,
            'label'             => 'DNS Forwarder 1: ',
            'maxlength'         => 16,
            'regExp'            => '\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b',
            'invalidMessage'    => 'Invalid IP address specified.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/i')),
                                   ),
        ));

        $this->addElement('ValidationTextBox', 'forwarder2', array(
            'required'          => false,
            'label'             => 'DNS Forwarder 2: ',
            'maxlength'         => 16,
            'regExp'            => '\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b',
            'invalidMessage'    => 'Invalid IP address specified.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/i')),
                                   ),
        ));

        $this->addElement(
            'hidden',
            'initialize_bind',
            array(
                'value' => '1'
                )
            );
        
        $this->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Initialize Bind',
            'onclick'     => "installer.postForm('bindsetup','/index/setupbind','pcontent','callRemoteRender'); return false;",
        ));

        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'dl')),
            'Form',
        ));
    }
}
