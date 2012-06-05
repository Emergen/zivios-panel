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
 * @package		mod_default
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: EMSOlcSchema.php 911 2008-08-25 11:06:13Z fkhan $
 **/

/**
 * The folowing schema additions were performed to activate this class:

dn: cn={6}ems,cn=schema,cn=config
changetype: modify
add: olcObjectClasses
olcObjectClasses:  {35}( 11.1.2.2.39 NAME 'emsOlcSchema' DESC 'EMS Schema Entry Object' SUP emsSecurityObject AUXILIARY )

 *
 * and to modify one object itself:
 *
dn: cn={6}ems,cn=schema,cn=config
cangetype: modify
add: objectclass
objectclass: emsOlcSchema
-
add: emsdescription
emsdescription: EMS Olc Schema
-
add: emsmodelclass
emsmodelclass: EMSOlcSchema
-
add: emspermission
emspermission: NULL
-
add: emstype
emstype: OLC Schema Entry
 *
 */


/* Example usage:
		$emsolc = EMSOlcSchema::getOlcSchema();
		$attrib = $emsolc->quickaddAttributeType("12.5.4","TestAttrib","For testing Only","emsdescription");
		$handler = $emsolc->update();
		$handler->process();
*/

class EMSOlcSchema extends EMSObject
{
	public $attributearr,$objclassarr,$schemaname;

	public function __construct($dn=null,$attrs=null)
	{
	    $attrs[] = 'cn';
	    $attrs[] = 'olcattributetypes';
	    $attrs[] = 'oldobjectclasses';
	    
		parent::__construct($dn,$attrs);
	}
	
	public function init()
    {
        parent::init();
        
        $attribs = $this->getProperty('olcattributetypes');
        $objclasses = $this->getProperty('olcobjectclasses');


        foreach ($attribs as $attrib) {
            $this->attributearr[] = new Zivios_Ldap_SchemaAttribute($this,$attrib);
        }

        foreach ($objclasses as $objclass) {
            $this->objclassarr[] = new Zivios_Ldap_SchemaObjClass($this,$objclass);
        }
	}

	public function getrdn()
	{
		return "cn";
	}

	public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup=null)
	{
		
		if ($this instanceof EMSOlcSchema ) {
            parent::addObjectClass('olcSchemaConfig');
			//$this->_lobj->addItem('member',GROUP_PLACEHOLDER);
			//$this->_lobj->addItem('objectclass',array('olcSchemaConfig'));
		}
		return parent::add($parent,$tgroup);
	}

	public function quickaddAttributeType($oidfamily,$name,$desc,$sup)
	{
		$attrib = new Zivios_Ldap_SchemaAttribute($this);
		$attrib->name = $name;
		$attrib->desc = $desc;
		$attrib->sup = $sup;
		$attrib->prio = $this->getMaxAttribPrio()+1;
		$attrib->oidfamily = $oidfamily;
		$attrib->oidvalue = $this->getMaxOidAttrib($oidfamily)+1;
		$this->addAttribute($attrib);
		return $attrib;
	}

	public function quickAddObjectClass($oidfamily,$name,$desc,$sup,$mustarr=null,$mayarr=null,$structural=0,$auxiliary=0,$abstract=0)
	{
		$objclass = new Zivios_Ldap_SchemaObjClass($this);
		$objclass->name = $name;
		$objclass->desc = $desc;
		$objclass->sup = $sup;
		$objclass->prio = $this->getMaxObjClassPrio()+1;
		$objclass->oidfamily = $oidfamily;
		$objclass->oidvalue = $this->getMaxOidObjClass($oidfamily)+1;
		$objclass->mustarray = $mustarr;
		$objclass->mayarray = $mayarr;
		$objclass->structural = $structural;
		$objclass->abstract = $abstract;
		$objclass->aux = $auxiliary;
		$this->addObjectClass($objclass);
		return $objclass;
	}
	
	public static function getOlcSchema($schema="{11}zivios-core")
	{
		return Zivios_Ldap_Cache::loadDn("cn=$schema,cn=schema,cn=config",'EMSOlcSchema');
	}


	public function addOlcObjectClass(Zivios_Ldap_SchemaObjClass  $schema)
	{
		if (!$schema->isValid()) throw new Zivios_Exception("Attempt to add invalid schema objectclass");
		$param = $this->getParameter('olcobjectclasses');
		$param->addValue($schema->render());
		//return $this->update();

	}

	public function addAttribute(Zivios_Ldap_SchemaAttribute $schema)
	{
		if (!$schema->isValid()) throw new Zivios_Exception("Attempt to add invalid schema attribute");
		$param = $this->getParameter('olcattributetypes');
		$param->addValue($schema->render());
        $this->attributearr[] = $schema;
		//return $this->update();

	}
	public function getMaxOidAttrib($oidfamily)
	{
		$max = 0;
		foreach ($this->attributearr as $attrib) {
			if ($attrib->oidfamily == $oidfamily) {
				if ($attrib->oidvalue > $max) {
					$max = $attrib->oidvalue;
				}
			}
		}
		return $max;
	}

	public function getMaxAttribPrio()
	{
		$max =0;
		foreach ($this->attributearr as $attrib) {
			if ($attrib->prio > $max) {
				$max = $attrib->prio;

			}
		}
		return $max;
	}

	public function getMaxOidObjClass($oidfamily)
	{
		$max = 0;
		foreach ($this->objclassarr as $obj) {
			if ($obj->oidfamily == $oidfamily) {
				if ($obj->oidvalue > $max) {
					$max = $obj->oidvalue;
				}
			}
		}
		return $max;
	}

	public function getMaxObjClassPrio()
	{
		$max =0;
		foreach ($this->objclassarr as $obj) {
			if ($obj->prio > $max) {
				$max = $obj->prio;

			}
		}
		return $max;
	}




}