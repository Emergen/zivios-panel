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
 * @package     mod_dns
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class EMSDnsPtrRecord extends EMSObject
{
    private $dnsrecord;

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'dlzhostname';

        parent::__construct($dn,$attrs,$acls);

    }

    public function init()
    {
        parent::init();

        if (!$this->isNew())
            $this->dnsrecord = Zivios_Ldap_Cache::loadDn('dlzrecordid=1,' . $this->getdn());
        else {
            Zivios_Log::debug('Initializing new EMSDnsRecord of type PTR');
            $this->dnsrecord = new EMSDnsRecord();
            $this->dnsrecord->init();

            Zivios_Log::debug('Setting type Record to PTR');
            $this->dnsrecord->setType(EMSDnsRecord::PTR_REC);
        }

        $param = $this->getParameter('dlzhostname');
        //$param->addValidator(new Zend_Validate_Ip(),'Reverse IP');

        $param = $this->dnsrecord->getParameter('dlzdata');
        //$param->addValidator(new Zend_Validate_Regex("/^([a-z0-9][a-z0-9\-]*[a-z0-9]\.){2,}$/"),'Mapped Host Name');
    }

    public function getrdn()
    {
        return 'dlzhostname';
    }

    public function getHostname()
    {
        return rtrim($this->dnsrecord->getProperty('dlzdata'), '.');
    }

    public function setHostname($hostname)
    {
        return $this->dnsrecord->setProperty('dlzdata', $hostname);
    }

    public function setPtrIp($ip)
    {}

    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description = null)
    {
        $this->setProperty('cn',$this->getProperty('dlzhostname'));
        $ip = $this->getProperty('dlzhostname');

        if (preg_match("/\./",$ip)) {
            // IP is dot formatted and not a single number, reverse it.
            $octects     = explode('.', $ip);
            $octects     = array_reverse($octects);
            $ptrip       = implode('.', $octects);
            $dlzhostname = $this->getParameter('dlzhostname');

            // Force update
            $dlzhostname->setValue($ptrip, 0, 1);
        }

        $this->setProperty('emsdescription','DNS Host Name Entry');
        $this->setProperty('emstype',EMSObject::TYPE_IGNORE);

        if (get_class($this) == 'EMSDnsPtrRecord') {
            $this->addObjectClass('dlzhost');
            $this->addObjectClass('emsdnshostname');
        }

        $this->dnsrecord->setProperty('dlzrecordid',1);
        $handler = parent::add($parent, $tgroup, $description);

        return $this->dnsrecord->add($this, $tgroup, $description);
    }

    public function deleteRecord(Zivios_Transaction_Group $tgroup, $description=null)
    {
        $handler = $this->dnsrecord->delete($tgroup, $description);
        return parent::delete($tgroup);
    }

    public function rvZoneOpts($zone, $service)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();
        
        // Initialize form
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $reform = new Zend_Dojo_Form_SubForm();
        $reform->setAttribs(array(
            'name'          => 'dnsaddptr' . $strpCn,
            'legend'        => 'Add PTR Record',
            'dijitParams'   => array(
                'title' => 'Add PTR Record',
            ),
        ));

        $reform->addElement('ValidationTextBox', 'dlzhostname', array(
            'required'          => true,
            'label'             => 'IP: ',
            'maxlength'         => 12,
            'regExp'            => $regexLib->exp->digits,
            'invalidMessage'    => 'Invalid characters in IP field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->digits.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzhostname'),
        ));

        $reform->addElement('ValidationTextBox', 'dlzdata', array(
            'required'          => true,
            'label'             => 'PTR (Host): ',
            'maxlength'         => 61,
            'regExp'            => $regexLib->exp->fullhostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->fullhostname.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzdata'),
        ));

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $reform->addElement($hf);
        }
        
        // We further ensure the form carries an entry type field (et) as well as
        // the service & zone dn.
        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(EMSDnsRecord::PTR_REC)
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $hfzdn = new Zend_Form_Element_Hidden('zonedn');
        $hfzdn->setValue(urlencode($zone->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $hfsdn = new Zend_Form_Element_Hidden('servicedn');
        $hfsdn->setValue(urlencode($service->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');
       
        $reform->addElement($hfet);
        $reform->addElement($hfzdn);
        $reform->addElement($hfsdn);

        return $reform;
    }
}

