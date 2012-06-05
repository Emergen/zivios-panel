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
 * @version		$Id: AsteriskUser.php 902 2008-08-25 06:39:02Z gmustafa $
 **/

class AsteriskUser extends Zivios_Plugin_User
{
    protected $_module = 'asterisk';
	
    public function init(EMSPluginManager $pm)
    {
        parent::init($pm);
        $pm->addEventListener('CORE_PCHANGE_EMSACCOUNTLOCKOUT',$this);
    }


    public function eventAction($eventname,Zivios_Transaction_Group $tgroup)
    {
        if ($eventname == 'CORE_PCHANGE_EMSACCOUNTLOCKOUT') {
            $this->setProperty('emsastdisable',$this->getProperty('emsaccountlockout'));
            $this->update($tgroup);
        }
    }
    
	public function getAttrs()
	{
		$attrs = parent::getAttrs();
		$attrs[] = 'astaccountallowedcodec';
		$attrs[] = 'astaccountamaflags';
		$attrs[] = 'astaccountcallerid';
		$attrs[] = 'astaccountcanreinvite';
		$attrs[] = 'astaccountcontext';
		$attrs[] = 'astaccountdefaultuser';
		$attrs[] = 'astaccountexpirationtimestamp';
		$attrs[] = 'astaccounthost';
		$attrs[] = 'astaccountname';
		$attrs[] = 'astaccountsecret';
		$attrs[] = 'astaccountipaddress';
		$attrs[] = 'astaccountport';
		$attrs[] = 'astaccountregistrationcontext';
		$attrs[] = 'astaccountregistrationexten';
		$attrs[] = 'astaccountsecret';
		$attrs[] = 'astaccounttype';
		$attrs[] = 'emsastphonelock';
		$attrs[] = 'emsastexten';
		$attrs[] = 'emsastroutesallowed';
		$attrs[] = 'emsastphonelockcode';
		$attrs[] = 'emsastdidnumber';
		$attrs[] = 'emsastdisable';
		$attrs[] = 'astaccountlastqualifymilliseconds';
		$attrs[] = 'astaccountqualify';
		$attrs[] = 'useragent';
		$attrs[] = 'astvoicemailmailbox';
		$attrs[] = 'astcontext';
		$attrs[] = 'astvoicemailpassword';
		$attrs[] = 'emsastvoicemailenable';
		
		return 	 $attrs;
	}

	public function getGroupPluginName()
	{
		return 'AsteriskGroup';
	}

	public function update(Zivios_Transaction_Group $tgroup,$description=null)
	{
	    if ($this->getProperty('emsastvoicemailenable') == 1)
		    $this->addObjectClass('asteriskvoicemail');
		
		return parent::update($tgroup,$description);
	}
	
	public function add(Zivios_Transaction_Group $tgroup,$description=null)
	{
		$callerid = '"'.$this->getProperty('cn').'" <'.$this->getProperty('emsastexten').'>';
		$this->setProperty('astaccountcallerid',$callerid);
		$this->addObjectClass('emsastuser');
		$this->addObjectClass('asterisksipuser');
		if ($this->getProperty('emsastvoicemailenable') == 1)
		    $this->addObjectClass('asteriskvoicemail');
		
		return parent::add($tgroup,$description);
	}
	
	

    public function delete(Zivios_Transaction_Group $tgroup,$decription=null)
    {
		$this->removeProperty('astaccountallowedcodec');
		$this->removeProperty('astaccountamaflags');
		$this->removeProperty('astaccountcallerid');
		$this->removeProperty('astaccountcanreinvite');
		$this->removeProperty('astaccountcontext');
		$this->removeProperty('astaccountdefaultuser');
		$this->removeProperty('astaccountexpirationtimestamp');
		$this->removeProperty('astaccounthost');
		$this->removeProperty('astaccountname');
		$this->removeProperty('astaccountsecret');
		$this->removeProperty('astaccountipaddress');
		$this->removeProperty('astaccountport');
		$this->removeProperty('astaccountregistrationcontext');
		$this->removeProperty('astaccountregistrationexten');
		$this->removeProperty('astaccountsecret');
		$this->removeProperty('astaccounttype');
		$this->removeProperty('emsastphonelock');
		$this->removeProperty('emsastexten');
		$this->removeProperty('emsastroutesallowed');
		$this->removeProperty('emsastphonelockcode');
		$this->removeProperty('emsastdidnumber');
		$this->removeProperty('emsastdisable');
		$this->removeProperty('astaccountlastqualifymilliseconds');
		$this->removeProperty('astaccountqualify');
		$this->removeProperty('useragent');
		$this->removeProperty('astvoicemailmailbox');
		$this->removeProperty('astcontext');
		$this->removeProperty('astvoicemailpassword');
		$this->removeProperty('emsastvoicemailenable');
		
		
		
		
        $this->removeObjectClass('emsastuser');
        $this->removeObjectClass('asterisksipuser');
        $this->removeObjectClass('asteriskvoicemail');


        return parent::delete($tgroup,$decription);
    }


	public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

	public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

	public function generateContextMenu()
	{
		return null;
	}

	public function returnDisplayName()
	{
		return "Zivios Asterisk Plugin";
	}

	public function returnModuleName()
	{
		return "asterisk";
	}

}