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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class ImportController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }
    
    
    public function searchAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $basedn = $this->getParam('basedn');
        $filter = $this->getParam('filter');
        $ip = $this->getParam('ip');
        $authdn = $this->getParam('authdn');
        $authpass = $this->getParam('authpass');
        $scope = $this->getParam('scope');
        
        $otherengine = new Zivios_Ldap_Engine();
        if (!$otherengine->connectToOtherLdap($authdn,$authpass,$ip))
            throw new Zivios_Error("Unable to authenticate to foreign Ldap Server");
        
        Zivios_Log::debug("Scope is ".$scope);
        $entries = $otherengine->search($filter,array('dn','cn','objectclass'),$basedn,$scope);
        
        $this->view->dn = $dn;
        $this->view->ip = $ip;
        $this->view->authdn = $authdn;
        $this->view->authpass = $authpass;
        $this->view->entries = $entries;
        
        
    }
    
    public function bulkimportAction()
    {
        $this->_helper->layout->disableLayout(true);
        $dn = urldecode($this->getParam('dn'));
        $this->view->dn = $dn;
    }
    
    public function docsvimportAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        Zivios_Log::debug($_FILES);
        Zivios_Log::debug($_POST);
        $uploadedfile = $_FILES['uploadedfile']['tmp_name'];
        $converto = $this->getParam('convertto');
        $parentdn = urldecode($this->getParam('parentdn'));
        $parent = Zivios_Ldap_Cache::loadDn($parentdn);
        $array = file($uploadedfile);
        
        
        if ($converto == 'user') {
            $handler = Zivios_Transaction_Handler::getNewHandler('Import Multiple Users from CSV Files');
            $pgroupdn = urldecode($this->getParam('defaultgroup'));
            $pgroup = Zivios_Ldap_Cache::loadDn($pgroupdn);
            foreach ($array as $csvline) {
                $line = explode("|",$csvline);
                if (sizeof($line) != 10) {
                    Zivios_Log::error("Skipping Malformed Import CSV Line: ". $csvline);
                    
                } else {
                    Zivios_Log::debug("**************Import User***********");
                    Zivios_Log::debug($line);
                    
                    /* CSV Format should be :
                    *"Emp-id","Mail ID","First Name","Last Name","Home Address","Contact No","Department","Designation","Uid Number","Gid Number"
                    */
                    $user = new EMSUser();
                    $user->init();
                    $user->setProperty('employeenumber',$line[0]);
                    $user->setProperty('uid',$line[1]);
                    $user->setProperty('givenname',$line[2]);
                    $user->setProperty('sn',$line[3]);
                    
                    $user->setProperty('cn',$line[2].' '.$line[3]);
                    $user->setProperty('homepostaladdress',$line[4]);
                    $user->setProperty('telephonenumber',$line[5]);
                    $user->setProperty('departmentnumber',$line[6]);
                    $user->setProperty('employeetype',$line[7]);
                    
                    $uidnumber = $line[8];
                    $gidnumber = $line[9];
                    
                    if ($gidnumber == '') {
                        $user->setPrimaryGroup($pgroup);
                        Zivios_Log::debug("Setting Primary group (using default) as ".$pgroup->getProperty('gidnumber'));
                    }
                    else {
                        $searchgroup = new EMSGroup();
                        Zivios_Log::debug("Setting Primary group from line as ".$gidnumber);
                        $primaryGroup = $searchgroup->getGroupByGidNumber($gidnumber);
                        if ($primaryGroup == null) {
                            Zivios_Log::debug("Unable to find group from line :".$gidnumber);
                            $primaryGroup = $pgroup;
                        }
                        
                        $user->setPrimaryGroup($primaryGroup);
                    }
                    
                    $tgroup = $handler->newGroup('Importing uid '.$line[1],Zivios_Transaction_Group::EM_SEQUENTIAL );            
                    $kerberosUser = $user->newPlugin('KerberosUser');
                    $posixUser    = $user->newPlugin('PosixUser');
                    
                    $posixUser->setProperty('homedirectory','/home/'.$line[1]);
                    $posixUser->setProperty('uidnumber',$line[8]);
        
                    
                    $user->add($parent, $tgroup);
                    $user->addPlugin($kerberosUser, $tgroup);
                    $user->addPlugin($posixUser, $tgroup);
            
                    $user->changePassword('default', $tgroup);
                    $tgroup->commit();
    
                }
            }
        } else if ($converto == 'bcontainer') {
            // Convert to branches!!!
            $handler = Zivios_Transaction_Handler::getNewHandler('Import Multiple Branches from CSV');

            foreach ($array as $csvline) {
                $line = explode("|",$csvline);
                if (sizeof($line) != 5) {
                    Zivios_Log::error("Skipping Malformed Import CSV Line: ". $csvline);
                    
                } else {
                    Zivios_Log::debug("**************Import Branch***********");
                    Zivios_Log::debug($line);
                    $tgroup = $handler->newGroup('Importing branch '.$line[0],Zivios_Transaction_Group::EM_SEQUENTIAL );
                    $branch = new EMSOrganizationalUnit();
                    $branch->init();
                    $branch->setProperty('emstype',EMSObject::TYPE_BRANCH);
                    $branch->importcsv($tgroup,$line,$parentdn);
                    $tgroup->commit();

                    
                }
            }
        }
        
        $status = $this->processTransaction($handler);
       
      
        $json = $this->sendResponse(1);
        $this->_response->appendBody('<html><body><textarea>'.$json.'</textarea></body></html>');
 
    }
    
    public function dobulkimportAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        Zivios_Log::debug($_POST);
        $dn = urldecode($this->getParam('dn'));
        $ip = $this->getParam('ip');
        $authdn = $this->getParam('authdn');
        $authpass = $this->getParam('authpass');
        $convertto = $this->getParam('convertto');
        $importdns = $this->getParam('importdns');
        
        $otherengine = new Zivios_Ldap_Engine();
        if (!$otherengine->connectToOtherLdap($authdn,$authpass,$ip))
            throw Zivios_Error("Unable to authenticate to foreign Ldap Server");
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Import Multiple Foreign Ldap Entries');
        
        
        foreach ($importdns as $importdn) {
            $importdn = urldecode($importdn);
            $tgroup = $handler->newGroup('Importing dn '.$importdn,Zivios_Transaction_Group::EM_SEQUENTIAL );
            
            $entry = $otherengine->search('(objectclass=*)',array(),$importdn,'BASE');
            $entry = $entry[0];
           
            if ($convertto == 'user') {
                $defaultgroup = urldecode($this->getParam('defaultgroup'));
                $user = new EMSUser();
                $user->init();
                $user->import($tgroup,$entry,$dn,$defaultgroup);
            } else if ($convertto == 'group') {
                $group = new EMSGroup();
                $group->init();
                $group->import($tgroup,$entry,$dn);
            } else if ($convertto == 'bcontainer') {
                $branch = new EMSOrganizationalUnit();
                $branch->init();
                $branch->setProperty('emstype',EMSObject::TYPE_BRANCH);
                $branch->import($tgroup,$entry,$dn);
            } else if ($convertto == 'locality') {
                $branch = new EMSOrganizationalUnit();
                $branch->init();
                $branch->setProperty('emstype',EMSObject::TYPE_LOCALITY);
                $branch->import($tgroup,$entry,$dn);
            } else if ($convertto == 'userc') {
                $branch = new EMSOrganizationalUnit();
                $branch->init();
                $branch->setProperty('emstype',EMSObject::TYPE_USERC);
                $branch->import($tgroup,$entry,$dn);
            }
            else if ($convertto == 'custom') {
                $custom = new EMSOrganizationalUnit();
                $custom->init();
                $custom->setProperty('emstype',EMSObject::TYPE_CUSTOM);
                $custom->import($tgroup,$entry,$dn);
            }
            $tgroup->commit();
            
        }
        
        $status = $this->processTransaction($handler);

        $countdns = sizeof($importdns);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($dn);
            $this->addNotify($countdns." entries imported from Foreign Ldap Service successfully");
        } else if ($status == Zivios_Transaction_Handler::STATUS_PARTIAL) {
            $this->refreshTreeNode($dn);
            $this->addNotify($countdns." entries imported from Foreign Ldap Service <font color='red'>with errors</font>");
        }
        $this->sendResponse();
        
    }
}
