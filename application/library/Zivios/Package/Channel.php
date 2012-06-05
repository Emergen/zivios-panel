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
 * @package     mod_package
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

abstract class Zivios_Package_Channel extends Zend_Db_Table
{
    protected $_name = 'channels';
    protected $_id = 'id';

    public $id,$name,$description,$type,$class,$baseurl,$baseport,$deb_section,$deb_dists,$rpm_mirrorlist,$arch,
            $enabled,$proxy_service_dn;

    const TYPE_DIRECT = "DIRECT";
    const TYPE_PROXY = "PROXY";

    public function __construct($id=null)
    {
        parent::__construct();
        $this->class = get_class($this);
        $this->enabled = 1;
        if ($id != null) {
            $this->id = $id;
            $this->revive($id);
        }
    }


    public function setData($name,$description,$baseurl,$type,$deb_section=null,$deb_dists=null,$arch,$rpm_mirrorlist=null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->baseurl = $baseurl;
        $this->type = $type;
        $this->deb_section = $deb_section;
        $this->rpm_mirrorlist = $rpm_mirrorlist;
        $this->deb_dists = $deb_dists;
        $this->arch = $arch;

    }

    public function revive($id)
    {
        $sql = "select * from channels where id=".$id;
        $row = $this->_db->fetchRow($sql);
        Zivios_Util::autoPopulateFromRows($this,$row);
    }

    public function write()
    {
        $data = array('name' => $this->name,
                    'description' => $this->description,
                    'type' => $this->type,
                    'class' => $this->class,
                    'baseurl' => $this->baseurl,
                    'deb_section' => $this->deb_section,
                    'deb_dists' => $this->deb_dists,
                    'rpm_mirrorlist' => $this->rpm_mirrorlist,
                    'enabled' => $this->enabled,
                    'arch' => $this->arch,
                    'proxy_service_dn' => $this->proxy_service_dn);
        $this->id = $this->insert($data);
    }

    abstract public function updatePackageList();

}
