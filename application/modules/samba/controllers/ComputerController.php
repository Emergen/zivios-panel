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
 * @package		mod_samba
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id$
 * @lastchangeddate $LastChangedDate$
 **/

class Samba_ComputerController extends Zivios_Controller_Computer
{
    public function linkComputerToServiceAction()
	{
		if (!isset($this->json->process)) {
			/**
			 * Instantiate the Service.
			 */

            $service = $this->view->obj->newLdapObject($this->json->srvSelect);
            $service = $service->getObject();
            $computerplug = new SambaComputer($this->view->obj);
            $computerplug->linkToService($service);
            $trans = $this->view->obj->addPlugin($computerplug);
            $trans->process();
            $this->_createPopupReturn(0, "System subscribed successfully to the Samba Service");
            $this->_jsCallBack('nodeDetails', array($this->view->obj->getdn()));
        }
    }

    public function dashboardAction()
    {
    }
}

