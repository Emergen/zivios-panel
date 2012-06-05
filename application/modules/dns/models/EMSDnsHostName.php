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

class EMSDnsHostName extends EMSObject
{
    public function __construct($dn=null,$attrs=null,$acls=null)
    {
        if ($attrs == null)
            $attrs = array();

        $attrs[] = 'dlzhostname';

        parent::__construct($dn,$attrs,$acls);

    }

    public function getrdn()
    {
        return 'dlzhostname';
    }

    public function init()
    {
        parent::init();
    }

    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->setProperty('cn', $this->getProperty('dlzhostname'));
        $this->setProperty('emsdescription', 'DNS Host Name Entry');
        $this->setProperty('emstype', EMSObject::TYPE_IGNORE);

        if (get_class($this) == 'EMSDnsHostName') {
            $this->addObjectClass('dlzhost');
            $this->addObjectClass('emsdnshostname');
        }

        return parent::add($parent,$tgroup,$description);
    }

    public function addARecord($ipaddr,Zivios_Transaction_Group $tgroup)
    {
        $record = new EMSDnsRecord();
        $record->init();
        $record->setType('A');
        $record->setProperty('dlzipaddr',$ipaddr);
        return $record->add($this,$tgroup);
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
            }

            $filter = "(objectclass=$dnstype)";
        }

        return $this->getAllChildren($filter);
    }
}
