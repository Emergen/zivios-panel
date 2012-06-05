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
 * @package		mod_kerberos
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: KerberosService.php 913 2008-08-25 11:24:35Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:24:35 +0600 (Mon, 25 Aug 2008) $
 **/


class PackageService extends EMSService
{
    protected $_module = 'package';

	public function __construct($dn=null,$attrs=null)
	{

        if ($attrs == null)
            $attrs = array();

        parent::__construct($dn,$attrs);
    }
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$desription=null)
    {
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emsservice');
        return parent::add($parent,$tgroup,$description);
    }
    
    public function getrdn()
    {
        return 'cn';
    }
    
}

    

