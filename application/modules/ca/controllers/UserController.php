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

class Ca_UserController extends Zivios_Controller
{
    private $caPlugin = null;
    protected function _init() {}
   
    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        echo '<div class="note">User plugin dashboard implementation is currently pending.<br />' .
             'Please visit our <a href="http://www.zivios.org/roadmap" target="_new">Road Map</a>, ' .
             'or e-mail us at <a href="mailto: zivios-discuss@lists.zivios.org">zivios-discuss@lists.zivios.org</a>';
    }

    public function activatepluginAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        echo '<div class="note">User plugin dashboard implementation is currently pending.<br />' .
             'Please visit our <a href="http://www.zivios.org/roadmap" target="_new">Road Map</a>, ' .
             'or e-mail us at <a href="mailto: zivios-discuss@lists.zivios.org">zivios-discuss@lists.zivios.org</a>';
    }

    public function _legacy_activatePluginAction()
    {
        if (isset($this->json->action) && $this->json->action == "activate") {
            /**
             * Process certificate generate request
             */

            if (!is_array($this->json->cct)) {
                if (trim($this->json->cct) != "")
                    $this->json->cct = array($this->json->cct);
                else {
                    $this->_createPopupReturn(1, "Please select certificate capabilities.");
                return;
                }
            }

            /**
             * Ensure the user has defined all required params for selected
             * capabilities.
             */
            $extensions = array();
            $props = array();
            $props['certtype'] = "user";
            $props['pubfilename'] = $this->view->obj->getProperty("uid") . '.crt';
            $props['prvfilename'] = $this->view->obj->getProperty("uid") . '.key';
            $props['subject'] = "cn=".$this->view->obj->getProperty("cn");

            foreach ($this->json->cct as $capability) {
                switch ($capability) {
                    case "https-client";
                        $extensions[] = 'https-client';
                        break;

                    case "smime" :
                        /**
                        * Ensure at least one (valid) e-mail address has been specified.
                        */

                        $emval = new Zend_Validate_EmailAddress();
                        foreach ($this->json->emailaddrs as $email) {
                            $email = trim(strip_tags($email));
                            if ($email != "") {
                                if (!$emval->isvalid($email)) {
                                    $this->_createPopupReturn(1, "Please enter valid e-mail address(es). " .
                                        " -- invalid address: ".$email);
                                    return;
                                } else {
                                    $props['emailaddrs'][] = $email;
                                }
                            }

                        }

                        if (!empty($props)) {
                            $extensions[] = "email";
                        } else {
                            $this->_createPopupReturn(1, "Please enter at least one valid e-mail address");
                            return;
                        }
                        break;

                    case "pkinit-client":
                        $extensions[] = 'pkinit-client';
                        if ($this->json->pksan == "") {
                            $this->_createPopupReturn(1, "Could not detect PKINIT Principal name. Please "
                                . "ensure the user belongs to a Kerberos Group.");
                            return;
                        } else {
                            $props['pkinit-san-id'] = $this->json->pksan;
                        }
                        break;
                }
            }

            /**
             * Get service instance, initialize service and generate user cert.
             */
            $caUser = $this->view->obj->newPlugin("CaUser");

            $pvstore = 0;
            if (isset($this->json->storeprvkey))
                $pvstore = 1;

            $handler = $caUser->_genCertificate(null, "Generating User Certificate", $extensions, $props);
            $handler = $this->view->obj->addPlugin($caUser, $handler);
            $handler->process();

            $this->_createPopupReturn(0, "User certificate generated successfully. Refreshing User entry.");
            $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
            return;
        }

        /**
         * Render the activate plugin view.
         */
        $this->render("activateplugin");
    }

    public function downloadCertAction()
    {
        $this->render("downloadcert");
    }

    public function downloadCertWinAction()
    {
        $this->view->certDetails = $this->caPlugin->readCertsOnFile();
        $this->render("downloadcertwin");
    }

    private function loadDashboardData()
    {
        return $this->caPlugin->getUserDashboard();
    }
}
