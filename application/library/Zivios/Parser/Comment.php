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
 * @version		$Id: Comment.php 908 2008-08-25 11:03:00Z fkhan $
 * @deprecated
 **/

class Zivios_Parser_Comment extends Zivios_Parser_Element
{
	private $text;

	public function __construct(Ecl_Parser_DTD $dtd) {
		parent::__construct($dtd);
		$this->name = "Comments";
		$this->text = array();
	}


	protected function decipher($str)
	{
		$this->text[] = trim($str);
		Ecl_Log::debug("Saved Comment :$str ");
	}

	public function canParse($string)
	{
		$dtd = $this->dtd->comment;
		$match = preg_match("/^$dtd/",$string);
		if ($match) {
			Ecl_Log::debug("$string contains a Comment");
			return 1;
		}
		return 0;
	}

	public function render()
	{
		$term = $this->dtd->parameterterm;
		$str = "";
		foreach ($this->text as $txt) {
			$str .= $txt.$term;
		}
		return $str;
	}

}
