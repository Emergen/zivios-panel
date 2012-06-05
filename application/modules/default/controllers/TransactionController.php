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

class TransactionController extends Zivios_Controller
{
    const PAGELENGTH=10;
    
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }
    
    public function indexAction() 
    {
        $this->_helper->layout->disableLayout(true);
        $this->view->id = $this->getParam('id');
        
    }
    
    public function showformAction()
    {
        $this->_helper->layout->disableLayout(true);
    }
    
    public function showtransactionAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $id = $this->getParam('id');
        $this->addCallBack('zivios.showTransaction',array($id));
        //$this->addCallBack('zivios.loadApp',array('/transaction/index/id/'.$id,
        //                                    'ztabs','ztransactions',
         //                                   'Zivios Transactions'));
            
        //$this->addCallBack('zivios.loadApp',array('/transaction/transdetail/id/'.$id,
          //                          'transsrvtabs01','transdetail'.$id,'Transaction #'.$id));
        $this->sendResponse();
    }
    public function transactionlistAction()
    {
        $this->_helper->layout->disableLayout(true);
        $trans = new Zivios_Transaction_Handler(-1);
        
        $this->view->count = $trans->getTransactionCount();
        $this->view->maxpage = ceil($this->view->count / self::PAGELENGTH);
        
        $pagetodisp = $this->getParam('page');
        if ($pagetodisp == "") 
            $pagetodisp = 0;
        
        $start = $pagetodisp * self::PAGELENGTH;
        $end = $start + self::PAGELENGTH;
        
        
        $this->view->page = $pagetodisp;
        $this->view->trans = $trans->getAllTransactions($start,$end);
        
    }
    
    public function transdetailAction()
    {
         $this->_helper->layout->disableLayout(true);
         $id = $this->getParam('id');
         $trans = new Zivios_Transaction_Handler($id);
         $trans->revive();
         $this->view->trans = $trans;
         $this->view->user = Zivios_Ldap_Cache::loadDn($trans->init_user);
         $this->view->tgroups = $trans->getAllGroups();
         Zivios_Log::debug("All groups loaded");
         //echo 'hello';
         //exit(0);
         
    }
    
    public function showexceptionAction()
    {
        $this->_helper->layout->disableLayout(true);
        $id = $this->getParam('itemid');
        $item = new Zivios_Transaction_Item($id);
        $item->revive();
        $this->view->item = $item;
    }

    public function rollbackmeAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        $id = $this->getParam('id');
        $transaction = new Zivios_Transaction_Handler($id);
        $transaction->revive();
        $transaction->rollback();
        unset($transaction);
        Zivios_Log::debug("Out of rollback!");
        $transtabid = "transdetail$id";
       // $this->refreshPane($transtabid);
      
        $this->addNotify("Rollback Successfully Attempted for Transaction ID : $id");
        $this->refreshPane('transdetail'.$id,'/transaction/transdetail/id/'.$id);
        Zivios_Log::debug("Sending Response");
        $this->sendResponse();
        //Zivios_Log::debug("Response Sent!");
       // sleep(5);
       
        
    }
    
    
}
