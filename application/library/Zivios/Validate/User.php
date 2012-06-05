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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: User.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Validator
 **/

class Zivios_Validate_User extends Zivios_Validate
{

	public function __construct(EMSObject $emsobj, $formobj)
	{
		parent::__construct($emsobj, $formobj);
		call_user_func_array(array($this, $this->call_func), '');
	}

	private function _validateAdd()
	{
		$param = $this->eobj->getParameter("givenname");
		if ($this->msg = $param->setValue($this->fobj->firstname)) {
			return $this->msg;
		}

		$param = $this->eobj->getParameter("sn");
		if ($this->msg = $param->setValue($this->fobj->lastname)) {
			return $this->msg;
		}

		$cn = $this->fobj->firstname . " " . $this->fobj->lastname;
		$param = $this->eobj->getParameter("cn");
		if ($this->msg = $param->setValue($cn)) {
			return $this->msg;
		}

		$param = $this->eobj->getParameter("uid");
		if ($this->msg = $param->setValue($this->fobj->userid)) {
			return $this->msg;
		}

		/**
		 * Password validation
		 */
		$password = $this->fobj->password;
		$passconfirm = $this->fobj->password_confirm;
		if ($password != $passconfirm) {
			return $this->msg = "User Passwords do not match";
		}

		$param = $this->eobj->getParameter("userpassword");
		if ($this->msg = $param->setValue($this->fobj->password))
			return $this->msg;
	}

	private function _validateUpdate()
	{
		switch ($this->fobj->int_call) {
			case "udetails" :
			/**
			 * All editable user level profile details checked here.
			 * Validator chains are already embedded in the User Object.
			 */
			$param = $this->eobj->getParameter("givenname");
			if ($this->msg = $param->setValue($this->fobj->firstname))
				return $this->msg;

			$param = $this->eobj->getParameter("sn");
			if ($this->msg = $param->setValue($this->fobj->lastname))
				return $this->msg;

			/**
			 * cn is based on givenname & sn.
			 */
			$param = $this->eobj->getParameter("cn");
			$cn = $this->fobj->firstname . " " . $this->fobj->lastname;
			if ($this->msg = $param->setValue($cn))
				return $this->msg;

			/**
			 * Optional values.
			 */
			if ($this->fobj->mobile != "") {
				$param = $this->eobj->getParameter("mobile");
				if ($this->msg = $param->setValue($this->fobj->mobile))
					return $this->msg;
			}

			if ($this->fobj->title != "") {
				$param = $this->eobj->getParameter("title");
				if ($this->msg = $param->setValue($this->fobj->title))
					return $this->msg;
			}

			if ($this->fobj->ou != "") {
				$param = $this->eobj->getParameter("ou");
				if ($this->msg = $param->setValue($this->fobj->ou))
					return $this->msg;
			}

			if ($this->fobj->phone1 != "") {
				$param = $this->eobj->getParameter("telephonenumber");
				if ($this->msg = $param->setValue($this->fobj->phone1))
					return $this->msg;
			}

			if ($this->fobj->fax1 != "") {
				$param = $this->eobj->getParameter("facsimiletelephonenumber");
				if ($this->msg = $param->setValue($this->fobj->fax1))
					return $this->msg;
			}

			break;

			case "ugsub" :
				/**
				 * The primary User Group cannot be changed (currently).
				 * Calculate the user un/subscription and push updates as
				 * required.
				 */
				$foo = print_r($this->fobj, 1);
				return $this->msg = $foo;
			break;

			case "uaccount" :
				return $this->msg = "Working baby, working";

			break;
		}
	}

	private function _validateDelete()
	{

	}
}