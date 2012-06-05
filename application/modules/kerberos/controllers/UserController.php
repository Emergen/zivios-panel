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
 * @package     mod_kerberos
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Kerberos_UserController extends Zivios_Controller
{
    protected function _init() {}
   
    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        if (null === ($dn = urldecode($this->_request->getParam('dn')))) {
            throw new Zivios_Error('Specified entry not found in system.');
        }
        $user = Zivios_Ldap_Cache::loadDn($this->getParam('dn'));
        $plugin = $user->getPlugin('KerberosUser');
        Zivios_Log::debug("Loading Kerberos Dashboard");
        $this->view->user = $user;
        $this->view->krbplugin = $plugin;
        /*$pluginform = $plugin->getMainForm();
        $form = new Zend_Dojo_Form();
        $form->setName('krbform')
             ->setElementsBelongTo('krbform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($pluginform,'mainform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue($dn)
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Update Kerberos Settings',
            'onclick'     => "zivios.formXhrPost('krbform','/kerberos/user/dodashboard'); return false;",
        ));
        
        Zivios_Log::debug("Helloooo");
        $this->view->form = $form;
        */
    }
    
    public function dodashboardAction()
    {
        $this->_helper->viewRenderer->setNoRender();

        $this->_helper->layout->disableLayout(true);
        Zivios_Log::debug($_POST);

        $form = $this->processForm('krb');
        Zivios_Log::debug($form);
        $dn = $form['dn'];
        $user = Zivios_Ldap_Cache::loadDn($dn);
        $krbplugin = $user->getPlugin('KerberosUser');
       	Zivios_Log::debug("here");

        $krbplugin->setProperty('krb5maxlife',$form['krb5maxlife']*86400);
        $datesplit = explode('-',$form['krb5passwordend']);
        $zdate = array('year' => $datesplit[0],
                       'month' => $datesplit[1],
                       'day' => $datesplit[2]);
        
        //$enddate = new Zend_Date($zdate);
        
        //date construction due to ZendDate Bug!
        
        $pwd = $zdate['year'].$zdate['month'].$zdate['day'].'000000Z';
        
        Zivios_Log::debug($zdate);
        
        if ($form['pwforceexpire'] == 1) {
            Zivios_Log::debug("Is forced...");
            $enddate = Zend_Date::now();
            $pwd = $enddate->get('YYYYMMdd').'000000Z';
        }
       
        $krbplugin->setProperty('krb5passwordend',$pwd);
	if ($form['pwlockout'] == 1) {
		$krbplugin->setLocked(1);
	}
	
	$handler = Zivios_Transaction_Handler::getNewHandler('Changing Kerberos Settings');
        $tgroup = $handler->newGroup('Changing Kerberos Settings',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $krbplugin->update($tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshPane('usrkrb5plugin');
        } 
        
        $this->sendResponse();    
    }
}

