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
class Form_Kerberos extends Zend_Dojo_Form
{
    public function init()
    {
        $session = Zend_Registry::get("installSession");

        $this->setName('krbsetup')
             ->setElementsBelongTo('krb-setup')
             ->setMethod('post')
             ->setAction('#');

        $this->addElement('ValidationTextBox', 'krb5realm', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Kerberos Realm ',
            'value'             => $session->localSysInfo["krb5realm"], 
        ));

        $this->addElement(
            'hidden',
            'initialize_krb5',
            array(
                'value' => '1'
                )
            );
        
        $this->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Initialize Kerberos data ',
            'onclick'     => "installer.postForm('krbsetup','/index/setupkrb','pcontent','callRemoteRender'); return false;",
        ));

        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'dl')),
            'Form',
        ));
    }
}
