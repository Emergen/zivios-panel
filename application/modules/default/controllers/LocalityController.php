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

class LocalityController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function viewAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $localityEntry  = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry   = $localityEntry;
        $this->view->toolbar = "locality/toolbar/ltb01.phtml";
        $this->view->tabheading = "Dashboard";
        $this->view->dataview = "locality/dashboard/main.phtml";
    }

    public function viewbranchAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $branchEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->entry   = $branchEntry;
    }

    public function doaddlocalityAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('localitydata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['localitydata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        $localityEntry = new EMSLocality();
        $localityEntry->init();
        
        // Validate and set locality params.
        $localityEntry->setViaForm($formData->cleanValues, array('dn'));
        
        // Load parent DN.
        $localityParent = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Locality');
        $tgroup = $handler->newGroup('Creating New Locality', Zivios_Transaction_Group::EM_SEQUENTIAL);
        $localityEntry->add($localityParent,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
        // Check transaction status and update client accordingly.
        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($localityParent->getdn());
            $this->addNotify('Locality added successfully');
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                '/default/locality/view/dn/'.$localityEntry->getdn()));
        } else {
            throw new Zivios_Error('Error adding Locality. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function addlocalityAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // Render addlocality template
        $this->view->parentdn = $dn;
        $this->render('addlocality');
    }

    public function deleteAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $localityEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $localityParent = $localityEntry->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this locality
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Locality');
            $tgroup = $handler->newGroup('Deleting a Locality',Zivios_Transaction_Group::EM_SEQUENTIAL);
            $localityEntry->deleteRecursive($tgroup);
            $tgroup->commit();

            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($localityParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Locality (<em>".
                                  $localityEntry->getProperty('cn')."</em>) deleted successfully</div>");
                $this->addNotify('Locality deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Locality. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $localityEntry;
            $this->view->tabheading = "Delete Locality";
            $this->view->dataview = "locality/delete/delete.phtml";
            $this->render('delete');
        }
    }

    public function deletebranchAction()
    {
        $dn = urldecode($this->_request->getParam('dn'));
        if (!isset($dn) || $dn=='') {
            throw new Zivios_Error('Invalid Request detected');
        }
        $branchEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $branchParent = $branchEntry->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
             $this->_helper->layout->disableLayout(true);
             $this->_helper->viewRenderer->setNoRender();
            // Delete this locality
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting Branch');
            $tgroup = $handler->newGroup('Deleting a Branch',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $branchEntry->delete($tgroup);
            $tgroup->commit();

            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($branchParent->getdn());
                $this->addDivData('dirdata',
                                  "<div class='note'> Branch deleted successfully</div>");
                $this->addNotify('Branch deleted successfully');
            } else {
                throw new Zivios_Error('Error deleting Branch. Please check system logs.');
            }
            $this->sendResponse();
        } else {

            $this->_helper->layout->disableLayout(true);

            $this->view->entry   = $branchEntry;
            $this->view->tabheading = "Delete Branch";
            $this->view->dataview = "branch/delete/delete.phtml";
            $this->render('deletebranch');
        }
    }

    public function doaddbranchAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('branchdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['branchdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        $parentdn = $formData->cleanValues['dn'];
        $branchEntry = new EMSOrganizationalUnit();
        $branchEntry->init();
        
        // Initialize branch object with supplied data.
        $branchEntry->setProperty('emstype', EMSOrganizationalUnit::TYPE_BRANCH);
        $branchEntry->setProperty('emsdescription','Branch OU');
        $branchEntry->setViaForm($formData->cleanValues, array('dn'));

        // Load parent node.
        $branchParent = Zivios_Ldap_Cache::loadDn($parentdn);
        
        // Initialize transaction handler and run add.
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding New Branch');
        $tgroup = $handler->newGroup('Creating New Branch', Zivios_Transaction_Group::EM_SEQUENTIAL );
        $branchEntry->add($branchParent, $tgroup);
        $tgroup->commit();

        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($branchParent->getdn());
            $this->addNotify('Branch added successfully');
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                'default/locality/viewbranch/dn/'. $branchEntry->getdn()));
        } else {
            throw new Zivios_Error('Error adding Branch. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function addbranchAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $this->view->parentdn = strip_tags(urldecode($dn));
        }
    }
}

