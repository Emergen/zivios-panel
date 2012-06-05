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
 * @version		$Id: AccessException.php 1051 2008-09-09 16:34:22Z fkhan $
 * @subpackage  Core
 **/

class  Zivios_Comm_Exception extends  Zivios_Exception
{
	const UNABLE_TO_CONNECT = 8;

	public $faultcode;

	public function __construct($message) {
		// Zivios_Log::logException($message);
		parent::__construct($message);
	}

	public function setFaultCode($faultcode)
	{
		$this->faultcode = $faultcode;
	}


}
