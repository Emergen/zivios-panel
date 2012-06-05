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

class Ca_ServiceController extends Zivios_Controller_Service
{
    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        // Initialize service & master server in view.
        $serviceEntry           = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service    = $serviceEntry;
    }

    public function loadtoolbarAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry        = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
        $this->render('dashboard/toolbar/tb01');
    }
    
    public function configAction()
    {
        $this->_helper->layout->disableLayout(true);
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = urldecode($dn);
        }
        
        $serviceEntry        = Zivios_Ldap_Cache::loadDn($dn);
        $this->view->service = $serviceEntry;
    }

    public function listCertsAction()
    {
        $this->view->pubCerts = $this->view->obj->getPubCerts();
        $this->render("listcerts");
    }

    /**
     * Load all certificate details for management.
     */
    public function manageCertAction()
    {
        $filename = trim(strip_tags($this->json->file));
        $this->view->certDetails = $this->view->obj->loadCertFromFile($filename);
        $this->render("managecert");
    }

    /**
     * Add a certificate. Option loader.
     */
    public function addCertAction()
    {
        if (!isset($this->json->step)) {
            $this->render("addcertoptions");
            return;
        }

        switch ($this->json->step) {
            case "2" :
                switch ($this->json->certtype) {
                    case "scap" :
                    if (!is_array($this->json->sct) || empty($this->json->sct)) {
                        if (trim($this->json->sct) != "")
                            $this->json->sct = array($this->json->sct);
                        else {
                            $this->_createPopupReturn(1, "Please select certificate capabilities.");
                            return;
                        }
                    }

                    /**
                     * Generate (service) certificate with requested capabilities
                     */
                    try {
                        $certInfo = $this->view->obj->genServiceCert($this->json->sct);

                    } catch (Exception $e) {
                        $this->_createPopupReturn(1, "System Error when generating certificate."
                            . " Additional Information: <br />" . $e->getMessage()
                        );
                    }

                    $this->_createPopupReturn(0, "Ready to proceed!");
                    return;
                    break;

                    case "eecap" :
                    if (!is_array($this->json->cct) || empty($this->json->cct)) {
                        if (trim($this->json->cct) != "")
                            $this->json->cct = array($this->json->cct);
                        else {
                            $this->_createPopupReturn(1, "Please select certificate capabilities.");
                            return;
                        }
                    }

                    $this->_createPopupReturn(0, "Ready to proceed");
                    break;
                }

            break;

            case "3" :

            break;

            default:
            $this->_createPopupReturn(1, "Process step not defined.");
            return;
        }
    }

    public function caConfigAction()
    {
        echo '<div class="info">
        CA Configuration options will be made available soon.
        </div>
        ';
    }
}
