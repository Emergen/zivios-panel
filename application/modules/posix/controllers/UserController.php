<?php
/**
 * Copyright (c) 2008-2010 Zivios, LLC.
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
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Posix_UserController extends Zivios_Controller
{
    protected function _init() {}

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

       if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->view->userEntry = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->posixUser = $this->view->userEntry->getPlugin('PosixUser');

        /*

        // Get posixUser form.
        $subform = $posixUser->getPosixUserForm();

        $form = new Zend_Dojo_Form();
        $form->setName('posixuserform')
             ->setElementsBelongTo('posixuserform')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($subform,'posixusersubform');

        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        
        $form->addElement($hfdn);
        
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Apply Settings',
            'onclick'     => "zivios.formXhrPost('posixuserform','/posix/user/foo'); return false;",
        ));

        $this->view->form = $form;
        */

    }

    public function updateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

                Zivios_Log::debug($_POST);

        if (!Zivios_Util::isFormPost('posixuserdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['posixuserdata']);

        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }
            
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        $userEntry   = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);
        $posixPlugin = $userEntry->getPlugin('PosixUser');
        
        $userEntry->setViaForm($formData->cleanValues, array('dn'));

        // create transaction handler
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating user posix plugin details');
        $tgroup = $handler->newGroup('Updating user posix plugin details',Zivios_Transaction_Group::EM_SEQUENTIAL);
        
        // update User entry
        $userEntry->update($tgroup);

        // Commit & Run transaction
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('User details updated successfully.');
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error updating user properties. Please check Zivios logs.');
        }
    }
}

