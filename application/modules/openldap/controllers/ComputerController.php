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
 * @package     mod_openldap
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Openldap_ComputerController extends Zivios_Controller_Computer
{
    protected function _init()
    {
        
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo "<div class='info'> The OpenLdap Plugin does not provide any configuration options </div>";
        
    }

    public function linkComputerToServiceAction()
    {
        Ecl_Log::debug("Hey");
        if (isset($this->json->srvSelect))  {

            /**
             * Linking computer to service.
             */
            $ldapService = Ecl_Ldap_Cache::loadDn($this->json->srvSelect);
            //$ntpService = $ntpService->getObject();

            $ldapClient = $this->view->obj->newPlugin("openldapComputer");
            $ldapClient->linkToService($ldapService);
            $trans = $this->view->obj->addPlugin($ldapClient);
            if ($this->processTransaction($trans)) {
             /**
             * Refresh computer object.
             */
                $this->_createPopupReturn(0,'Ldap Plugin added successfully');
                $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
                return;
            }
        }
    }
}

