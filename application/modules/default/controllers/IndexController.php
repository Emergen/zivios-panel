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
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class IndexController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function indexAction()
    {
        if ($this->_request->getParam('dn')) {
            $this->_helper->layout->disableLayout(true);
            $this->_helper->viewRenderer->setNoRender();
        }

        $this->_helper->viewRenderer->setNoRender();
        $this->view->user = Zivios_Ldap_Cache::loadDn($this->_session->user_dn);

        if (isset($this->_session->baseAdmin) && $this->_session->baseAdmin == true)  {
            $this->view->baseAdmin = true;
            $this->render();
        } else if ($this->view->user->pwexpired) {
            $this->view->baseAdmin = false;
            $this->_forward('logindialog','user');
        } else {
            $this->view->baseAdmin = false;
            $this->render('selfservice');
        }
    }
    
    
    public function subscribegrouptoldapAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $groupdn = "cn=booseter,ou=global groups,dc=zivios,dc=net";
        $group = Zivios_Ldap_Cache::loadDn($groupdn);
        $group->addPropertyItem('emsmodules','openldap');
        $group->addPropertyItem('emsplugins','OpenldapGroup');
        $group->addPropertyItem('emsservicemap','OpenldapGroup:cn=zivios directory,ou=master services,ou=core control,ou=zivios,dc=zivios,dc=net:OpenldapService');
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Openldap plugin to group '.$group->getdn());
        $tgroup = $handler->newGroup('Adding Openldap plugin to group '.$group->getdn(), Zivios_Transaction_Group::EM_SEQUENTIAL);
        $group->update($tgroup);
        $tgroup->commit();
        $this->processTransaction($handler);
        $this->sendResponse();
    }
    
    
    public function subscribeusertoldapAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $userdn = "uid=aststuff,ou=users,dc=zivios,dc=net";
        $user = Zivios_Ldap_Cache::loadDn($userdn);
        $user->addPropertyItem('emsmodules','openldap');
        $user->addPropertyItem('emsplugins','OpenldapUser');
        //$group->addPropertyItem('emsservicemap','OpenldapGroup:cn=zivios directory,ou=master services,ou=core control,ou=zivios,dc=zivios,dc=net:OpenldapService');
        $handler = Zivios_Transaction_Handler::getNewHandler('Adding Openldap plugin to user '.$user->getdn());
        $tgroup = $handler->newGroup('Adding Openldap plugin to user '.$user->getdn(), Zivios_Transaction_Group::EM_SEQUENTIAL);
        $user->update($tgroup);
        $tgroup->commit();
        $this->processTransaction($handler);
        $this->sendResponse();
    }
    
    public function moveAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->entry = Zivios_Ldap_Cache::loadDn(strip_tags(urldecode($this->getParam('dn'))));
    }
    
    public function domoveAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        
        if (!Zivios_Util::isFormPost('entrysearch')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['entrysearch']);
        

        $entry  = Zivios_Ldap_Cache::loadDn($formData->cleanValues['dn']);
        $entryParentdn = $entry->getParent()->getdn();
        
        $newparentdn  = $formData->cleanValues['newparentdn'];
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Move/Rename operation on '.$entry->getdn().' to '.$newparentdn);
        $tgroup = $handler->newGroup('Move operation on '.get_class($entry).'::'.$entry->getdn().' to '.$newparentdn, Zivios_Transaction_Group::EM_SEQUENTIAL);
        $entry->move(Zivios_Ldap_Cache::loadDn($newparentdn),$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);
        
       //$status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->addNotify('Entry Moved successfully');
            $this->refreshTreeNode($entryParentdn);
            $this->refreshTreeNode($newparentdn);
            $this->sendResponse();
        } else {
            throw new Zivios_Error('Error adding user to group. Please check system logs.');
        }
    }
    
    public function testAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        echo Zivios_Security::decrypt("ZJJzBprJkRc=");
        /*
        $mystart = microtime(true);
        $dn = Zivios_Ldap_Cache::loadDn("ou=Global Groups,dc=zivios,dc=net");
        $mystop = microtime(true);
        Zend_Debug::dump($dn);
        echo 'boo';
        echo '<br>Time :'.$mystop.' -- '.$mystart.' :: '.($mystop-$mystart);
        
        $group = new Zivios_Transaction_Group(369);
        $store= new Zivios_Transaction_Store($group);
        //$store->dump();
        Zivios_Log::debug("Call complete");
        */
        
    }
    
    
    public function reloadtreeAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $this->refreshTreeNode($this->getParam('dn'));
        $this->sendResponse();

    }
    
    public function fixcodeAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $dn = Zivios_Ldap_Cache::loadDn('dc=bankislami,dc=com,dc=pk');
        $entries = $dn->search('(&(emscode=*)(objectclass=emsorganizationalunit))',array('dn'),$dn->getdn(),'SUB',4000);
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $obj = Zivios_Ldap_Cache::loadDn($objdn);
            $obj->setProperty('emscode',strip_tags(trim($obj->getProperty('emscode'))));
            $obj->setProperty('postaladdress',strip_tags(trim($obj->getProperty('postaladdress'))));
            $obj->setProperty('facsimiletelephonenumber',strip_tags(trim($obj->getProperty('facsimiletelephonenumber'))));
            $obj->setProperty('telephonenumber',strip_tags(trim($obj->getProperty('telephonenumber'))));
            $tgroup = $handler->newGroup('Fixing Postal address and emscode',Zivios_Transaction_Group::EM_SEQUENTIAL );
            $obj->update($tgroup);
            $tgroup->commit();
        }
        $this->processTransaction($handler);
        $this->sendResponse();
        

        
    }
    
    public function updateallgroupsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $basedn = 'dc=bankislami,dc=com,dc=pk';
        //$basedn = 'dc=zivios,dc=net';
        $base = Zivios_Ldap_Cache::loadDn($basedn);
        $entries = $base->search('(objectclass=emsgroup)',array('dn','uid'),$basedn,'SUB',4000);
        //$handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        
        $c=0;
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $uid = $entries[$i]['uid'][0];
            $group = Zivios_Ldap_Cache::loadDn($objdn);
            $groupmod = array();
            $groupmod['emsmodules'] = 'openldap';
            $groupmod['emsplugins'] = 'OpenldapGroup';
            $groupmod['emsservicemap'] = 'OpenldapGroup:cn=zivios directory,ou=master services,ou=core control,ou=zivios,'.$basedn.':OpenldapService';
            try {
                $group->mod_add($groupmod);
                echo '<br>Successfully updated : '.$group->getdn();
            } catch (Exception $e) {
                echo '<br>Failed to update '.$group->getdn();
            }
        }
            
    }
    
    public function updateallusersAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $basedn = 'dc=bankislami,dc=com,dc=pk';
        //$basedn = 'dc=zivios,dc=net';
        $base = Zivios_Ldap_Cache::loadDn($basedn);
        $entries = $base->search('(objectclass=emsuser)',array('dn','uid'),$basedn,'SUB',4000);
        //$handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        
        $c=0;
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $uid = $entries[$i]['uid'][0];
            $user = Zivios_Ldap_Cache::loadDn($objdn);
            $usermod = array();
            $usermod['emsmodules'] = 'openldap';
            $usermod['emsplugins'] = 'OpenldapUser';
            try {
                $user->mod_add($usermod);
                echo '<br>Successfully updated : '.$user->getdn();
            } catch (Exception $e) {
                echo '<br>Failed to update '.$user->getdn();
            }
        }
            
    }
    
    public function makedepsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $basedn = 'ou=head office,dc=bankislami,dc=com,dc=pk';
        //$basedn = 'dc=zivios,dc=net';
        $base = Zivios_Ldap_Cache::loadDn($basedn);
        $entries = $base->search('(objectclass=emsuser)',array('dn','uid'),$basedn,'SUB',4000);
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        $other = new Zivios_Ldap_Engine();
        $other->connectToOtherLdap('cn=admin,dc=bankislami,dc=com,dc=pk','k98.#2Op','192.168.0.232');
        

        $c=0;
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            $uid = $entries[$i]['uid'][0];
            $user = Zivios_Ldap_Cache::loadDn($objdn);
            $oentry = $other->search('(uid='.$uid.')',array('dn'),'dc=bankislami,dc=com,dc=pk','SUB',4000);
            if ($oentry['count'] == 1) {
                $otherdn = $oentry[0]['dn'];
                $split = explode(',',$otherdn);
                $parent = $split[1];
                $parent = explode('=',$parent);
                $parent = $parent[1];
                $userc = $this->getHoDep($base,$parent,$handler);
                $tgroup = $handler->newGroup('Moving uid :'.$user->getdn().' to parent '.$userc->getdn(),Zivios_Transaction_Group::EM_SEQUENTIAL );
                $user->move($userc,$tgroup);
                $c++;
                $tgroup->commit();
                
                
                
            } else {
                Zivios_Log::error("********* COULD NOT FIND IN OTHER LDAP : ".$uid);
            }
            
            if ($c == 10) {
                    $this->processTransaction($handler);
                    echo '<br/>';
                    $this->sendResponse();
                    $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files take :'.$i);
                    $c=0;
                    
            }
        }
        $this->processTransaction($handler);
        echo '<br/>';
        $this->sendResponse();
            
            
    }
    
    public function getHoDep($ho,$dep,$handler)
    {        
        $container = 'ou='.$dep.','.$ho->getdn();
        try {
            $cobj = Zivios_Ldap_Cache::loadDn($container);
            return $cobj;
        } catch (Zivios_Exception $e) {
            //$handler = Zivios_Transaction_Handler::getNewHandler('Creating new user container');
            $tgroup = $handler->newGroup('Creating ou=users under'.$container,Zivios_Transaction_Group::EM_SEQUENTIAL );
            $userc = new EMSOrganizationalUnit();
            $userc->init();
            $userc->setProperty('emstype',EMSObject::TYPE_USERC);
            $userc->setProperty('cn',$dep);
            $userc->setProperty('emsdescription','User Container');
            $userc->add($ho,$tgroup);
            $tgroup->commit();
            return $userc;
        }
        
    }
    
    public function importdnsAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $array = file('/tmp/dnsentry.txt');
        //$zoneEntry = Zivios_Ldap_Cache::loadDn("dlzzonename=bankislami.com.pk,cn=zivios dns,ou=master services,ou=core control,ou=zivios,dc=bankislami,dc=com,dc=pk");
        $zoneEntry = Zivios_Ldap_Cache::loadDn("dlzzonename=zivios.net,cn=zivios dns,ou=master services,ou=core control,ou=zivios,dc=zivios,dc=net");
        $handler = Zivios_Transaction_Handler::getNewHandler('Bulk Importing DNS Entries');
        
        foreach ($array as $entry) {
            $explod = explode('|',$entry);
            $hostname = trim($explod[0]);
            $ip = trim($explod[1]);
            $nRecord = new EMSDnsRecord();
            $nRecord->init();
            $nRecord->setType(EMSDnsRecord::A_REC);
            $nRecord->setProperty('dlzhostname',$hostname);
            $nRecord->setProperty('dlzipaddr',$ip);
            $nRecord->setProperty('dlzttl','86400');
            
            $zoneRec = new EMSDnsHostName();
            $zoneRec->init();

            // Note: the hostname has been verified via form-update, we can
            // hence update the record directly here and add it to the transaction.
            $zoneRec->setProperty('dlzhostname', $hostname);
            // Add host name
            $tgroup = $handler->newGroup('Bulk importing for hostname : '.$hostname,Zivios_Transaction_Group::EM_SEQUENTIAL );
            $zoneRec->add($zoneEntry, $tgroup);           
            $nRecord->add($zoneRec, $tgroup);
            $tgroup->commit();
        }
        
        $this->processTransaction($handler);
        $this->sendResponse();
    }
    public function importlostAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $array = file('/tmp/notfound.txt');
        //$parentdn = 'ou=import,dc=zivios,dc=net';
        $parentdn = 'ou=import,dc=bankislami,dc=com,dc=pk';
        //$defaultgroup = 'cn=allusers,ou=global groups,dc=zivios,dc=net';
        $defaultgroup = 'cn=allusers,ou=global groups,dc=bankislami,dc=com,dc=pk';
        
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Lost Users email CSV Files');
        $other = new Zivios_Ldap_Engine();
        $other->connectToOtherLdap('cn=admin,dc=bankislami,dc=com,dc=pk','k98.#2Op','192.168.0.232');
        
        for ($i=0;$i<sizeof($array);$i++) {
            $uid = trim($array[$i]);
            Zivios_Log::debug("Reading UID :".$uid);
            $oentry = $other->search('(uid='.$uid.')',array(),'dc=bankislami,dc=com,dc=pk','SUB',4000);
            if ($oentry == null || sizeof($oentry) == 0) {
                Zivios_Log::error("UID ".$uid." Not found in foreign ldap");
            } else {
                Zivios_Log::debug($oentry);
                $obj = $oentry[0];
                
                $tgroup = $handler->newGroup('Importing dn '.$obj['dn'],Zivios_Transaction_Group::EM_SEQUENTIAL );
                $user = new EMSUser();
                $user->init();
                $user->import($tgroup,$obj,$parentdn,$defaultgroup);
                $tgroup->commit();
            }
        }
        
        $this->processTransaction($handler);
        echo '<br/>';
        $this->sendResponse();

    }
    
    public function biusermoveAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $array = file('/tmp/biusercodes.csv');
        
        $base = Zivios_Ldap_Cache::loadDn('dc=bankislami,dc=com,dc=pk');
        $importuserbase = Zivios_Ldap_Cache::loadDn('ou=import,dc=bankislami,dc=com,dc=pk'); 
        //$base = Zivios_Ldap_Cache::loadDn('dc=zivios,dc=net');
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        $c=0;
        for ($i=0;$i<sizeof($array);$i++) {
            
                $line = $array[$i];
            
                Zivios_Log::debug("line is ".$line);
                $split = explode('|',$line);
                if (sizeof($split) != 2) {
                    Zivios_Log::debug("SKipping Malformed Import line :".$line);
                } else {
                    $bid = trim($split[0]);
                    $usermail = trim($split[1]);
                        $filter = '(&(emscode='.$bid.')(objectclass=EMSOrganizationalUnit))';
                        $branch = $base->getAllChildren($filter);
                        if (sizeof($branch) > 1) {
                            echo 'Multiple branches match code :'.$bid;
                            Zivios_Log::info('Multiple branches match code :'.$bid);
                        } else if (sizeof($branch) == 0){
                            echo '<br>Branch code not found:'.$bid.'<br />';
                            Zivios_Log::info('Branch code not found:'.$bid);
                        } else {
                            
                            
                            $filter = '(&(mail='.$usermail.')(objectclass=EMSUser))';
                            $user = $importuserbase->getAllChildren($filter);
                            if (sizeof($user) > 1) {
                                echo 'Multiple users match email :'.$usermail;
                                Zivios_Log::info('Multiple users match email :'.$usermail);
                            } else if (sizeof($user) == 0) {
                                echo '<br>User not found:'.$usermail.'<br>';
                            } else {
                                $branch = $branch[0];
                                $user= $user[0];
                                if ($bid == 'HO')
                                    $userc = $this->getUserContainer($branch,$handler,true,$user->getParent());
                                else
                                    $userc = $this->getUserContainer($branch,$handler);
                                    
                                $c++;
                                $tgroup = $handler->newGroup('Moving uid :'.$user->getdn().' to parent '.$userc->getdn(),Zivios_Transaction_Group::EM_SEQUENTIAL );
                                $user->move($userc,$tgroup);
                                $tgroup->commit();
                            }
                        }
                }
                if ($c == 10) {
                    $this->processTransaction($handler);
                    echo '<br/>';
                    $this->sendResponse();
                    $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files take :'.$i);
                    $c=0;
                }
        }
        
        $this->processTransaction($handler);
        echo '<br/>';
        $this->sendResponse();

    }
    
    public function getUserContainer($branch,$handler,$isho=false,$userparent)
    {
        if ($isho) {
            $dept = $this->getHODepContainer($branch,$handler,$userparent);
            $branch = $dept;
        }
        
        $container = 'ou=users,'.$branch->getdn();
        try {
            $cobj = Zivios_Ldap_Cache::loadDn($container);
            return $cobj;
        } catch (Zivios_Exception $e) {
            //$handler = Zivios_Transaction_Handler::getNewHandler('Creating new user container');
            $tgroup = $handler->newGroup('Creating ou=users under'.$container,Zivios_Transaction_Group::EM_SEQUENTIAL );
            $userc = new EMSOrganizationalUnit();
            $userc->init();
            $userc->setProperty('emstype',EMSObject::TYPE_USERC);
            $userc->setProperty('cn','users');
            $userc->setProperty('emsdescription','User Container');
            $userc->add($branch,$tgroup);
            $tgroup->commit();
            return $userc;
        }
        
    }
    
    public function getHODepContainer($branch,$handler,$userparent)
    {
        $dep = $userparent->getProperty('cn');
        $container = 'ou='.$dep.','.$branch->getdn();
        try {
            $cobj = Zivios_Ldap_Cache::loadDn($container);
            return $cobj;
        } catch (Zivios_Exception $e) {
            //$handler = Zivios_Transaction_Handler::getNewHandler('Creating new user container');
            $tgroup = $handler->newGroup('Creating ou=users under'.$container,Zivios_Transaction_Group::EM_SEQUENTIAL );
            $userc = new EMSOrganizationalUnit();
            $userc->init();
            $userc->setProperty('emstype',EMSObject::TYPE_USERC);
            $userc->setProperty('cn','users');
            $userc->setProperty('emsdescription','User Container');
            $userc->add($branch,$tgroup);
            $tgroup->commit();
            return $userc;
        }
    }
    public function bimailupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $base = Zivios_Ldap_Cache::loadDn('dc=bankislami,dc=com,dc=pk');
        
        $entries = $base->search('(&(objectclass=emsuser)(!(mail=*bankislami.com.pk)))',array('dn','mail'),$base->getdn(),'SUB',4000);
        $handler = Zivios_Transaction_Handler::getNewHandler('Updating Multiple Users email CSV Files');
        for ($i=0;$i<$entries['count'];$i++) {
            $objdn = $entries[$i]['dn'];
            if (!preg_match('/bankislami.com.pk/',$entries[$i]['mail'])) {
                $child = Zivios_Ldap_Cache::loadDn($objdn);
            
                $tgroup = $handler->newGroup('Manual email set on '.$child->getProperty('uid'),Zivios_Transaction_Group::EM_SEQUENTIAL );
                Zivios_Log::debug('Updating mail for :'.$child->getProperty('uid'));
                $child->setProperty('mail',$child->getProperty('uid').'@bankislami.com.pk');
                $child->update($tgroup);
                $tgroup->commit();
            }
        }
        $status = $this->processTransaction($handler);
        $this->sendResponse();
        
        
    }
    
     public function searchdnAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        // unless a dn is specified for the search, we return
        // an empty array.
        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('no dn specified...');
            echo Zend_Json::encode(array());
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // check query for legality.
        if (null === ($query = $this->_request->getParam('q'))) {
            // ignore lookups till 'q' is sent
            echo Zend_Json::encode(array());
            return;
        } else {
            // proceed with group lookup if at least 3 characters
            // have come in the query.
            $query = trim(strip_tags($query));
            if (strlen($query) < 2) {
                echo Zend_Json::encode(array());
                return;
            }

            $ouentry  = Zivios_Ldap_Cache::loadDn($dn);
            $filter     = '(&(objectclass=EMSObject)(cn='.$query.'))';
                          
            $entries    = $ouentry->getAll($filter,'NOMODEL');
            $data  = array();
            if (is_array($entries) && !empty($entries)) {
                foreach ($entries as $entry) {
                    $data[urlencode($entry->getdn())] = $entry->getProperty('cn') . " | ".$entry->getdn();
                    
                }
                Zivios_Log::debug($data);
                
                // Get response and send to client.
                $response = $this->_helper->autoCompleteDojo
                                 ->prepareAutoCompletion($data);
                 Zivios_Log::debug($data);
                echo $response;
                return;

            } else {
                echo Zend_Json::encode(array());
                return;
            }
        }
    }
    
    public function groupmemberupdateAction()
    {
        $this->view->user = Zivios_Ldap_Cache::loadDn($this->_session->user_dn);
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        $lconf = Zend_Registry::get('ldapConfig');
        $basedn = $lconf->basedn;
        $placeholder = $lconf->placeholder;
        $groups = $this->view->user->getAllChildren('(objectclass=EMSGroup)','SUB',null,$basedn);
        $handler = Zivios_Transaction_Handler::getNewHandler('Redoing groups');
        
        
        foreach ($groups as $group) {
            $member = $group->getProperty('member');
            $group->setProperty('member',$placeholder);
            $remove = $handler->newGroup('removing users from group');
            $group->update($remove);
            $remove->commit();
            $group->setProperty('member',$member);
            $add = $handler->newGroup('adding users to group');
            $group->update($add);
            $add->commit();
            
        }
        
        
        $handler->commit();
        $handler->process();
        
        
    }

    /**
     * Add a custom container. Container type allows for all EMSObjects.
     *
     */
    public function addcustomcontAction()
    {
        $this->_helper->layout->disableLayout(true);

        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Invalid request -- missing "dn".');
        } else {
            $this->view->parentdn = strip_tags(urldecode($dn));
        }

        $this->render('add/custom');
    }

    public function doaddcustomAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('customcontainerdata')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['customcontainerdata']);
        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }

            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }

        // set parentdn
        $parentdn = $formData->cleanValues['dn'];

        $customEntry = new EMSOrganizationalUnit();
        $customEntry->init();

        $customEntry->setProperty('emstype', EMSOrganizationalUnit::TYPE_CUSTOM);
        $customEntry->setProperty('emsdescription','Custom Container');
        $customEntry->setViaForm($formData->cleanValues, array('dn'));
        
        // load parent dn
        $contParent = Zivios_Ldap_Cache::loadDn($parentdn);

        $handler = Zivios_Transaction_Handler::getNewHandler('Adding new custom container');
        $tgroup = $handler->newGroup('Adding new custom container',Zivios_Transaction_Group::EM_SEQUENTIAL );
        $customEntry->add($contParent,$tgroup);
        $tgroup->commit();
        $status = $this->processTransaction($handler);

        if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
            $this->refreshTreeNode($contParent->getdn());
            $this->addNotify('Custom container added successfully');
            $this->addCallback('zivios.cpaneRefresh', array('dirdata',
                                '/default/index/viewcustomcontainer/dn/' . $customEntry->getdn()));
        } else {
            throw new Zivios_Error('Error adding custom container. Please check system logs.');
        }

        $this->sendResponse();
    }

    public function viewcustomcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading user toolbar. DN not found in request.');
            echo "Error loading user toolbar";
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $ccEntry = Zivios_Ldap_Cache::loadDn($dn);

        $this->view->entry  = $ccEntry;
        $this->view->cctb01 = 'index/customcontainer/cctb01.phtml';
        $this->view->ccdash = 'index/customcontainer/ccdashboard.phtml';
        $this->render('zcustomcontainer');
    }

    public function deletecustomcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading delete custom container action.' .
                ' DN not found in request.');
            throw new Zivios_Error('Required data (dn) missing from request.');
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        // Load the entry
        $this->view->entry = Zivios_Ldap_Cache::loaddn($dn);
        $this->render('customcontainer/delete');
    }

    public function dodeletecustomcontainerAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (null === ($dn = $this->_request->getParam('dn'))) {
            Zivios_Log::debug('Error loading delete custom container action.' .
                ' DN not found in request.');
            throw new Zivios_Error('Required data (dn) missing from request.');
            return;
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $ccEntry  = Zivios_Ldap_Cache::loadDn($dn);
        $ccParent = $ccEntry->getParent();

        if ($this->_request->getParam('confirm') == 'true') {
            // Delete this locality
            $handler = Zivios_Transaction_Handler::getNewHandler('Deleting custom container');
            $tgroup = $handler->newGroup('Deleting custom container', Zivios_Transaction_Group::EM_SEQUENTIAL );
            $ccEntry->delete($tgroup);
            $status = $this->processTransaction($handler);

            if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                $this->refreshTreeNode($ccParent->getdn());
                $this->addDivData('dirdata', '<div class="note">Custom container deleted successfully</div>');
            } else {
                throw new Zivios_Error('Error deleting custom container. Please check system logs.');
            }
            $this->sendResponse();
        }
    }
        
    public function zdirectoryAction()
    {
        $this->_helper->layout->disableLayout(true);

        // Initialize openldap module paths.
        $config = Zend_Registry::get("appConfig");
        $modules = $config->modules;
        initLibraryPaths($modules, array('openldap'));
    }

    /**
     * Returns the context menu for a tree item upon a right click
     * event.
     *
     * @todo: Implement caching for menu config files: important!
     * @return string $menuJson
     */
    public function generatecontextmenuAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        // ensure the type & dn are available.
        if (null === ($type = $this->_request->getParam('type')) || 
            null === ($dn = $this->_request->getParam('dn'))) {
            return false;
        }

        // Currently we simply pull up the menu by type, however  we can check the dn & 
        // registered plugins as well to generate module-level context menus specific 
        // for the entry in question.
        $appConfig  = Zend_Registry::get('appConfig');
        $modulePath = $appConfig->modules;

        $menuDefs   = $modulePath . '/' . $this->_module . '/config/menudefs.php';
        $menuConfig = $modulePath . '/' . $this->_module . '/config/contextmenu.ini';

        if (!include_once($menuDefs)) {
            return false;
        }

        if (!file_exists($menuConfig)) {
            return false;
        }
        
        try {
            $menu = new Zend_Config_Ini($menuConfig, $type);
            $menuJson = Zend_Json::encode($menu->toArray());
        } catch (Exception $e) {
            return false;
        }

        $this->_response->appendBody($menuJson);
    }

    public function zlogsAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function ztransactionsAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function zhelpAction()
    {
        $this->_helper->layout->disableLayout(true);
    }

    public function getconsoledataAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        $_logs = new EMSLogs();
        $log = $this->_getParam('log');

        $consoleData = $_logs->getConsoleData($log);
        $consoleData = (string) $consoleData;

        // Return as json to caller.
        $this->_response->appendBody($consoleData);
        return;
    }

    public function logoutAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        // destroy all session data and remove user cookie
        Zend_Session::destroy(true);
        
        if (WEB_ROOT == '') {
            $redirectTo = '/';
        } else {
            $redirectTo = WEB_ROOT;
        }

        $response = array('logout' => '1', 'url' => $redirectTo);
        $response = Zend_Json::encode($response);
        $this->_response->appendBody($response);
        return;
    }
}

