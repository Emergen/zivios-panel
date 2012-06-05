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

class EMSOrganizationalUnit extends EMSObject
{
    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emscontainertype';
        $attrs[] = 'cn';
        $attrs[] = 'postaladdress';
        $attrs[] = 'telephonenumber';
        $attrs[] = 'facsimiletelephonenumber';
        $attrs[] = 'ou';
        $attrs[] = 'emscode';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();
        $param = $this->getParameter('cn');
        //$param->addValidator(new Zend_Validate_Alpha(1),
        //  Zivios_Validate::errorCode2Array('alpha','Container Name'));

        // We're exporting some additional parameters which may optionally
        // be used by organizational units.
        $param = $this->getParameter('postaladdress');
        //$param->addValidator(new Zend_Validate_Regex('/^[\d\w\s\-\.#,\/]+$/'),
        //    Zivios_Validate::errorCode2Array('regex', "Branch Address"));

        $param = $this->getParameter('telephonenumber');
        //$param->addValidator(new Zend_Validate_Regex('/^[\d\-]+$/'),
        //    Zivios_Validate::errorCode2Array('regex', "Telephone number"));

        $param = $this->getParameter('facsimiletelephonenumber');
        //$param->addValidator(new Zend_Validate_Regex('/^[\d\-]+$/'),
        //    Zivios_Validate::errorCode2Array('regex', "Fax number"));
    }

    public function move(Zivios_Ldap_Engine $newparent,Zivios_Transaction_Group $tgroup,$description=null,$parentmove=false) 
    {
        $children = $this->getImmediateChildren(null,null,'NOMODEL',null);
        
        Zivios_Log::debug("Moving dn :".$this->getdn()." to ".$newparent->getdn());
        
        parent::move($newparent,$tgroup,'Moving OU :'.$this->getdn().' to '.$newparent->getdn(),$parentmove);
        
        foreach ($children as $child) {
            Zivios_Log::debug("Calling Move on child :".$child->getdn());
            $child = Zivios_Ldap_Cache::loadDn($child->getdn());
            $child->move($this,$tgroup,null,true);
        }
    }
        
    
    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup,$description=null) {

        if (get_class($this) == 'EMSOrganizationalUnit') {
            $this->addObjectClass('emsOrganizationalUnit');
        }

        $this->addObjectClass('organizationalUnit');
        $this->addAttrs(array('ou'));
        $this->setProperty('ou',$this->getProperty('cn'));
        $this->setProperty('emscontainertype',$this->getProperty('emstype'));

        return parent::add($parent,$tgroup,$description);
    }

    protected function getrdn()
    {
        return 'ou';
    }

    /*public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        Zivios_Log::debug("Recursive delete at dn : ".$this->getdn());
        $children = $this->getImmediateChildren();
        if ($children != null)  {
            return parent::deleteRecursive($tgroup,$description);
        }
        else return parent::delete($tgroup,$description);

    }*/
    

    public function getAllGroups()
    {
        $this->checkType(EMSObject::TYPE_BRANCH);
        $filter = "(objectclass=EMSGroup)";
        return $this->getAllChildren($filter);
    }

    public function getAllUsers()
    {
        $this->checkType(EMSObject::TYPE_BRANCH);
        return $this->getAllChildren("(objectclass=EMSUser)");
    }
    
    /**
     * Returns an array of service entry dns for a given OU.
     * 
     * @param  array $serviceFilter
     * @return array $servicesIndex
     */
    public function getAvailableServices($servicesFilter = array())
    {
        if (!empty($servicesFilter)) {
            // construct filter for services.
            $sc = 0;
            $svcfilter = '';
            foreach ($servicesFilter as $service) {
                $svcfilter .= '('.$service.')';
                if ($sc <= sizeof($servicesFilter)) {
                    $svcfilter .= '|';
                }
                $sc++;
            }
        }
        
        Zivios_Log::debug($svcfilter);
        return;

		$lconf = Zend_Registry::get('ldapConfig');
		$aconf = Zend_Registry::get('appConfig');
		$containerFilter = "(emstype=ServiceContainer)";
		$serviceFilter = '(emstype=ServiceEntry)';
		$availableServices = array();
		$existingModules = $this->getModules(); 
		$parent = $this;
		$registeredServices = array();
		$c=0;

		while (1) {
			$result = $parent->getAllChildren($containerFilter,'ONE');

			if (!empty($result)) {
				Zivios_Log::debug("Found service container(s) in: ". $parent->getdn());
				foreach ($result as $serviceContainer) {
					Zivios_Log::debug("Looking for Services in Service Container: " .
						$serviceContainer->getProperty("cn"));

					foreach($serviceContainer->getAllChildren($serviceFilter, 'ONE') as $serviceEntry) {
						Zivios_Log::debug("Service Entry Found. Service Available: " .	$serviceEntry->getProperty("cn"));

						if (!in_array($serviceEntry->getProperty("cn"), $registeredServices)) {
							$registeredServices[] = $serviceEntry->getProperty("cn");
						} else {
							/**
							 * Service already registered -- this is required as computers, users &
							 * groups coming out of zivios core control will hit the forceful service
							 * scan in ou=Master Services.
							 */
							Zivios_Log::Debug("Skipping already registered service: " .
								$serviceEntry->getProperty("cn"));
							continue;
						}

						/**
						 * Initially we check the object type and ensure the module provides a
						 * configuration file for it.
						 */
						switch ($this->getProperty("emstype")) {
							case "UserEntry" :

							break;

							case "GroupEntry" :
							if (file_exists($aconf->modules .'/'. $serviceEntry->getProperty("emsmodulename")
								. '/config/group.ini')) {
                                if (!in_array($serviceEntry->getProperty("emsmodulename"), $existingModules)) {
									$availableServices[] = $serviceEntry;
                                    Zivios_Log::debug("Adding Service : ".$serviceEntry->getdn());
                                }
							}

							break;

							case "ServerEntry" :
							case "DesktopEntry" :
							/**
							 * For computer entries, we need to ensure that the module is compatible
							 * with the computer's os.
							 */
							if (file_exists($aconf->modules .'/'. $serviceEntry->getProperty("emsmodulename")
								. '/config/computer.ini')) {

								/**
								 * Load the INI file and figure out if the computer in question is
								 * compatible with the module.
								 */
								Zivios_Log::debug("computer.ini found for module: " .
									$serviceEntry->getProperty("emsmodulename"));

								$cInf = new Zend_Config_Ini($aconf->modules . '/' .
									$serviceEntry->getProperty("emsmodulename")	. '/config/computer.ini');

								$distros = explode(",", $cInf->distros->supported);
								$osCheck = $this->getComputerDistroId();

								if (in_array($osCheck, $distros)) {
									if (!in_array($serviceEntry->getProperty("emsmodulename"), $existingModules))
										$availableServices[] = $serviceEntry;
								}
							}

							break;

							default:
								throw new Zivios_Exception("Unknown EMS object");
						}
					}
				}
			}

			if (isset($zvForceBreak) && $zvForceBreak == 1) {
				// Reassign parent to baseDN & break.
				Zivios_Log::DEBUG("Reassigning Parent and breaking Loop.");
				$parent = $saveParent;
				unset($saveParent);
				break;
			}

			if ($parent->getdn() != $lconf->basedn) {
				$parent = $parent->getParent();
				continue;
			} else {
				// We've hit the base dn -- instantiate the following DN:
				// ou=Master Services, ou=Core Control, ou=Zivios, base_dn &
				// scan for services.
				$zvServDn = 'ou=Core Control,ou=Zivios,' . $lconf->basedn;
				$zvForceBreak = 1;
				$saveParent = $parent;
				$parent = Zivios_Ldap_Cache::loadDn($zvServDn);

				Zivios_Log::DEBUG("Making parent: " . $zvServDn);
				Zivios_Log::DEBUG("Continuing Search...");

				continue;
			}
		}

		return $availableServices;
    }

    private function checkType($type)
    {
        if ($type != $this->getProperty('emstype')) {
            throw new Zivios_Exception("type must be $type to use this function!");
        }
    }
    
    public function getAddServiceContainerForm($parentdn)
    {
        $regexLib = $this->_getRegexLibrary();
        
        $sform = new Zend_Dojo_Form_SubForm();
        $sform->setAttribs(array('name'   => 'servicecontainerdata'));

        $sform->addElement('ValidationTextBox', 'cn', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Service Container: ',
            'regExp'            => $regexLib->exp->alnumwithspaces,
            'invalidMessage'    => 'Invalid characters in service container name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/')),
                                   ),
            'value'             => $this->getProperty('cn'),
        ));

        $form = new Zend_Dojo_Form();
        $form->setName('addservicecontainer')
             ->setElementsBelongTo('addservicecontainer')
             ->setMethod('post')
             ->setAction('#');

        $form->addSubForm($sform, 'servicecontainerdata');

        // Add parentDN
        $hf = new Zend_Form_Element_Hidden('dn');
        $hf->setValue(urlencode($parentdn))
           ->removeDecorator('label')
           ->removeDecorator('HtmlTag');

        $form->addElement($hf);
        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Add Service Container',
            'onclick'     => "zivios.formXhrPost('addservicecontainer','default/service/doaddcontainer'); return false;",
        ));

        return $form;
    }
    
    public function setAddServerContainerForm($setVals)
    {
        $this->setProperty('emstype',EMSOrganizationalUnit::TYPE_SERVERC);
        $this->setProperty('emsdescription','Server Container');
        $this->setViaForm($setVals, array('dn'));
    }

    public function setAddDesktopContainerForm($setVals)
    {
        $this->setProperty('emstype',EMSOrganizationalUnit::TYPE_DESKTOPC);
        $this->setProperty('emsdescription','Desktop Container');
        $this->setViaForm($setVals, array('dn'));
    }    

    public function setAddServiceContainerForm($setVals)
    {
        $this->setProperty('emstype',EMSOrganizationalUnit::TYPE_SERVICEC);
        $this->setProperty('emsdescription','Service Container');
        $this->setViaForm($setVals, array('dn'));
    }
    
    public function setAddUserContainerForm($setVals)
    {
        $this->setProperty('emstype',EMSOrganizationalUnit::TYPE_USERC);
        $this->setProperty('emsdescription','User Container');
        $this->setViaForm($setVals, array('dn'));
    }    
    
    public function setAddGroupContainerForm($setVals)
    {
        $this->setProperty('emstype',EMSOrganizationalUnit::TYPE_GROUPC);
        $this->setProperty('emsdescription','Group Container');
        $this->setViaForm($setVals, array('dn'));
    }
    
    public function setDesktopContainerForm($valarray)
    {
        $this->setForm($this->getDesktopContainerForm(),$valarray);
    }
    
    public function import(Zivios_Transaction_Group $tgroup,$entry,$parentdn)
    {
        $parent = Zivios_Ldap_Cache::loadDn($parentdn);
        $cn =$entry['cn'][0];
        $ou = $entry['ou'][0];
        $l = $entry['l'][0];
        
        if ($cn == null || $cn == "") {
            if ($ou != null && $ou != "") 
                $cn = $ou;
            else if ($l != null && $l != "")
                $cn = $l;
            else
                throw new Zivios_Exception("Cannot import entry with dn :".$entry['dn']." no cn,ou or l parameters found");
        }
        
        $this->setProperty('cn',strip_tags(trim($cn)));
        $this->setProperty('postaladdress',strip_tags(trim($entry['postaladdress'][0])));
        $this->setProperty('telephonenumber',strip_tags(trim($entry['telephonenumber'][0])));
        $this->setProperty('facsimiletelephonenumber',strip_tags(trim($entry['facsimiletelephonenumber'][0])));
        $this->add($parent,$tgroup);
    }
    
    public function importcsv(Zivios_Transaction_Group $tgroup,$line,$parentdn)
    {
        $parent = Zivios_Ldap_Cache::loadDn($parentdn);
        $cn =$line[0];
        
        $this->setProperty('cn',$cn);
        
        $this->setProperty('telephonenumber',$this->cleanPhone(strip_tags(trim($line[1]))));
        $this->setProperty('facsimiletelephonenumber',$this->cleanPhone(strip_tags(trim($line[2]))));
        $this->setProperty('postaladdress',strip_tags(trim($line[3])));
        $this->setProperty('emscode',strip_tags(trim($line[4])));
        $this->add($parent,$tgroup);
    }
    
    public function cleanPhone($phone)
    {
        $phone = str_replace("(","",$phone);
        $phone = str_replace(")","",$phone);
        $phone = str_replace(".","-",$phone);
        return $phone;
    }
}

