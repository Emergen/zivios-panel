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
 * @version		$Id: IndexController.php 902 2008-08-25 06:39:02Z gmustafa $
 **/

class Asterisk_IndexController extends Zivios_Controller
{
	public function indexAction()
	{


		$emsolc = EMSOlcSchema::getOlcSchema("");
		$attrib = $emsolc->quickaddAttributeType("12.5.4","TestAttrib","For testing Only","emsdescription");
		$handler = $emsolc->update();
		$handler->process();

		echo "Index action called of asterisk indexController";
		/**
		 * Rendering is off by default -- did you create an index.phtml?
		 */
		//$this->render("index");
	}

	public function testAction()
	{
		echo "qwert";
	}

}