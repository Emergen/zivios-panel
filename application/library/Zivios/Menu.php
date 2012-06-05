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
 * @version		$Id: Menu.php 908 2008-08-25 11:03:00Z fkhan $
 * @subpackage  Core
 **/

class Zivios_Menu extends Zend_Db_Table
{
	const TABLE = 'menu';
	protected $_name = 'menu';
	private $user_session;
	private $menu_val;
	private $menu_sub;
	public $menu_type;
	public $menu;

	public function __construct($type="guest", $override=1)
	{
		parent::__construct();

		$this->user_session = new Zend_Session_Namespace("userSession");

		/**
		 * Get all "access types" for menu.
		 */
		$rows = $this->_db->fetchAll('show columns from ' . self::TABLE .
			' where field="access"');

		preg_match_all("/'(.*?)'/", $rows[0]['Type'], $enum_array);

		in_array($type, $enum_array[1]) ? $this->menu_type = $type :
			$this->menu_type = "guest";

		if ($type == "guest" && $override == 1) {
			/**
			 * Override guest if user is authenticated.
			 */
			if (!isset($this->user_session->auth))
				$this->menu_type = 'guest';
			else
            	$this->menu_type = 'authenticated';
		}
	}

	public function generate_menu()
	{
        $rows = $this->_db->fetchAll('select * from ' . self::TABLE . ' where
				access="'.$this->menu_type.'" and type="root" and display="Y"');

        $hicon = '<img src="/public/images/icons/home.png" vspace="3" align="absmiddle" />';
        $this->menu = '<div id="menutop"><ul id="navmenu">';
		$this->menu .= '<li><a href="'.'/'.'">'.$hicon.' Home</a></li>';

		foreach ($rows as $this->menu_val) {
			$this->menu_sub = false;
			$menu_sub = $this->get_children($this->menu_val["id"]);

			if ($this->menu_sub != false)
				$sub_indicator = ' +';
			else
				$sub_indicator = '';

			if (isset($this->menu_val) && $this->menu_val["icon"] != "") {
				$imgBase = '/public/images';
				$icon = '<img src="'.$imgBase . $this->menu_val['icon'].
					'" align="absmiddle" vspace="4" />';
				Zivios_Log::DEBUG("Menu ICON is: " . $icon);
			} else
				$icon = '';

			/**
			 * Check Menu Item Link Type
			 */
			switch ($this->menu_val["linktype"]) {
				case "redirect" :
				$this->menu .= '<li><a href="javascript:menu(\''
				. $this->menu_val["controller"].'\',\'redirect\')">' . $icon . $this->menu_val["name"]
				. $sub_indicator .	'</a><ul>';
				break;

				case "jsinternal" :
				$this->menu .= '<li><a href="javascript:menu(\''
				. $this->menu_val["controller"].'\', \'jsinternal\')">' . $icon . $this->menu_val["name"]
				. $sub_indicator .	'</a><ul>';
				break;

				case "disabled" :
				$this->menu .= '<li><a href="#">' . $icon . $this->menu_val["name"]
				. $sub_indicator .	'</a><ul>';
				break;
			}

			$this->menu .= $this->menu_sub;
			$this->menu .= '</ul></li>';
		}
		$this->menu .= '</ul></div>';
	}

	private function get_children($parent)
	{
        $rows = $this->_db->fetchAll('select * from ' . self::TABLE . ' where
				access="'.$this->menu_type.'" and parent="'. $parent . '" and
				display="Y"');

		if (!empty($rows)) {
			foreach ($rows as $cval) {
				if ($cval["type"] == "branch") {

					/**
					 * Generate Link Type here as well.
					 * @todo break this functionality into it's own function (low priority).
					 */

					switch ($cval["linktype"]) {
						case "redirect" :
						$this->menu_sub .= '<li><a href="javascript:menu(\''
							.$cval["controller"].'\',\'redirect\')">'. $cval["name"].
							' &raquo;</a><ul>';
						break;

						case "jsinternal":
						$this->menu_sub .= '<li><a href="javascript:menu(\''
							.$cval["controller"].'\', \'jsinternal\')">'. $cval["name"].
							' &raquo;</a><ul>';
						break;

						case "disabled" :
						$this->menu_sub .= '<li><a href="#">'. $cval["name"].
							' &raquo;</a><ul>';
						break;
					}


					$this->get_children($cval["id"]);
					$this->menu_sub .= "</ul></li>";

				} else {

					switch ($cval["linktype"]) {
						case "redirect" :
						$this->menu_sub .= '<li><a href="javascript:menu(\''
							.$cval["controller"].'\',\'redirect\')">'.$cval["name"]
							.'</a></li>';
						break;

						case "jsinternal" :
						$this->menu_sub .= '<li><a href="javascript:menu(\''
							.$cval["controller"].'\',\'jsinternal\')">'.$cval["name"]
							.'</a></li>';
						break;

						case "disabled" :
						$this->menu_sub .= '<li><a href="#">'.$cval["name"]
							.'</a></li>';
						break;
					}
				}
			}
		}
	}
}