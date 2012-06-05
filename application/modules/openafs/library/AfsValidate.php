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
 * @version		$Id: AfsValidate.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/

class AfsValidate extends Ecl_Validate
{
	private $installer_enabled = 0;

	public function __construct(Afs $emsobj, $formobj)
	{
		parent::__construct($emsobj, $formobj);
		call_user_func_array(array($this, $this->call_func), '');
	}

	private function _validateAdd()
	{
		if (!isset($this->fobj->addRequestFor)) {
			throw new Ecl_Exception("Add Request requires addRequestFor
				Value. Please read the plugin documentation.");
		}

		switch ($this->fobj->addRequestFor) {
			case "service" :
				$this->_addService();
			break;

			case "volume" :
				$this->_addVolume();
			break;

			case "addvolmountpoint" :
				$this->_addVolMount();
			break;

			case "user" :
				$this->_addUser();
			break;

			case "group" :
				$this->_addGroup();
			break;

			case "partition" :
				$this->_addPartition();
			break;

			default:
				throw new Ecl_Exception("Unknown Add Request Sent to AFS
					Validation Object");
		}

	}

	private function _validateUpdate()
	{
		if (!isset($this->fobj->updateRequestFor)) {
			throw new Ecl_Exception("Update Request requires updateRequestFor
				Value. Please read the plugin documentation.");
		}

		switch ($this->fobj->updateRequestFor) {
			case "volquota" :
				$this->_volQuotaUpdate();
			break;

			case "updatevolmountpoint" :
				$this->_updateVolMountPoint();
			break;

			case "vollocation" :
				$this->_updateVolLocation();
			break;

			default:
				throw new Ecl_Exception("Unknown Add Request Sent to AFS
					Validation Object");
		}
	}

	private function _addVolMount()
	{
		/**
		 * We need to ensure that the volume mountpoint obeys some basic
		 * criteria. Adding a validator to the parameter doesn't make sense
		 * here as we'll split and check every single path entry by exploding /
		 */
		$pattern = '/^(?:[a-z0-9_-]|\.(?!\.))+$/iD';

		if ("" === trim($this->fobj->newmountpoint))
			return $this->msg = "Please define a mountpoint";

		foreach (explode('/', trim($this->fobj->newmountpoint)) as $dirEntry)
        	if (!preg_match($pattern, $dirEntry))
				return $this->msg = "Invalid Input in Mount Point Field";
	}

	private function _updateVolMountPoint()
	{
		$pattern = '/^(?:[a-z0-9_-]|\.(?!\.))+$/iD';

		if ("" === trim($this->fobj->nmp))
			return $this->msg = "Please define a mountpoint";

		foreach (explode('/', trim($this->fobj->nmp)) as $dirEntry)
        	if (!preg_match($pattern, $dirEntry))
				return $this->msg = "Invalid Input in Mount Point Field";
	}

	private function _volQuotaUpdate()
	{
		if ($this->fobj->maxquota == "") {
			return $this->msg = "Please enter a quota for the volume";
		} else {
			if ($this->fobj->maxquota !== preg_replace('/[^0-9]/', '',
				(string) $this->fobj->maxquota)) {
				return $this->msg = "The quota field may only contain digits";
			} else {
				/**
				 * Should give the user the option to specify KB,GB,MB,TB...
				 */
				$this->fobj->maxquota = $this->fobj->maxquota * 1024;
			}
		}
	}

	private function _validateDelete()
	{

	}

	private function _addVolume()
	{
		/**
		 * Without setting properties or creating an LDAP object,
		 * this function simply does validation of passed user
		 * data. The rest of is handled outside it's scope in the controller.
		 */
		if ($this->fobj->volname == "") {
			return $this->msg = "Please enter a volume name";
		} else {
			/**
			 * Ensure the volume name does not already exist -- this may be
			 * better served on the server side (just in case the systems
			 * aren't in sync -- we'll however check in both places.
			 *   -->getImmediateChildren() (or a better way from fk)
			 */

		}

		if ($this->fobj->maxquota == "") {
			return $this->msg = "Please enter a quota for the volume";
		} else {
			if ($this->fobj->maxquota !== preg_replace('/[^0-9]/', '',
				(string) $this->fobj->maxquota)) {
				return $this->msg = "The quota field may only contain digits";
			} else {
				/**
				 * Should give the user the option to specify KB,GB,MB,TB...
				 */
				$this->fobj->maxquota = $this->fobj->maxquota * 1024;
			}
		}

		if ($this->fobj->newmountpoint != "") {
			/**
			 * Check if the mount point makes sense.
			 */
			if ($this->msg = $this->_addVolMount())
				return $this->msg;
		}

		/**
		 * Lets echo everything being sent in request.
		 *

		return $this->msg = "Mountpoint: " . $this->fobj->newmountpoint . " " .
			"Mountpoint type: " . $this->fobj->vtype . " " .
			"Release Base: " . $this->fobj->releasebasevol;
		/**/
	}

	private function _addService()
	{
		/**
		 * Adding the AFS plugin to a computer
		 */
		if ($this->fobj->doinstall != 2) {
			/**
			 * Caller is requesting complete package install.
			 * This is on the todo list.
			 */
			return $this->msg = 'Installation option currently unavailable.';
		}

		$param = $this->eobj->_computerobj->getParameter("emsafscell");

		if (trim($this->fobj->afscellname) == '')
			return $this->msg = 'Please Enter a Cell Name';
		if ($this->msg = $param->setValue($this->fobj->afscellname))
			return $this->msg;

		$param = $this->eobj->_computerobj->getParameter("emsafsrole");

		/**
		 * Three types of roles exist for AFS: DB, FS & Client. Entries
		 * added to Computer object accordingly.
		 * @note: at least one role needs to be specified.
		 */
		if (isset($this->fobj->DB) && $this->fobj->DB == 1) {
			if ($this->msg = $param->setValue('DB'))
				return $this->msg;
			else
				$rs = 1; // role satisfied check.
		}

		if (isset($this->fobj->FS) && $this->fobj->FS == 1) {
			if ($rs) {
				// role already defined. Add another value to
				// attribute.
				if ($this->msg = $param->addValue('FS'))
					return $this->msg;
			} else {
				if ($this->msg = $param->setValue('FS'))
					return $this->msg;
			}
			$rs = 1;
		}

		if (isset($this->fobj->CL) && $this->fobj->CL == 1) {
			if ($rs) {
				if ($this->msg = $param->addValue('CL'))
					return $this->msg;
			} else {
				if ($this->msg = $param->setValue('CL'))
					return $this->msg;
			}
			$rs = 1;
		}

		if (!$rs) {
			return $this->msg = "AFS Role Not Specified";
		}
	}
}
