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
 * @package		mod_openafs
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSAfsVolume.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/

class EMSAfsVolume extends EMSSecurityObject
{
	public function __construct(Ecl_LdapObject $lobj)
	{
		parent::__construct($lobj);

		/**
		 * Volume Parameters for an AFS Volume
		 * Note: validators are added for any parameters where user
		 * intervention comes in or we feel is required.
		 */
		$this->addParameter('cn','Volume ID',1);
		$this->addParameter('emsafsvolid','Volume ID', 1);

		$param = $this->addParameter('emsafsvolname','Volume Name', 1);
		/**
		 * Need to confirm the max possible length of a volume name from
		 * the AFS team.
		 */
		$param->addValidator(new Zend_Validate_StringLength(1,32),
			Ecl_Validate::errorCode2Array('stringlength',$param->dispname));

		$this->addParameter('emsafsvollocation','Volume Location', 1);
		$this->addParameter('emsafsvolpartition','Volume Partition', 1);
		$this->addParameter('emsafsvolstatus','Volume Status', 1);
		$this->addParameter('emsafsvolbackupid','Backup Volume ID', 1);
		$this->addParameter('emsafsvolparentid','Parent Volume ID', 1);
		$this->addParameter('emsafsvolcloneid','Clone Volume ID', 1);
		$this->addParameter('emsafsvolinuse','Volume in Use', 1);
		$this->addParameter('emsafsvolneedssalvage','Volume Needs Salvage', 1);
		$this->addParameter('emsafsvoldestroyme','Volume Destroy', 1);
		$this->addParameter('emsafsvoltype','Volume Type', 1);
		$this->addParameter('emsafsvolcreationdate','Volume Creation Date', 1);
		$this->addParameter('emsafsvolaccessdate','Volume Access Date', 1);
		$this->addParameter('emsafsvolcopydate','Volume Copy Date', 1);
		$this->addParameter('emsafsvolbackupdate','Volume Backup Date', 1);
		$this->addParameter('emsafsvolupdatedate','Volume Update Date', 1);
		$this->addParameter('emsafsvoldiskused','Volume Disk Used', 1);

		/**
		 * Validator added for max afs volume quota.
		 */
		$param = $this->addParameter('emsafsvolmaxquota','Volume Max Quota', 1);
		$param->addValidator(new Zend_Validate_Digits(),
			Ecl_Validate::errorCode2Array('digits',$param->dispname));

		$this->addParameter('emsafsvolmountpoint','Volume Mount Point', 1);
		$this->addParameter('emsafsvolfilecount','Volume File Count', 1);
		$this->addParameter('emsafsvoldayuse','Volume Day Use', 1);
		$this->addParameter('emsafsvolweekuse','Volume Week Use', 1);
		$this->addParameter('emsafsvolflags','Volume Flags', 1);
		$this->addParameter('emstype','EMS Object Type', 1);

	}

	public function add(EMSObject $parent,Ecl_Transaction_Handler $handler=null)
	{
		if ($handler == null) {
			$handler = $this->getTransaction();
		}
		if (get_class($this) == 'EMSAfsVolume') {

			$this->addParameter('o','Volume Entry',1);
			$this->setProperty('o', $this->getProperty('cn'));

			$this->addObjectClass('emsafsvolume');
			$this->addObjectClass('organization');
			$this->addObjectClass('emsIgnore');
		}
		return parent::add($parent,$handler);
	}
}
