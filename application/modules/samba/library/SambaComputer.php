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

class SambaComputer extends Zivios_Plugin_Computer
{
	protected $_module = 'samba';

  	public function getAttrs()
	{
		$attrs = parent::getAttrs();
        //Samba computers are also Posix accounts eeek!
        $attrs[] = 'uidnumber';
		$attrs[] = 'homedirectory';
		$attrs[] = 'gidnumber';

        //Samba computers are also SambaSamAccounts!! double eeek

        $attrs[] = 'sambaacctflags';
        //$this->addParameter('sambahomedrive','Samba Home Drive',1);
        //$this->addParameter('sambahomepath','Samba Home Path',1);
        //$this->addParameter('sambakickofftime','Samba Kick off Time',1);
        $attrs[] = 'sambalmpassword';
        $attrs[] = 'sambantpassword';
        //$this->addParameter('sambaprofilepath','Samba Roaming Profile Path',1);
        $attrs[] = 'sambalogofftime';
        $attrs[] = 'sambalogontime';
        //$this->addParameter('sambapwdcanchange','Samba Password Can be Changed by User',1);
        //$this->addParameter('sambaprimarygroupsid','Samba Primary Groups SID',1);
        //$this->addParameter('sambapwdlastset','Samba Password Last Set Date',1);
        //$this->addParameter('sambapwdmustchange','Samba Forceful Password Change',1);
        $attrs[] = 'sambasid';
        $attrs[] = 'sambarid';
        return $attrs;
	}

	public function add(Zivios_Transaction_Group $tgroup,$description=null)
	{
		$uidnumber = $this->autocalculateuid();
        if ($this->getProperty('uidnumber') == "")
            $this->setProperty('uidnumber', $this->autocalculateuid());
        if ($this->getProperty('gidnumber') == "")
            $this->setProperty('gidnumber','515'); // Hardcoded default for Domain Computers
        $this->addObjectClass('posixAccount');

        $this->addObjectClass('sambasamaccount');

        $sambaservice = $this->getService();
        $sid = $sambaservice->getProperty('sambasid');

        if ($this->getProperty('sambarid') == null) {
            $uid = $this->getProperty('uidnumber');
            $rid = (((int)$uid) * 2) + 1000; // SAMBA Quackery
        } else $rid = $this->getProperty('sambarid');

        $this->setProperty('sambasid',"$sid-$rid");
        $this->setProperty('sambaacctflags','[W          ]');
        $this->setProperty('homedirectory','/dev/null');



        return parent::add($tgroup,$description);
	}

	public function autocalculateuid()
	{
        if (defined('LDAP_UID_START') && defined('LDAP_UID_MAX')) {
            /**
             * All required params defined.
             */
            $minusr_id = LDAP_UID_START;
            $maxusr_id = LDAP_UID_MAX;
        } /** If they are passed, add the option to check / override
            * as your needs may be
            */

        /**
         * Obviously an expensive bit of searching.
         */


        while ($minusr_id <= $maxusr_id) {
            $filter = "(&(objectClass=posixAccount)
						(uidnumber={$minusr_id}))";
            $return = array("uid");
            $results = $this->_pmobj->search($filter,$return);

            if ($return['count'] > 0)
                $minusr_id++;
            else
                return $minusr_id;
        }

        throw new Zivios_Exception('Ran out of UID numbers! Increase range.');
    }

	public function setUid($uid)
	{
		if ($uid <= 0) {
			$uid = $this->autocalculateuid();
			Zivios_Log::info("got autocalculated uid : $uid");
		}

		$this->_userobj->setProperty('uidnumber',$uid);

	}



	public function generateContextMenu()
	{
		return false;
	}


}


