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
 * @version		$Id: Branch.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Validator
 **/

class Zivios_Validate_Branch extends Zivios_Validate
{

	public function __construct(EMSObject $emsobj, $formobj)
	{
		parent::__construct($emsobj, $formobj);
		call_user_func_array(array($this, $this->call_func), '');
	}

	private function _validateAdd()
	{
		$param = $this->eobj->getParameter("cn");
		if ($this->msg = $param->setValue($this->fobj->branch))
			return $this->msg;
	}

	private function _validateUpdate()
	{

	}

	private function _validateDelete()
	{

	}
}