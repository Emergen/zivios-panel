<?php
/**
 * Copyright (c) 2008-2010 Zivios, LLC.
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
 * @package     mod_default
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSLocality extends EMSObject
{
    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }
        $attrs[] = "cn";
        $attrs[] = "emscode";
        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();
        //$param = $this->getParameter('cn');
        //$param->addValidator(new Zend_Validate_Alpha(1),
        //        Zivios_Validate::errorCode2Array('alpha',$param->dispname));
    }

    public function add (Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->setProperty('emstype', EMSObject::TYPE_LOCALITY);

        if ($this instanceof EMSLocality) {
            $this->addAttrs(array('l'));
            $this->setProperty('l',$this->getProperty('cn'));

            $this->addObjectClass('locality');
            $this->addObjectClass('emslocality');
        }

        return parent::add($parent, $tgroup, $description);
    }

    protected function getrdn()
    {
        return 'l';
    }

}

