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
 * @package     mod_kerberos
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class OpenldapUser extends Zivios_Plugin_User
{
    const DEFAULT_PPOLICY = "cn=default,ou=policies";
    const ADMIN_LOCK = "000001010000Z";
    
    protected $_module = 'openldap';
    
    /**
     * Instantiates the passed user object and add required krb ldap params
     *
     * @param EMSUser $userobj
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    public function getDefaultPolicy()
    {
        return self::DEFAULT_PPOLICY.",".$this->ldapConfig->basedn;
    }

    public function getAttrs()
    {
        $attr = parent::getAttrs();
        
        /**
         * Push  LDAP attributes to user obj.
         */
        $attr[] = 'pwdaccountlockedtime';
        $attr[] = 'pwdfailuretime';
        $attr[] = 'pwdreset';
        $attr[] = 'pwdchangedtime';
        $attr[] = 'pwdgraceusetime';
        $attr[] = 'pwdpolicysubentry';
       
        return $attr;
    }

    /**
     * User password update in Kerberos
     *
     * @param string $password
     */

    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);
        //$pm->addEventListener('CORE_USER_PWCHANGED',$this);
        $pm->addEventListener('CORE_PCHANGE_EMSACCOUNTLOCKOUT',$this);
    }
    
    public function eventAction($eventname,Zivios_Transaction_Group $tgroup)
    {
        Zivios_Log::debug("Event Action called with event $eventname");
        if ($eventname == 'CORE_USER_PWCHANGED') {
            $this->_setPassword($tgroup,'Changing OpenLdap Password for User',$this->_userobj->getNewPassword(), false);
        } else if ($eventname == 'CORE_PCHANGE_EMSACCOUNTLOCKOUT') {
            $this->setLocked($this->getProperty('emsaccountlockout'));
            $this->update($tgroup);
        }
    }
    
    public function setLocked($lock=1)
    {
        if ($lock) {
            $enddate = Zend_Date::now();
            $enddate = $enddate->get('YYYYMMdd').'000000Z';
            $enddate = self::ADMIN_LOCK;
            $this->setProperty('pwdaccountlockedtime',$enddate);
        } else {
            Zivios_Log::debug("Unlocking OpenLdap account, Removing pwdlockedtime");
            $this->removeProperty('pwdaccountlockedtime');
        }
    }
    
    public function isAdminLock()
    {
        return ($this->_pmobj->getProperty('emsaccountlockout')==1);
    }
    
    public function resetAccount(Zivios_Transaction_Group $tgroup,$mustchangepwd=false)
    {
        $this->_pmobj->setProperty('emsaccountlockout','0');
        $this->_pmobj->update($tgroup);
        $this->removeProperty('pwdaccountlockedtime');
        $this->pwdReset($tgroup,'Reset Openldap account',$mustchangepwd);
    }
    
    public function pwdReset($mustchangepwd = false) 
    {
        if($mustchangepwd) {
            $this->setProperty('pwdreset','TRUE'); 
        } else {
            $this->setProperty('pwdreset','FALSE');
        }
    }
    
    public function getPolicy()
    {
        $subentry = $this->getProperty('pwdpolicysubentry');
        if (isset($subentry) && $subentry != null && $subentry != "") {
            return Zivios_Ldap_Cache::loadDn($subentry);
        } else {
            return Zivios_Ldap_Cache::loadDn(self::DEFAULT_PPOLICY.",".$this->ldapConfig->basedn);
        }
    }
    
    public function getExpiryDate()
    {
        $policy = $this->getPolicy();
        $pwdchanged = Zivios_Util::ldaptoZdate($this->getProperty('pwdchangedtime'));
        if ($pwdchanged != null) {
            $expiryseconds = $policy->getProperty('pwdmaxage');
            $pwdchanged = $pwdchanged->addSecond($expiryseconds);
            return $pwdchanged;
        } else
            return null;
    }
    
    public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
    {}

    public function generateContextMenu()
    {}
}

