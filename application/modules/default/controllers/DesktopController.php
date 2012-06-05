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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class DesktopController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function viewcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $desktopContainer  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry      = $desktopContainer;
        $this->view->toolbar    = "desktop/container/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview   = "desktop/container/dashboard/main.phtml";
    }

    public function deletecontainerAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $desktopContainer  = Zivios_Ldap_Cache::loadDn($dn);
        $containerParent = $desktopContainer->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this container
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting DesktopContainer');
            $tgroup = $handler->newGroup('Deleting a Desktop Container',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $desktopContainer->delete($tgroup);
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($containerParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Desktop Container (<em>".
                                  $desktopContainer->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('Desktop Container deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Desktop Container. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $desktopContainer;
            $this->view->tabheading = "Delete Desktop Container";
            $this->view->dataview = "desktop/container/delete/delete.phtml";
            $this->render('deletecontainer');
        }
    }

    public function doaddcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('desktopcontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['desktopcontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        // set parentdn
        $parentdn = $formData->cleanValues['dn'];

        $desktopContainer = new EMSOrganizationalUnit();
        $desktopContainer->init();

        // Initialize container with suppplied data.
        $desktopContainer->setAddDesktopContainerForm($formData->cleanValues);
        
        // Load parent
        $containerParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Desktop Container');
        $tgroup = $handler->newGroup('Creating New Desktop Container', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $desktopContainer->add($containerParent, $tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($containerParent->getdn());
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                               'default/desktop/viewcontainer/dn/'. $desktopContainer->getdn()));
            $this->addNotify('Desktop Container added successfully');
        } else {
            throw new Zivios_Error('Error adding Desktop Container. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function addcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($this->_request->getParam('dn')));
        }
    }

    public function adddesktopAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo '<div class="info">Desktop add functionality is being added to Zivios
        for the coming release. Please check the Zivios Roadmap for additional information.
        </div>';
    }
}
