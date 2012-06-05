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
 * @package		Parser
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: Element.php 908 2008-08-25 11:03:00Z fkhan $
 * @deprecated
 **/

class Ecl_Parser_Element
{
	protected $dtd;
	public $name;

	public function __construct(Ecl_Parser_DTD $dtd)
	{
		$this->dtd = $dtd;
	}

	public function parse(&$filepointer,$str)
	{
		$lasttoken="";
		$this->decipher($str);
		$autoadvance = $this->dtd->paramautoadvance;
		while (!feof($filepointer) && $autoadvance) {
			$str = $this->peekLine($filepointer);
			if (($str !== FALSE) && $this->canParse($str)) {
				$this->decipher($str);
			} else {
				Ecl_Log::debug("Ended reading ".get_class($this));
				return $str;
			}
		}
	}

	protected function decipher($str)
	{
	}

	public function canParse($str)
	{
	}

	protected function peekLine(&$filepointer)
	{
		if (!feof($filepointer)) {
			$str = fgets($filepointer);
			return $str;
		}
		return FALSE;
	}

	public function stripInlineComments($string)
	{
		$arr = explode($this->dtd->comment,$string,2);
		return $arr[0];
	}



}

