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
 * @package     mod_package
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_Package_PackageQuery
{
    const ACTION_INSTALL="INSTALL";
    const ACTION_REMOVE="REMOVE";
    const ACTION_UPGRADE="UPGRADE";

    public $origin_action,$origin_package,$items;

    public function __construct($origincation,$originpackage=null)
    {
        $this->origin_action = $origincation;
        $this->origin_package = $originpackage;
        $this->items = array();
    }

    public function addItem(Zivios_Package_PackageQueryItem $item)
    {
        $this->items[$item->name] = $item;
    }
}
