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

class EMSDnsZone extends EMSObject
{
    const TYPE_FORWARD = 'forward';
    const TYPE_REVERSE = 'reverse';

    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'dlzzonename';
        $attrs[] = 'emsdnszonetype';
        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();
    }

    public function setType($type)
    {
        $this->setProperty('emsdnszonetype',$type);
    }

    public function getType()
    {
        return $this->getProperty('emsdnszonetype');
    }

    public function getrdn()
    {
        return 'dlzzonename';
    }

    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->setProperty('emsdescription','DNS Zone Entry');
        $this->setProperty('emstype',EMSObject::TYPE_IGNORE);
        if (get_class($this) == 'EMSDnsZone') {
            $this->addObjectClass('dlzzone');
            $this->addObjectClass('emsdnszone');
            $this->addObjectClass('emsignore');
        }

         // Add a SOA and '@' Record here for this zone, fill with defaults
         // do the following ONLY if this is a forward zone!
        if ($this->getProperty('emsdnszonetype') == self::TYPE_FORWARD) {
            
            // The NS records are based on the primary name service managing
            // the zone. We hence look up the parent emsdnsrootzone attr for
            // this info.
            $primaryNs = 'ns1.' . $parent->getProperty('emsdnsrootzone') . '.';

            $this->setProperty('dlzzonename',$this->getProperty('cn'));
            $handler = parent::add($parent,$tgroup,$description);
            $dnshost = new EMSDnsHostName();
            $dnshost->init();
            $dnshost->setProperty('dlzhostname',"@");
            $handler =  $dnshost->add($this,$tgroup);
            $soarecord = new EMSDnsRecord();
            $soarecord->init();
            $soarecord->setType(EMSDnsRecord::SOA_REC);

            // The entries below should be user supplied and configurable.
            $soarecord->setProperty('dlzhostname','@');
            $soarecord->setProperty('dlzserial','1');
            $soarecord->setProperty('dlzrefresh','2800');
            $soarecord->setProperty('dlzretry','7200');
            $soarecord->setProperty('dlzminimum','86400');
            $soarecord->setProperty('dlzexpire','604800');
            $soarecord->setProperty('dlzadminemail','root.'.$this->getProperty('dlzzonename').'.');
            $soarecord->setProperty('dlzprimaryns',$primaryNs);
            $handler = $soarecord->add($dnshost,$tgroup);
        } else {
            // Calculate the DLZ Zone Name parameter from CN for reverse DNS zones
            $cn = $this->getProperty('cn');
            $octects = explode(".",$cn);
            $octects = array_reverse($octects);
            $dlzzone = implode(".",$octects);
            $dlzzone = $dlzzone . ".in-addr.arpa";
            $this->setProperty('dlzzonename',$dlzzone);
            $handler = parent::add($parent,$tgroup,$description);
        }

        return $handler;
    }

    public function removeZone(Zivios_Transaction_Group $tgroup, $description=null)
    {
        $tgroup = $this->deleteRecursive($tgroup, $description);
        return $tgroup;
    }

    public function addHostName($hostname,Zivios_Transaction_Group $tgroup)
    {
        $dnshost = new EMSDnsHostName();
        $dnshost->init();
        $dnshost->setProperty('dlzhostname',$hostname);
        return $dnshost->add($this,$tgroup);
    }

    public function getAllHosts()
    {
        $hostnames = $this->getAllChildren('(&(objectclass=emsdnshostname)(!(dlzhostname=@)))',null,'ONE');
        return $hostnames;
    }

    public function getAllRecords($type=null)
    {
        $filter = null;

        if ($type != null) {
            switch ($type) {
                case EMSDnsRecord::A_REC:
                    $dnstype = 'dlzarecord';
                    break;

                case EMSDnsRecord::MX_REC:
                    $dnstype = 'dlzmxrecord';
                    break ;
            
                case EMSDnsRecord::NS_REC:
                    $dnstype = 'dlznsrecord';
                    break;
            
                case EMSDnsRecord::SOA_REC:
                    $dnstype = 'dlzsoarecord';
                    break;

                case EMSDnsRecord::CNAME_REC:
                    $dnstype = 'dlzcnamerecord';
                    break;

                case EMSDnsRecord::GENERIC_REC:
                    $dnstype = 'dlzgenericrecord';
                    break;

                case EMSDnsRecord::PTR_REC:
                    $dnstype = 'dlzptrrecord';
                    break;

                case EMSDnsRecord::TXT_REC:
                    $dnstype = 'dlzgenericrecord';
                    $overrideFilter = true;
                    $cFilter = '(&(objectclass='.$dnstype.')(dlztype=TXT))';
                    break;

                case EMSDnsRecord::SRV_REC:
                    $dnstype = 'dlzgenericrecord';
                    $overrideFilter = true;
                    $cFilter = '(&(objectclass='.$dnstype.')(dlztype=SRV))';
                    break;

                default:
                    throw new Zivios_Exception('Invalid record type specified for DNS entry.');
            }
            
            if (!isset($overrideFilter)) {
                $filter = '(objectclass=' . $dnstype . ')';
            } else {
                $filter = $cFilter;
            }
        }

        return $this->getAllChildren($filter,0,'ONE');
    }

    public function getAllPtrRecords()
    {
        if ($this->getType() == self::TYPE_REVERSE) {
            return $this->getAllHosts();
        }
    }

    public function getMasterZoneHN()
    {
        return Zivios_Ldap_Cache::loadDn('dlzhostname=@,' . $this->getdn());
    }

    public function getOptionsForm($type, $service=null)
    {
        switch ($type) {
            case self::TYPE_FORWARD: 
                $dnsRecordi = new EMSDnsRecord();
                return $dnsRecordi->fwdZoneOpts($this->getProperty('cn'));
                break;

            case self::TYPE_REVERSE:
                if (null === $service) {
                    throw new Zivios_Exception('Missing initialized zone and service objects.');
                } else {
                    if (!$service instanceof DnsService) {
                        throw new Zivios_Exception('Unknown object type in call to getOptionsForm.');
                    }
                }

                $dnsRecordi = new EMSDnsPtrRecord();
                return $dnsRecordi->rvZoneOpts($this, $service);
                break;
        }
    }
}

