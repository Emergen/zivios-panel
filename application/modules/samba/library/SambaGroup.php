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

class SambaGroup extends Zivios_Plugin_Group
{
    protected $_module = 'samba';

    public $sambarid = null;
    public function getAttrs()
	{
        $attrs = parent::getAttrs();
        $attrs[] = 'sambasid';
        $attrs[] = 'sambagrouptype';

        return $attrs;

    }

    public function setRid($rid)
    {
        $this->sambarid = $rid;
    }

    public function add(Zivios_Transaction_Group $tgroup,$description=null)
    {
        if (!$this->_groupobj->hasModule('posix'))
            throw new Zivios_Exception("Samba Group Requires Module Posix!");
        if (!$this->_groupobj->hasModule('kerberos'))
            throw new Zivios_Exception("Samba Group Requires Module kerberos!");


        $sambaservice = $this->getService();
        $sid = $sambaservice->getProperty('sambasid');

        if ($this->sambarid == null) {
            $gid = $this->getProperty('gidnumber');
            $rid = (((int)$gid) * 2) + 1001; // SAMBA Quackery
        } else $rid = $this->sambarid;

        if ($this->getProperty('sambasid') == null) {
            $this->setProperty('sambasid',"$sid-$rid");
        }

        $this->addObjectClass('sambaGroupMapping');
        return parent::add($tgroup,$description);

    }

    public function generateContextMenu()
    {
        return false;
    }

}



