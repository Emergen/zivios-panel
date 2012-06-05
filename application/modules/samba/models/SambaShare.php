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

class SambaShare extends EMSObject
{
    public $_module = 'samba';

    public function __construct($dn=null,$attrs=null,$acl=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'emssharepath';
        $attrs[] = 'emssharecfgcomment';
        $attrs[] = 'emssharecfgbrowseable';
        $attrs[] = 'emssharecfgwriteable';
        $attrs[] = 'emssharecfgcreatemask';
        $attrs[] = 'emssharecfgdirmask';
        $attrs[] = 'emssharecfgvalidusers';
        $attrs[] = 'emssharecfgreadonly';
        $attrs[] = 'emssharecfgguestok';

        parent::__construct($dn,$attrs,$acl);


    }

    public function getrdn()
    {
        return 'cn';
    }
    public function add(EMSObject $parent,Zivios_Transaction_Group $tgroup)
    {
        $this->addObjectClass('emssambashare');
        $this->addObjectClass('namedobject');
        $this->setProperty('emsmodulename','samba');
        $this->setProperty('emstype','ShareEntry');
        $this->setProperty('emsdescription','Samba Share Entry');

        return parent::add($parent,$tgroup);

    }

    public function renderCfg()
    {
        $appConfig = Zend_Registry::get('appConfig');
        $tmplfile = $appConfig->bootstrap->modules . $this->_module . '/config/share.smb.conf.tmpl';
        return Zivios_Util::renderTmplToCfg($tmplfile,$this);

    }

}






