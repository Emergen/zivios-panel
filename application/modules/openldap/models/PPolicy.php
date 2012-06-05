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

class PPolicy extends EMSObject
{
    
    public function __construct($dn=null,$attrs = null,$acls = null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        if ($acls == null) {
            $acls = array();
        }
        
    
        $attrs[] = 'cn';
        $attrs[] = 'pwdallowuserchange';
        $attrs[] = 'pwdcheckquality';
        $attrs[] = 'pwdexpirewarning';
        $attrs[] = 'pwdfailurecountinterval';
        $attrs[] = 'pwdgraceauthnlimit';
        $attrs[] = 'pwdinhistory';
        $attrs[] = 'pwdlockout';
        $attrs[] = 'pwdlockoutduration';
        $attrs[] = 'pwdmaxage';
        $attrs[] = 'pwdmaxfailure';
        $attrs[] = 'pwdminage';
        $attrs[] = 'pwdminlength';
        $attrs[] = 'pwdmustchange';
        $attrs[] = 'pwdsafemodify';
        $attrs[] = 'pwdattribute';

        $acls[] = 'CORE_CANCHANGEPOLICY';
        
        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();
    }
    
    public function getrdn()
    {
        return 'cn';
    }
    
    
    public function getAllPolicies()
    {
        return $this->getAll('(objectclass=emspwdpolicy)');
    }
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $group,$description=null)
    {
        
        $this->requireAcl("CORE_CANCHANGEPOLICY");
        $param = $this->getParameter('objectclass');
        
        $param->addValue('pwdpolicy');
        $param->addValue('device');
        $param->addValue('emspwdpolicy');
        $this->setProperty('pwdattribute','userPassword');
        $this->setProperty('emstype',EMSObject::TYPE_PPOLICY);
        $this->setProperty('pwdcheckquality','2');
        parent::add($parent,$group,$description);
    }
    
}
    
