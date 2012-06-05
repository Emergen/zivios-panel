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

class EMSDnsRecord extends EMSObject
{
    private $type, $idused;

    const A_REC         = 'A';
    const MX_REC        = 'MX';
    const NS_REC        = 'NS';
    const SOA_REC       = 'SOA';
    const CNAME_REC     = 'CNAME';
    const PTR_REC       = 'PTR';
    const TXT_REC       = 'TXT';
    const SRV_REC       = 'SRV';
    const GENERIC_REC   = 'GENERIC';

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'dlzhostname';
        $attrs[] = 'dlzrecordid';
        $attrs[] = 'dlzttl';
        $attrs[] = 'dlztype';

        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();

        if (!$this->isNew()) {
            $this->setType($this->getProperty('dlztype'));
        }
    }

    public function setType($type)
    {
        $this->type = $type;
        switch ($this->type) {

            case self::A_REC:
                $this->addAttrs(array('dlzipaddr'));
                //$param = $this->getParameter('dlzipaddr');
                //$param->addValidator(new Zend_Validate_Ip(),'Ip Address');
                break;

            case self::MX_REC:
                $this->addAttrs(array('dlzdata','dlzpreference'));
                //$param = $this->getParameter('dlzdata');
                //$param->addValidator(new Zend_Validate_Alnum(),Zivios_Validate::errorCode2Array('alnum','MX Host'));
                //$param = $this->getParameter('dlzpreference');
                //$param->addValidator(new Zend_Validate_Digits(),'MX Preference');
                break;

            case self::NS_REC:
                $this->addAttrs(array('dlzdata'));
                break;

            case self::SOA_REC:
                $this->addAttrs(array('dlzdata','dlzserial','dlzrefresh','dlzexpire',
                                      'dlzminimum','dlzretry','dlzadminemail','dlzprimaryns'));
                break;
            case self::CNAME_REC:
                $this->addAttrs(array('dlzdata'));
                break;
                
            case self::GENERIC_REC:
                $this->addAttrs(array('dlzdata'));
                break;

            case self::PTR_REC:
                $this->addAttrs(array('dlzdata'));
                break;

            case self::TXT_REC:
                $this->addAttrs(array('dlzdata'));
                break;

            case self::SRV_REC:
                $this->addAttrs(array('dlzdata'));
                break;

            default: 
                throw new Zivios_Exception('Unknown record type specified in setType call.');
        }

        if ($this->isNew()) {
            $this->setProperty('dlztype',$type);
        }
    }

    public function getrdn()
    {
        return 'dlzrecordid';
    }

    public function makedn($parent)
    {
        return $this->getrdn().'='.$this->getProperty('dlzrecordid').','.$parent->getdn();
    }
    
    /**
     * Flawed logic in autopopulateid(). Record Ids need to be calculated from 
     * their relative parent. 
     * 
     * @todo: fix.
     */
    public function autopopulateid($parent)
    {
        $dn = $parent->getdn();
        // Search starting from 1, return id when found
        $i=1;
        $entries = $parent->getAllChildren("(dlzrecordid=$i)");
        while (sizeof($entries)>0) {
            $entries = $parent->getAllChildren("(dlzrecordid=$i)");
            if (sizeof($entries) == 0) {
                break;
            }
            $i++;
        }
        return $i;
    }

    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        if (get_class($this) == 'EMSDnsRecord') {
            $dnstype='ERROR';

            switch ($this->type) {
            case self::A_REC:
                $dnstype = 'dlzarecord';
                break;
            
            case self::MX_REC:
                $dnstype = 'dlzmxrecord';
                break ;

            case self::NS_REC:
                $dnstype = 'dlznsrecord';
                break;

            case self::SOA_REC:
                $dnstype = 'dlzsoarecord';
                break;
                
            case self::CNAME_REC:
                $dnstype = 'dlzcnamerecord';
                break;

            case self::GENERIC_REC:
                $dnstype = 'dlzgenericrecord';
                break;

            case self::TXT_REC:
                $dnstype = 'dlzgenericrecord';
                // Ensure dlzdata is further enclosed in quotation marks.
                $dlzdata = '"'.$this->getProperty('dlzdata').'"';
                $this->setProperty('dlzdata', $dlzdata);
                break;

            case self::SRV_REC:
                $dnstype = 'dlzgenericrecord';
                break;

            case self::PTR_REC:
                $dnstype = 'dlzptrrecord';
                break;
            }

            if ($this->getProperty('dlzhostname') == "") {
                $this->setProperty('dlzhostname', $parent->getProperty('dlzhostname'));
            }
            
            if ($this->getProperty('dlzttl') == '') {
                // set default ttl only if one has not (already) been set by the caller.
                $this->setProperty('dlzttl','500');
            }

            $this->setProperty('emsdescription','DNS Record Entry');
            $this->setProperty('emstype', EMSObject::TYPE_IGNORE);
            $this->setProperty('cn', $this->getProperty('dlzhostname'));

            if ($this->getProperty('dlzrecordid') == "") {
                $this->idused = $this->autopopulateid($parent);
                $this->setProperty('dlzrecordid',$this->idused);
            }

            $this->addObjectClass('emsdnsrecord');
            $this->addObjectClass($dnstype);
        }

        return parent::add($parent,$tgroup,$description);
    }
    
    /**
     * Wrapper method for record delete. Function additionally checks
     * if last record of a given host name is being removed and calls
     * remove on the parent accordingly.
     *
     * @return Zivios_Transaction_Group $tgroup
     */
    public function deleteRecord(Zivios_Transaction_Group $tgroup, $description=null)
    {
        // Check if last child is being removed from the parent, if so, ensure
        // the transaction group removes the parent as well.
        $parent       = $this->getParent();
        $children     = $parent->getImmediateChildren();
        $deleteParent = sizeof($children);

        // Delete child and call parent delete as required.
        $this->delete($tgroup, $description);

        if ($deleteParent == 1) {
            $tgroup = $parent->delete($tgroup);
        }
        
        // return transaction group
        return $tgroup;
    }

    public function fwdZoneOpts($zonecn)
    {
        // unique subform id based on cn of zone in question.
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zonecn));

        $oform = new Zend_Dojo_Form_SubForm();
        $oform->setAttribs(array(
            'name'          => 'dnsoptsform' . $strpCn,
            'legend'        => 'Add DNS Record',
            'dijitParams'   => array(
                'title' => 'Add DNS Record',
            ),
        ));
        
        $oform->addElement(
            'FilteringSelect',
            'dnslrt' . $strpCn, // list record type.
            array(
                'label'        => 'Record Type',
                'value'        => 'A',
                'autocomplete' => false,
                'multiOptions' => array(
                    self::A_REC       => 'A/AAAA',
                    self::MX_REC      => 'MX',
                    self::CNAME_REC   => 'CNAME',
                    self::TXT_REC     => 'TXT',
                    self::SRV_REC     => 'SRV',
                ),
            )
        );

        return $oform;
    }

    public function getEntryForm(DnsService $service, $type, EMSDnsZone $zone)
    {
        // Before we lookup the form type, we need to ensure record status and
        // set type in object for attribute pull.
        if ($this->isNew()) {
            $this->init();
            $this->setType($type);
        }

        switch ($type) {
            case self::A_REC:
                return $this->getAForm($service, $zone);

            case self::MX_REC:
                return $this->getMxForm($service, $zone);

            case self::CNAME_REC:
                return $this->getCnameForm($service, $zone);

            case self::SOA_REC:
                return $this->getSoaForm($service, $zone);

            case self::TXT_REC:
                return $this->getTxtForm($service, $zone);

            case self::SRV_REC:
                return $this->getSrvForm($service, $zone);

            default: 
                throw new Exception('Unknown record entry type defined in request.');
        }
    }
    
    protected function getSoaForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $soaform  = new Zend_Dojo_Form();
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'soa' . $ufid;
        
        // Set form properties.
        $soaform->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        $soaform->addElement('TextBox', 'domainname', array(
            'required'          => false,
            'disabled'          => true,
            'label'             => 'Domain Name: ',
            'maxlength'         => 61,
            'value'             => $zone->getProperty('cn'),
        ));

        $soaform->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

         $soaform->addElement(
            'FilteringSelect',
            'dlzrefresh',
            array(
                'label'        => 'Refresh Rate',
                'value'        => $this->getProperty('dlzrefresh'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

         $soaform->addElement(
            'FilteringSelect',
            'dlzretry',
            array(
                'label'        => 'Retry Rate',
                'value'        => $this->getProperty('dlzretry'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

         $soaform->addElement(
            'FilteringSelect',
            'dlzexpire',
            array(
                'label'        => 'Expire Time',
                'value'        => $this->getProperty('dlzexpire'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        // Add the existing record DN.
        $hf = new Zend_Form_Element_Hidden('recorddn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');
        $soaform->addElement($hf);            
        
        // We further ensure the form carries an entry type field (et) as well as
        // the service & zone dn.
        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::SOA_REC)
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
       
        $soaform->addElement($hfet);
        $soaform->addElement($hfzdn);
        $soaform->addElement($hfsdn);

        // return form.
        return $soaform;
    }

    protected function getAForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $aform  = new Zend_Dojo_Form();
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'a' . $ufid;
        
        // Set form properties.
        $aform->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        // existing entry hostnames cannot be edited.
        if ($this->isNew()) {
            $disableHost = false;
        } else {
            $disableHost = true;
        }

        $aform->addElement('ValidationTextBox', 'dlzhostname', array(
            'required'          => false,
            'disabled'          => $disableHost,
            'label'             => 'Host Name: ',
            'maxlength'         => 32,
            'regExp'            => $regexLib->exp->hostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->hostname.'/i')),
                                   ),
            'value'             => ($this->getProperty('dlzhostname')) == '@' ? '' : $this->getProperty('dlzhostname'),
        ));

        $aform->addElement('ValidationTextBox', 'dlzipaddr', array(
            'required'          => true,
            'label'             => 'IP Address: ',
            'maxlength'         => 15,
            'regExp'            => $regexLib->exp->ip,
            'invalidMessage'    => 'Invalid characters in ip address field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->ip.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzipaddr'),
        ));

        $aform->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $aform->addElement($hf);
        } else {
            // If an existing A record is being added, we include the option
            // of adding an additional IP to the existing host.
            $aform->addElement('Checkbox', 'addnew', array(
                'required'          => false,
                'label'             => 'Add IP: ',
                'checkedValue'      => '1',
                'uncheckedValue'    => '0',
                'checked'           => false,
            ));

            // Add the existing record DN.
            $hf = new Zend_Form_Element_Hidden('recorddn');
            $hf->setValue(urlencode($this->getdn()))
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $aform->addElement($hf);            
        }
        
        // We further ensure the form carries an entry type field (et) as well as
        // the service & zone dn.
        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::A_REC)
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
       
        $aform->addElement($hfet);
        $aform->addElement($hfzdn);
        $aform->addElement($hfsdn);
        
        // return form.
        return $aform;
    }

    protected function getMxForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $mform  = new Zend_Dojo_Form();
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'mx' . $ufid;
        
        // Set form properties.
        $mform->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        $mform->addElement('ValidationTextBox', 'dlzdata', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Host Name: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->fullhostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->fullhostname.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzdata'),
        ));

        $mform->addElement(
            'NumberSpinner',
            'dlzpreference',
            array(
                'value'             => ($this->getProperty('dlzpreference')) ? $this->getProperty('dlzpreference') : '10',
                'label'             => 'Preference',
                'smallDelta'        => 10,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 10,
                'max'               => 255,
                'places'            => 0,
                'maxlength'         => 3,
            )
        );

        $mform->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $mform->addElement($hf);
        } else {
            // Add the existing record DN.
            $hf = new Zend_Form_Element_Hidden('recorddn');
            $hf->setValue(urlencode($this->getdn()))
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $mform->addElement($hf);            
        }

        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::MX_REC)
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
       
        $mform->addElement($hfet);
        $mform->addElement($hfzdn);
        $mform->addElement($hfsdn);

        return $mform;
    }

    
    protected function getCnameForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $cform  = new Zend_Dojo_Form();
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'cname' . $ufid;
        
        // Set form properties.
        $cform->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        // existing entry hostnames cannot be edited.
        if ($this->isNew()) {
            $disableHost = false;
            $requireHost = true;
        } else {
            $disableHost = true;
            $requireHost = false;
        }

        $cform->addElement('ValidationTextBox', 'dlzhostname', array(
            'required'          => $requireHost,
            'disabled'          => $disableHost,
            'label'             => 'Host Name: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->hostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->hostname.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzhostname'),
        ));

        $cform->addElement('ValidationTextBox', 'dlzdata', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Aliases To: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->fullhostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->fullhostname.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzdata'),
        ));

        $cform->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $cform->addElement($hf);
        } else {
            // Add the existing record DN.
            $hf = new Zend_Form_Element_Hidden('recorddn');
            $hf->setValue(urlencode($this->getdn()))
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $cform->addElement($hf);            
        }

        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::CNAME_REC)
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
       
        $cform->addElement($hfet);
        $cform->addElement($hfzdn);
        $cform->addElement($hfsdn);

        return $cform;
    }

    protected function getTxtForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $form  = new Zend_Dojo_Form();
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'txt' . $ufid;
        
        // Set form properties.
        $form->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        // existing entry hostnames cannot be edited.
        if ($this->isNew()) {
            $disableHost = false;
            $requireHost = true;
        } else {
            $disableHost = true;
            $requireHost = false;
        }

        $form->addElement('ValidationTextBox', 'dlzhostname', array(
            'required'          => $requireHost,
            'disabled'          => $disableHost,
            'label'             => 'Name: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->hostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->hostname.'/i')),
                                   ),
            'value'             => $this->getProperty('dlzhostname'),
        ));

        $form->addElement('ValidationTextBox', 'dlzdata', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Value: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->alnumwithspaces,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/i')),
                                   ),
            'value'             => preg_replace('/\"/', '', $this->getProperty('dlzdata')),
        ));

        $form->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $form->addElement($hf);
        } else {
            // Add the existing record DN.
            $hf = new Zend_Form_Element_Hidden('recorddn');
            $hf->setValue(urlencode($this->getdn()))
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $form->addElement($hf);            
        }

        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::TXT_REC)
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
       
        $form->addElement($hfet);
        $form->addElement($hfzdn);
        $form->addElement($hfsdn);

        return $form;
    }

    protected function getSrvForm($service, $zone)
    {
        // Initialize regular expression library.
        $regexLib = $this->_getRegexLibrary();

        $form  = new Zend_Dojo_Form();
        $strpCn = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $ufid   = strtolower(preg_replace("/[^a-z0-9]/i", "", $zone->getProperty('cn')));
        $formId = 'srv' . $ufid;
        
        // Set form properties.
        $form->setName($formId)
                     ->setElementsBelongTo($formId)
                     ->setMethod('post')
                     ->setAction('#');

        if ($this->isNew()) {
            $disableHost = false;
            $requireHost = true;
            $serviceName = '';
            $targetName  = '';
            $protocol    = 'udp';
            $priority    = '10';
            $weight      = '5';
            $portNumber  = '80';
        } else {

            $disableHost = true;
            $requireHost = false;

            // get dlzdata and hostname and parse out required vals.
            $dlzhost = $this->getProperty('dlzhostname');
            $dlzdata = $this->getProperty('dlzdata');
            
            $dlzhost = explode('.', $dlzhost);
            $dlzdata = explode(' ', $dlzdata);

            $serviceName = $dlzhost[0];
            $targetName  = rtrim($dlzdata[3], '.');
            $protocol    = $dlzhost[1];
            $priority    = $dlzdata[0];
            $weight      = $dlzdata[1];
            $portNumber  = $dlzdata[2];
        }

        $form->addElement('ValidationTextBox', 'servicename', array(
            'required'          => $requireHost,
            'disabled'          => $disableHost,
            'label'             => 'Service: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->srvservice,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->srvservice.'/i')),
                                   ),
            'value'             => $serviceName,
        ));

        $form->addElement(
            'FilteringSelect',
            'protocol',
            array(
                'label'        => 'Protocol',
                'value'        => $protocol,
                'autocomplete' => false,
                'multiOptions' => array(
                    '_udp'       => 'udp',
                    '_tcp'       => 'tcp',
                    '_xmpp'      => 'xmpp',
                ),
            )
        );

        $form->addElement('ValidationTextBox', 'target', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Target: ',
            'maxlength'         => 62,
            'regExp'            => $regexLib->exp->fullhostname,
            'invalidMessage'    => 'Invalid characters in host name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->fullhostname.'/i')),
                                   ),
            'value'             => $targetName,
        ));

        $form->addElement(
            'NumberSpinner',
            'priority',
            array(
                'value'             => $priority,
                'label'             => 'Priority (0-255)',
                'smallDelta'        => 1,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 0,
                'max'               => 255,
                'places'            => 0,
                'maxlength'         => 3,
            )
        );

        $form->addElement(
            'NumberSpinner',
            'weight',
            array(
                'value'             => $weight,
                'label'             => 'Weight (0-255)',
                'smallDelta'        => 1,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 0,
                'max'               => 255,
                'places'            => 0,
                'maxlength'         => 3,
            )
        );

        $form->addElement(
            'NumberSpinner',
            'port',
            array(
                'value'             => $portNumber,
                'label'             => 'Port (1-65535)',
                'smallDelta'        => 1,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 1,
                'max'               => 65535,
                'places'            => 0,
                'maxlength'         => 5,
            )
        );

        $form->addElement(
            'FilteringSelect',
            'dlzttl',
            array(
                'label'        => 'TTL',
                'value'        => $this->getProperty('dlzttl'),
                'autocomplete' => false,
                'multiOptions' => array(
                    '300'       => '5 minutes',
                    '3600'      => '1 hour',
                    '7200'      => '2 hours',
                    '14440'     => '4 hours',
                    '28800'     => '8 hours',
                    '57600'     => '16 hours',
                    '86400'     => '1 day',
                    '172800'    => '2 days',
                    '345600'    => '4 days',
                    '604800'    => '1 week',
                    '1209600'   => '2 weeks',
                    '2419200'   => '4 weeks',
                ),
            )
        );

        if ($this->isNew()) {
            // add hidden field signifying a new record.
            $hf = new Zend_Form_Element_Hidden('nentry');
            $hf->setValue('1')
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $form->addElement($hf);
        } else {
            $form->addElement('Checkbox', 'addnew', array(
                'required'          => false,
                'label'             => 'Add as new: ',
                'checkedValue'      => '1',
                'uncheckedValue'    => '0',
                'checked'           => false,
            ));

            // Add the existing record DN.
            $hf = new Zend_Form_Element_Hidden('recorddn');
            $hf->setValue(urlencode($this->getdn()))
               ->removeDecorator('label')
               ->removeDecorator('HtmlTag');
            $form->addElement($hf);
        }

        $hfet = new Zend_Form_Element_Hidden('et');
        $hfet->setValue(self::SRV_REC)
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
       
        $form->addElement($hfet);
        $form->addElement($hfzdn);
        $form->addElement($hfsdn);

        return $form;
    }
}
