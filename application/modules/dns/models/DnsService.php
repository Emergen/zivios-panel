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
 * @package     mod_dns
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class DnsService extends EMSService
{
    private   $_mastersrv, $_slavesrvs;
    protected $_module = 'dns';
    public    $mastercomp;

    public function init()
    {
        parent::init();

        $mcdn = $this->getProperty('emsmastercomputerdn');
        if ($mcdn != null) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
        } else {
            throw new Zivios_Exception('Core Service DNS missing attribute "Master Computer DN".');
        }
    }

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'emsdnsmastercomputerdn';
        $attrs[] = 'emsdnsserverreplicas';
        $attrs[] = 'emsdnsreplicationmodel';
        $attrs[] = 'emsdnsrootzone';

        parent::__construct($dn,$attrs,$acls);
        
        Zivios_Log::debug('initializing service config');
        $this->_initializeServiceConfig();
    }

    public function getRootZone()
    {
        $rootzone = $this->getProperty('emsdnsrootzone');
        $zones = $this->getAllChildren("(dlzzonename=$rootzone)",null,"ONE");
        if (sizeof($zones) == 1) {
            Zivios_Log::debug("Returning Zone!!");
            return $zones[0];
        } else {
            throw new Zivios_Exception("Root Zone not found.");
        }
    }

    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->addObjectClass('emsdnsservice');
        $handler = parent::add($parent,$tgroup,$description=null);

        // Add Root Default Zone!
        $handler = $this->addZone($this->getProperty('emsdnsrootzone'),$tgroup);
        return $handler;
    }

    public function getMasterComputer()
    {
        return Zivios_Ldap_Cache::loadDn($this->getProperty("emsdnsmastercomputerdn"));
    }

    public function addZone($zonename,$zonetype,Zivios_Transaction_Group $tgroup)
    {
        $zone = new EMSDnsZone();
        $zone->init();
        $zone->setProperty('cn',$zonename);
        $zone->setType($zonetype);
        $handler = $zone->add($this,$tgroup);
        return $handler;
    }

    public function getAllZones()
    {
        $zones = $this->getImmediateChildren();
        return $zones;
    }

    public function getZoneListing($options=array())
    {
        if (!empty($options)) {
            $filter    = null;
            $emsIgnore = false;
            $model     = null;
            isset($options['limit']) ? $limit = $options['limit'] : $limit = null;
        }

        $zones = $this->getImmediateChildren($filter, $emsIgnore, $model, $limit);
        return $zones;
    }    

    public function addARecord($hostname,$ip,Zivios_Transaction_Group $tgroup)
    {
        $hostnames = $this->getAllChildren("(dlzhostname=$hostname)",null,"ONE",$this->getRootZone()->getdn());
        if (sizeof($hostnames) == 1) {
        $hostname = $hostnames[0];
        } else if (sizeof($hostnames) > 1) {
            throw new Zivios_Exception("DNS error occured, multiple hostnames found, this should not be possible");
        } else {
            $dnshost = new EMSDnsHostName();
            $dnshost->init();
            $dnshost->setProperty('dlzhostname',$hostname);
            $handler = $dnshost->add($this->getRootZone(),$tgroup);

        }

        return $dnshost->addARecord($ip,$handler);
    }

    public function generateContextMenu()
    {
        return false;
    }

    public function getServerTime()
    {
        $this->_initCommAgent();
        return $this->_commAgent->currentTime();
    }

    public function getServiceStatus()
    {
        if ($this->pingZiviosAgent()) {
            $status = $this->_commAgent->status();
            Zivios_Log::debug('Service Status: ' . $status);
            return $status;
        } else {
            Zivios_Log::error('Zivios Agent appears to be off-line.');
            return false;
        }
    }

    public function startDns()
    {
        if ($this->pingZiviosAgent()) {
            return $this->_commAgent->startDns();
        } else {
            // Logging can be auto-handled by the EMSService model.
            Zivios_Log::error('Zivios Agent appears to be off-line.');
            return false;
        }
    }

    public function stopDns()
    {
        if ($this->pingZiviosAgent()) {
            return $this->_commAgent->stopDns();
        } else {
            Zivios_Log::error('Zivios Agent appears to be off-line');
            return false;
        }
    }
    
    /**
     * Returns a Zend_Dojo_Form object with elements for adding a forward
     * zone file.
     * 
     * @return object $azform;
     */
    public function getAddFwZoneForm()
    {
        $regexLib = $this->_getRegexLibrary();
       
        $azform = new Zend_Dojo_Form();
        $azform->setName('addfwzoneform')
                     ->setElementsBelongTo('addfwzone-form')
                     ->setMethod('post')
                     ->setAction('#');

        $azform->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'dl')),
            'Form',
        ));

        $azform->addElement('ValidationTextBox', 'cn', array(
            'required'          => true,
            'label'             => 'Domain Name: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->hostname,
            'invalidMessage'    => 'Invalid domain name specified.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->hostname.'/i')),
                               ),
        ));

        $hf = new Zend_Form_Element_Hidden('servicedn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $azform->addElement($hf);

        $azform->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Add Forward Zone',
            'onclick'     => "zivios.formXhrPost('addfwzoneform','/dns/service/addzonefile'); return false;",
        ));


        return $azform;
    }

    /**
     * Returns a Zend_Dojo_Form object with elements for adding a reverse
     * zone file.
     * 
     * @return object $azform;
     */
    public function getAddRvZoneForm()
    {
        $regexLib = $this->_getRegexLibrary();
       
        $azform = new Zend_Dojo_Form();
        $azform->setName('addrvzoneform')
                     ->setElementsBelongTo('addrvzone-form')
                     ->setMethod('post')
                     ->setAction('#');

        $azform->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'dl')),
            'Form',
        ));

        $azform->addElement('ValidationTextBox', 'cn', array(
            'required'          => true,
            'label'             => 'IP Subnet: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->subnet,
            'invalidMessage'    => 'Invalid subnet specified.',
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->subnet.'/i')),
                               ),
        ));

        $hf = new Zend_Form_Element_Hidden('servicedn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $azform->addElement($hf);

        $azform->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => 'Add Reverse Zone',
            'onclick'     => "zivios.formXhrPost('addrvzoneform','/dns/service/addzonefile'); return false;",
        ));

        return $azform;
    }

    public function getZoneSearchForm($dn = null)
    {
        if ($dn === null) {
            $dn = $this->getdn();
        }

        $szform = new Zend_Dojo_Form_SubForm();
        $szform->setAttribs(array(
            'name'          => 'searchavailablezonesform',
            'legend'        => 'Zone Search',
            'dijitParams'   => array(
                'title' => 'Zone Search',
            ),
        ));

        $hf = new Zend_Form_Element_Hidden('servicedn');
        $hf->setValue(urlencode($this->getdn()))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $szform->addElement($hf);

        $szform->addElement('FilteringSelect', 'zonesearch', array(
            'required'          => 'true',
            'invalidMessage'    => 'Zone not found.',
            'label'             => 'Search for Zone:',
            'storeId'           => 'autocompleter',
            'storeType'         => 'zivios.AutocompleteReadStore',
            'storeParams'       => array(
                    'url'           => '/dns/service/searchzones/dn/'.urlencode($dn).'/',
                    'requestMethod' => 'get',
                    ),
            'hasDownArrow'      => 'false',
        ));

        return $szform;
    }    
}
