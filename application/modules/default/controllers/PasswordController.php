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
 
class PasswordController extends Zivios_Controller
{
    
    
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }
    
    public function indexAction() 
    {
        $this->_helper->layout->disableLayout(true);
        
    }
    
    public function dobulkupdateAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        

        $passarray = file($_FILES['uploadedfile']['tmp_name']);
        $this->view->notexists = array();
        $this->view->malformed = array();
        $this->view->imported = array();
        $this->view->failed = array();
        
        
        foreach ($passarray as $line) {
            $user = new EMSUser();
            $exploded = explode(' ',trim($line));
            if (sizeof($exploded) == 2) {
                $user = $user->getByUid(trim($exploded[0]));
                if ($user != null) {
                    $handler = Zivios_Transaction_Handler::getNewHandler('Bulk Password Update for '.$user->getdn());
                    $tgroup = $handler->newGroup('Updating Password for '.$user->getdn(), Zivios_Transaction_Group::EM_SEQUENTIAL);
                    $user->changePassword(trim($exploded[1]),$tgroup);
                    $tgroup->commit();
                    $status = $this->processTransaction($handler);
        
                    // Check transaction status and update client accordingly.
                    if ($status == Zivios_Transaction_Handler::STATUS_COMPLETED) {
                        $this->view->imported[] = $user->getdn();
                    } else {
                        $this->view->failed[] = $user->getdn();
                    }
                    
                } else {
                    $this->view->notexists[] = trim($exploded[0]);
                }
                
            } else {
                $this->view->malformed[] = $line;
                Zivios_Log::error("Malformed line : ".$line);
            }
        }
        
        
        $content = "<hr>";
        $content .= "<div style='note notecl'><b>Bulk password update results</b></div><br />";
        $content .= '<br /><font color="green"> Successfully Updated </font><br />';
        foreach ($this->view->imported as $imp) {
            $content .= $imp.'<br />';
        }
        
        $content .= '<br /><font color="red"> Failed Updated </font><br />';
        foreach ($this->view->failed as $imp) {
            $content .= $imp.'<br />';
        }
        
        $content .= '<br /><font color="blue"> UID does not exist </font><br />';
        foreach ($this->view->notexists as $imp) {
            $content .= $imp.'<br />';
        }
        
        $content .= '<br /><font color="red"> Malformed Line </font><br />';
        foreach ($this->view->malformed as $imp) {
            $content .= $imp.'<br />';
        }
        
        $content .= "<br /><hr>";
            
        
        $this->addDivData('zpassupdate',$content);
        $json = $this->sendResponse(1);
        $this->_response->appendBody('<html><body><textarea>'.$json.'</textarea></body></html>');
        
        
    }
}

