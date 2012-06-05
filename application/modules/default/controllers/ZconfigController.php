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

class ZconfigController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function indexAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function generalAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        $zvConfig = new ZiviosConfiguration();
        $this->view->config = $zvConfig->getZvConfig();
    }

    public function directoryAction()
    {
        $this->_helper->layout->disableLayout(true);
        
        $zvConfig = new ZiviosConfiguration();
        $this->view->config = $zvConfig->getZvConfig();
    }

    public function securityAction()
    {
        $this->_helper->layout->disableLayout(true);

        $zvConfig = new ZiviosConfiguration();
        $this->view->config = $zvConfig->getZvConfig();
    }

    public function databaseAction()
    {
        $this->_helper->layout->disableLayout(true);

        $zvConfig = new ZiviosConfiguration();
        $this->view->config = $zvConfig->getZvConfig();
    }

    public function loglevelAction()
    {
        $this->_helper->layout->disableLayout(true);

        $zvConfig = new ZiviosConfiguration();
        $this->view->config = $zvConfig->getZvConfig();
    }

    public function infoAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function updateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (!Zivios_Util::isFormPost('zcfggeneral')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['zcfggeneral']);

        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }
            
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        // Load Zivios Configuration model.
        $ZvConfig = new ZiviosConfiguration();

        // Update configuration.
        if ($ZvConfig->updateConfig($formData->cleanValues, $formData->cleanValues['section'])) {
            $this->addNotify('Zivios Configuration was updated successfully.');
            $this->sendResponse();
        } else {
            throw new Zivios_Error('There was an error updating the Configuration file. Please '.
                'check Zivios and system logs.');
        }
    }
}

