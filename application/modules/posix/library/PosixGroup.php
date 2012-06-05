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
 * @package		mod_posix
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: PosixGroup.php 1095 2008-09-10 12:03:37Z mhashmi $
 * @lastchangeddate $LastChangedDate: 2008-09-10 17:03:37 +0500 (Wed, 10 Sep 2008) $
 **/
class PosixGroup extends Zivios_Plugin_Group
{
	protected $_module = 'posix';

	public function __construct()
	{
		parent::__construct();

    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();
        $attrs[] = 'gidnumber';
        return $attrs;
    }

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);

		$gidnum = $this->getProperty('gidnumber');
	//	Zivios_Log::debug("instantiating PosixGroupPlugin with gidNumber : $gidnum");
	}

	public function autocalculategid()
    {
        $mingrp_id = $this->ldapConfig->ldap_gid_min;
        $maxgrp_id = $this->ldapConfig->ldap_gid_max;

        while ($mingrp_id <= $maxgrp_id) {
            $filter = "(&(objectClass=posixGroup)
						(gidNumber={$mingrp_id}))";
            $return = array("cn");

            $result = $this->_groupobj->search($filter,$return);
            if ($result['count'] > 0)
                $mingrp_id++;
            else
                return $mingrp_id;
        }
    }

	public function setGid($gid)
	{
		if ($gid <= 0) {
			$gid = $this->autocalculategid();
			Zivios_Log::info("got autocalculated gid : $gid");
		}
		$this->setProperty('gidnumber',$gid);
	}

	public function add(Zivios_Transaction_Group $tgroup,$description=null)
	{
		$this->addObjectClass('posixGroup');
		return $this->update($tgroup,$description);
	}


    public function import($entry)
    {
        $this->setGid($entry['gidnumber'][0]);
    }


	public function generateContextMenu()
	{
		return false;
	}
}

