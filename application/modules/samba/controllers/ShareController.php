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


class Samba_ShareController extends Zivios_Controller
{

    public function dashboardAction()
    {

        $this->render('edit');
    }

    public function _init()
    {
        if ((!isset($this->json->operate_dn) || $this->json->operate_dn ==""))
                throw new Zivios_Exception("Invalid called to _init(). Operate DN missing");



            $this->view->obj = Zivios_Ldap_Cache::loadDn($this->json->operate_dn);

    }


    public function editAction()
    {
        if (isset($this->json)) {
            if ($this->json->action == 'update') {
                Zivios_Log::debug($this->json);
                $sambashare = $this->view->obj;
                $sambashare->setProperty('cn',$this->json->cn);
                if (isset($this->json->browseable)) $browseable = 'yes'; else $browseable = 'no';

                $sambashare->setProperty('emssharecfgcomment',$this->json->comment);
                $sambashare->setProperty('emssharecfgbrowseable',$browseable);

                if (isset($this->json->writeable)) $writeable = 'yes'; else $writeable = 'no';
                $sambashare->setProperty('emssharecfgwriteable',$writeable);
                $sambashare->setProperty('emssharecfgcreatemask','0700');
                $sambashare->setProperty('emssharecfgdirmask','0700');
                $sambashare->setProperty('emssharecfgvalidusers','%S');
                if ($writeable == 'yes') $readonly = 'no'; else $readonly = 'yes';
                $sambashare->setProperty('emssharecfgreadonly',$readonly);

                if (isset($this->json->guestok)) $guestok = 'yes'; else $guestok = 'no';
                $sambashare->setProperty('emssharecfgguestok',$guestok);

                $sambashare->setProperty('emssharepath',$this->json->path);
                $trans = $sambashare->update();
                $trans->process();
                $this->_createPopupReturn(0, "Share Added Successfully");
                $this->_jsCallBack('nodeDetails', array($sambashare->getdn()));
            }
        }
    }

    public function addShareAction()
    {

        if (isset($this->json)) {
            if ($this->json->action == 'add') {
                Zivios_Log::debug($this->json);
                $sambashare = new SambaShare();
                $sambashare->init();
                $sambashare->setProperty('cn',$this->json->cn);
                if (isset($this->json->browseable)) $browseable = 'yes'; else $browseable = 'no';

                $sambashare->setProperty('emssharecfgcomment',$this->json->comment);
                $sambashare->setProperty('emssharecfgbrowseable',$browseable);

                if (isset($this->json->writeable)) $writeable = 'yes'; else $writeable = 'no';
                $sambashare->setProperty('emssharecfgwriteable',$writeable);
                $sambashare->setProperty('emssharecfgcreatemask','0700');
                $sambashare->setProperty('emssharecfgdirmask','0700');
                $sambashare->setProperty('emssharecfgvalidusers','%S');
                if ($writeable == 'yes') $readonly = 'no'; else $readonly = 'yes';
                $sambashare->setProperty('emssharecfgreadonly',$readonly);

                if (isset($this->json->guestok)) $guestok = 'yes'; else $guestok = 'no';
                $sambashare->setProperty('emssharecfgguestok',$guestok);

                $sambashare->setProperty('emssharepath',$this->json->path);
                $trans = $sambashare->add($this->view->obj);
                $trans->process();
                $this->_createPopupReturn(0, "Share Added Successfully");
                $this->_jsCallBack('nodeDetails', array($sambashare->getdn()));
                $this->_refreshTreeView($this->view->obj->getdn());
            }
        }

        $this->render('add');
    }





}

