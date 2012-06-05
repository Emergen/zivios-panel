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
 * @package		mod_default
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSComputer.php 967 2008-08-29 13:43:24Z fkhan $
 **/

abstract class ComputerPackage extends Zivios_Plugin_Computer
{
    protected $_module = 'package';

    public function getAttrs()
    {
    	$attrs =parent::getAttrs();
        $attrs[] = 'emssubchannels';
        return $attrs;
    }

    abstract function getSubscribedChannels();


    abstract function hasPackage($package,$version="0") ;
    
	public function generateContextMenu()
	{
		return false;
	}

    public function add(Zivios_Transaction_Group $group,$description=null)
    {
        parent::add($group,$description);
        $this->_populatePackages($group,'Populating Packages from Computer');
        return $group;
    }


}

