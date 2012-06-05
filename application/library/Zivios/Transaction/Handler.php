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
 * @package     Transaction
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Zivios_Transaction_Handler extends Zend_Db_Table
{
    public $description;

    protected $_name = 'transactions';
    protected $_primary = 'id';
    public $init_user, $status, $id,
            $rollbackmode, $flags, $pw, $refreshdn,$last_exception,$defer_index,$processcount,
            $dirty_trans_groups,$stats;

    const STATUS_READY='READY';
    const STATUS_FAILED='FAILED';
    const STATUS_WORKFLOW='PWORKFLOW';
    const STATUS_COMPLETED='COMPLETED';
    const STATUS_DEFERRED = 'DEFERRED';
    const STATUS_PARTIAL='PARTIAL';
    const STATUS_INIT="INITIALIZE";
    const STATUS_ROLLBACK_COMPLETE="RB_COMPLETE";
    const STATUS_ROLLBACK_FAILED="RB_FAILED";
    const STATUS_ROLLBACK_PARTIAL='RB_PARTIAL';
    const STATUS_ROLLBACK_RUNNING='RB_RUNNING';
    const STATUS_RUNNING="RUNNING";
    const STATUS_PAUSED="PAUSED";
    const FLAG_NORMAL="N";
    const FLAG_PRUNED="P";

    public function __construct($id=0)
    {
        parent::__construct();


        if ($id == 0) {
            $this->status = self::STATUS_INIT;
            $this->id = 0;
            $this->rollbackmode = 0;
            $this->dirty_trans_groups = array();
            $this->processcount = 0;

            /**
             * Initialize the user object for transaction.
             */
            $_userSession = new Zend_Session_Namespace("userSession");


            if (isset($_userSession->transinprocess)) {
                throw new Zivios_Exception("Cannot continue, This session already has an active Transaction!" .
                    " Check your code");
            }
            else
                $_userSession->transinprocess = uniqid(rand(),true);

			Zivios_Log::info("Going to transaction mode - transaction session unique id : ".$_userSession->transinprocess);
            $this->init_user = $_userSession->user_dn;
        }
        $this->deferred = 0;
        $this->id = $id;
    }

   

    
    public function getTransactionCount()
    {
        $sql = "select count(*) from transactions";
        $count = $this->_db->fetchOne($sql);
        return $count;
    }
    
    public function getAllTransactions($start=0,$limit=20)
    {
        $sql = "select id,user,status,description,deferred,created_ts,start_ts,stop_ts from transactions ".
                "order by id DESC limit $limit offset $start ";
        $rows = $this->_db->fetchAll($sql);
        $trans = array(); 
        foreach ($rows as $row) {
            $tr = new Zivios_Transaction_Handler(-1);
            $tr->fillBaseInformation($row);
            $trans[] = $tr;
        }
        return $trans;
    }
    
    public function fillBaseInformation($row)
    {
        $this->id = $row['id'];
        $this->init_user = $row['user'];
        $this->description = $row['description'];
        $this->deferred = $row['deferred'];
        $this->created_ts = new Zend_Date($row['created_ts'],Zend_Date::ISO_8601);
        $this->start_ts = new Zend_Date($row['start_ts'],Zend_Date::ISO_8601);
        $this->stop_ts = new Zend_Date($row['stop_ts'],Zend_Date::ISO_8601);
        $this->status = $row['status'];
    }
    
    public function getNextDeferredTransactionId()
    {
    	$sql = 'select id from transactions where status="'.self::STATUS_DEFERRED.'" order by processcount,id';
    	Zivios_Log::debug($sql);
    	$rows = $this->_db->fetchCol($sql);
    	if (sizeof($rows) > 0) {
	    	$id = $rows[0];
	    	Zivios_Log::debug("returning Transaction id ".$id);
	    	return $id;
    	} else {
    		Zivios_Log::debug("No deferred transaction, returning null");
    		return null;
    	}
    }

    public function setRollbackMode()
    {
        $this->last_exception = 0;
        $this->rollbackmode = 1;
    }

    public function isRollBack()
    {
        return $this->rollbackmode;
    }

    public function getAllGroupIds()
    {
        $sql = 'select id from trans_groups where transaction_id='.$this->id.' order by id';
        Zivios_Log::Debug("Fetch SQL Is " . $sql);
        $rows = $this->_db->fetchCol($sql);
        return $rows;
    }
    
    public function getGroup($id)
    {
        $group = new Zivios_Transaction_Group($id);
        $group->setHandler($this);
        $group->revive();
        return $group;
    }
    
    public function getAllGroups()
    {
        $sql = 'select id from trans_groups where transaction_id='.$this->id.' order by id';
        Zivios_Log::Debug("Fetch SQL Is " . $sql);
        $rows = $this->_db->fetchCol($sql);
        $groups = array();
        foreach ($rows as $row) {
            $group = new Zivios_Transaction_Group($row);
            $group->setHandler($this);
            $group->revive();
            $groups[] = $group;

        }
        Zivios_Log::debug("Done getting groups");
	
        return $groups;

    }

    public function routineGroupStatusUpdate()
    {
    	$this->update_status(self::STATUS_RUNNING);
    	$this->incrementProcessCount();

        $sql = 'select id from trans_groups where transaction_id='.$this->id.' and ' .
        '(status="'.Zivios_Transaction_Group::STATUS_DEFERRED.'" OR status = "'.
        Zivios_Transaction_Group::STATUS_RUNNING.'" OR status = "'.
        Zivios_Transaction_Group::STATUS_READY.'") order by id';

        $col = $this->_db->fetchCol($sql);

        /**
         * Ask groups to do a routine check on their items first!
         */
        if (sizeof($col) != 0) {
        	foreach ($col as $id) {
        		$group = new Zivios_Transaction_Group($id);
        		$group->revive();
        		$group->setHandler($this);
        		$group->routineItemStatusUpdate();
        	}
        }
	
	$this->

        /**
         * Repeat the earlier query, maybe the status update changed
         * status of a group!
         */

        $col = $this->_db->fetchCol($sql);

        if (sizeof($col) == 0) {
            //All groups have been accounted for! Status update!
            $this->markStop();
            $groups = $this->getAllGroups();
            $stats = $this->getStats($groups);
            Zivios_Log::debug("Stats for Transaction id ".$this->getId()." groups are as follows :");
            Zivios_Log::debug($stats);
            $this->generateStatus($stats);
            return;
        } else {


            //There maybe be READY groups to start up - Check!

            //first check if there are exactly ZERO deferred groups:
            $sql = 'select id from trans_groups where transaction_id='.$this->id.' and ' .
            '(status="'.Zivios_Transaction_Group::STATUS_DEFERRED.'" OR status = "'.
            Zivios_Transaction_Group::STATUS_RUNNING.'") order by id';

            $col = $this->_db->fetchCol($sql);
            if (sizeof($col) ==0) {

                //amazing, no deferred or RUNNING groups - this means that there MUST be
                //at least one READY group
                $sql = 'select id from trans_groups where transaction_id='.$this->id.' and ' .
                'status="'.Zivios_Transaction_Group::STATUS_READY.'" order by id';
                $col = $this->_db->fetchCol($sql);
                if (sizeof($col) == 0) {
                    throw new Zivios_Exception("Impossible state reached when trying to reactive group. No Ready Groups" .
                                            " found!");
                }
                $groups = array();
                foreach ($col as $id) {
                    $group = new Zivios_Transaction_Group($id);
                    $group->revive();
                    $group->setHandler($this);
                    $groups[] = $group;
                }

                if ($this->runGroups($groups) == -1)
                    return;
                $this->markStop();
                $stats = $this->getStats($groups);
                Zivios_Log::debug("Transaction Id " . $this->id . " Stats :");
                Zivios_Log::debug($stats);

                $this->generateStats($stats);
                return;
            }
        }
        /**
         * There is nothing to do - all groups are still deferred!
         */
        Zivios_Log::debug("Nothing to do with transaction id ".$this->getId()." - Deferring");
        $this->update_status(self::STATUS_DEFERRED);
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function isCommitted()
    {
        if ($this->status == self::STATUS_INIT)
            return 0;
        else return 1;
    }

    public function isNew()
    {
        return ($this->id == 0);
    }

    public function success_notification()
    {
        $nf = new Zivios_Notification();
        $description = "Transaction " . $this->id . " Successful";

        $nf->setData($description,
                     Zivios_Notification::TYPE_NOTICE,
                     $this->description);
        $nf->write();
    }

    public function failure_notification()
    {
        $nf = new Zivios_Notification();

        $description = "Transaction " . $this->id . " Failed";

        $nf->setData($description,
                     Zivios_Notification::TYPE_ERROR,
                     $this->description);
        $nf->write();
    }

    public function defer_notification()
    {
        $nf = new Zivios_Notification();

        $description = "Transaction " . $this->id . " deferred for background execution";

        $nf->setData($description,
                     Zivios_Notification::TYPE_NOTICE,
                     $this->description);
        $nf->write();
    }

    public function incrementProcessCount()
    {
    	$this->processcount++;
    	$data = array('processcount' => $this->processcount);
    	$this->update($data,'id='.$this->id);
    }

    public function onSuccessRefreshDn($dn)
    {
        $this->refreshdn = $dn;

    }

    public function getRefreshDn()
    {
        return $this->refreshdn;
    }

    public function write()
    {
        /**
         * write to mysql DB
         */

        $_userSession = new Zend_Session_Namespace("userSession");

        $pw = $_userSession->password;

        if ($this->rollbackmode)
            Zivios_Log::debug("In rollback mode, skipping write for Transaction");
        else {
            if ($this->id > 0)
                throw new Zivios_Exception("Transaction id ".$this->id." already written, will not re-write");

            $user = '';
            if ($this->init_user != null)
                $user = $this->init_user;
            else
                throw new Zivios_Exception("Null user passing to Transaction not supported, please pass a user next time");

            $data = array ('status' => $this->status,
                            'user' => $user,
                            'description' => $this->description,
                            'password' => $pw,
                            'deferred' => $this->deferred,
                            'refreshdn' => $this->refreshdn,
                            'created_ts' => null);
            $this->id = $this->insert($data);
//            $this->object_store->write();
        }
    }


    public function markStart()
    {
        $this->start_ts = Zend_Date::now();
        $data = array ('start_ts' => $this->start_ts->get(Zend_Date::ISO_8601));
        $this->update($data,'id = '.$this->id);
    }

    public function markStop()
    {
        $this->stop_ts = Zend_Date::now();
        $data = array ('stop_ts' => $this->stop_ts->get(Zend_Date::ISO_8601));
        $this->update($data,'id = '.$this->id);
    }

    public function update_status($status,$exception=null)
    {
        if ($this->isPruned())
            throw new Zivios_Exception("Cannot Operate on Pruned Transaction id ".$this->id);

        $this->status = $status;


        $data = array ('status' => $this->status);
        if ($exception != null)
        	$data['last_exception'] = serialize($exception);
        $this->update($data,'id = '.$this->id);

    }

    public function getTransactionCreds()
    {
        $rows = $this->_db->fetchRow('select user,password from transactions where id='.$this->id);
        if ($rows !== FALSE) {
            $creds = array();
            $creds['user'] = $rows['user'];
            $creds['password'] = $rows['password'];
            return $creds;
        }
        return false;
    }

    public function revive()
    {
        if (!Zivios_Ldap_Engine::hasAuth())
            throw new Zivios_Exception("Cannot reviving a transaction without a valid authenticated user!");

        $id = $this->id;
        $rows = $this->_db->fetchRow('select * from transactions where id='.$id);
        if ($rows !== FALSE) {
            $this->id = $id;
            $this->status = $rows['status'];
            $this->flags = $rows['flags'];
            $this->description = $rows['description'];
            $userdn = $rows['user'];
            $this->init_user = $userdn;
            $this->pw = $rows['password'];
            $this->deferred = $rows['deferred'];
            $this->refreshdn = $rows['refreshdn'];
            $this->processcount = $rows['processcount'];
            $this->last_exception = $rows['last_exception'];
            
   //         $this->object_store = new Zivios_Transaction_Store($this);
   //         $this->object_store->revive();

            $this->created_ts = new Zend_Date($rows['created_ts'],Zend_Date::ISO_8601);
            $this->stop_ts = new Zend_Date($rows['stop_ts'],Zend_Date::ISO_8601);
            $this->start_ts = new Zend_Date($rows['start_ts'],Zend_Date::ISO_8601);
            Zivios_Log::info("revived transaction id " . $id);
        }
    }

    public function isPruned()
    {
        return ($this->flags == self::FLAG_PRUNED);
    }


    /**
     * The process function returns a workflow if necessary on the said
     * transaction
     */

    public function process()
    {
        $this->commit();
        return $this->run();
    }

    public function commit()
    {
        if ($this->isPruned())
            throw new Zivios_Exception("Cannot Operate on Pruned Transaction id " . $this->id);

        
        $groups = $this->getAllGroups();
        //make sure the groups are all committed!
        
        foreach ($groups as $group) {
        	if($group->status != Zivios_Transaction_Group::STATUS_READY)
        	{
        	    throw new Zivios_Exception("Transaction group id :".$group->id." not committed ".
        	        "Status : ".$group->status);
        	}
        }
        
        $this->update_status(self::STATUS_READY);

        /**
         * since there is no workflow currently we dont really care!
         */
    }

/**
    public function externalReport($itemid,$successful,$error=null)
    {
        $_userSession = new Zend_Session_Namespace("userSession");
        $item = $this->codearray[$itemid];
        if ($successful) {
            Zivios_Log::info("Unpaused with Success result code. Continuing.");
            $item->markComplete();

            /**
            * Check if there are no more deferred ITEMS. If there are,
            * we need to stay paused
            */
/*
            if (!$this->hasDeferredItems($group)) {
                /**
                * No deferred items remaining!. unpause this transaction!
                */
/*
                $this->unpause($successful,$error);

            }


        } else {
            $item->markFailed();
            Zivios_Log::error("Transaction :" . $this->id . " Unpausing caused exception");
            Zivios_Log::error($error);
            unset($_userSession->transinprocess);
            $this->update_status(self::STATUS_FAILED);
            $this->failure_notification();
        }
    }

    public function hasDeferredItems($group)
    {
        $hasdeferred = 0;
        foreach ($this->codearray as $items) {
            if ($items->isDeferred()) {
                $hasdeferred = 1;
                break;
            }
        }
        return $hasdeferred;
    }

    public function unpause($successful=0,$error=null)
    {
        /**
        * This would be called when the paused transaction is woken up
        * Basically this is used to signal the status of the last code item
        * that was 'deferred'. This would be marked as failed or otherwise and we would
        * continue processing
        */
/*
        $_userSession = new Zend_Session_Namespace("userSession");
        $item = $this->codearray[$this->sleep_index];
        if ($successful) {
            Zivios_Log::info("Unpaused with Success result code. Continuing.");
            $this->run();
        } else {
            $item->markFailed();
            Zivios_Log::error("Transaction :" . $this->id . " Unpausing caused exception");
            Zivios_Log::error($error);
            unset($_userSession->transinprocess);
            $this->update_status(self::STATUS_FAILED);
            $this->failure_notification();
        }
    }
*/

	private function defer_transaction($deferindex)
	{
		$this->defer_index = $deferindex;
		$data = array ('defer_index' => $deferindex,
						'status' => self::STATUS_DEFERRED );
		$this->update($data,'id = '.$this->id);
	}
    public function run()
    {
        if ($this->isPruned())
            throw new Zivios_Exception("Cannot Operate on Pruned Transaction id ".$this->id);

        if ((!$this->isNew() && $this->status == self::STATUS_READY) || $this->rollbackmode) {
            $this->markStart();
            $this->update_status(self::STATUS_RUNNING);
            $_userSession = new Zend_Session_Namespace("userSession");
            $groups = $this->getAllGroupIds();

            Zivios_Log::debug("Got a Total of " . sizeof($groups) . " groups for this transaction ");
            if (sizeof($groups) ==0) {
                Zivios_Log::error("Zero Groups returned for Transaction ID : ".$this->id." Cannot proceed");
                $this->failure_notification();
                $this->markStop();
                $this->update_status(self::STATUS_FAILED);
                return;
            }


            if ($this->runGroups($groups) == -2)
                    return;

            $this->markStop();
            $stats = $this->getStats($groups);
            $this->stats = $stats;
            Zivios_Log::debug("Transaction Id " . $this->id . " Stats :");
            Zivios_Log::debug($stats);

            $this->generateStatus($stats);
            

        }
        else
        throw new Zivios_Exception("Transaction Not in READY state. Cannot Continue with current state: " .
                                $this->status);
    }
    
    public function getLastExceptionMessage()
    {
        $sql = "select i.last_exception from trans_groups as g,trans_items as i where g.transaction_id=".$this->id." and i.trans_group_id=g.id and i.last_exception IS NOT NULL";
        Zivios_Log::debug($sql);
        $trace = $this->_db->fetchOne($sql);
        Zivios_Log::debug($trace);
        $exp = explode("\n",$trace);
        Zivios_Log::debug($exp[0]);
        return $exp[0];
        
    }
    
    /**
     * Runs all groups in array and returns -1 if a deferred group is encountered, runs through
     * all groups otherwise
     *
     * @param unknown_type $groups
     * @return unknown
     */

    public function runGroups($groupids)
    {
        $_userSession = new Zend_Session_Namespace("userSession");
        foreach ($groupids as $key => $groupid) {
                $group = $this->getGroup($groupid);
            	$group->revive();
                try {
                	$group->run();
                	if ($group->getExecMode() == Zivios_Transaction_Group::EM_DEFERRED) {
                		$this->defer_transaction($key);
                		return -2;
                	}
                	$group->__destruct();
                }
                catch (Exception $e) {
                    
                    Zivios_Log::error("Transaction :" . $this->id . " caused Exception- Logging and re-throwing exception");
                    Zivios_Log::error($e->getTraceAsString());
                    $group->__destruct();
                    unset($_userSession->transinprocess);
                    $this->update_status(self::STATUS_FAILED,$e);
                    $this->markStop();
                    $this->failure_notification();
                    return -1;
                }

            }
            unset($_userSession->transinprocess);
            return 1;
    }

    public function generateStatus($stats)
    {
        if ($stats[Zivios_Transaction_Group::STATUS_COMPLETE] == $stats['total']) {
            	$this->update_status(self::STATUS_COMPLETED );
                $this->success_notification();
            } else if ($stats[Zivios_Transaction_Group::STATUS_DEFERRED] > 0) {
            	$this->update_status(self::STATUS_DEFERRED );
                $this->defer_notification();
            } else if ($stats[Zivios_Transaction_Group::STATUS_COMPLETE] > 0 && 
                    $stats[Zivios_Transaction_Group::STATUS_COMPLETE] < $stats['total']) {
            	$this->update_status(self::STATUS_PARTIAL );
                $this->failure_notification();
            } else {
                $this->update_status(self::STATUS_FAILED);
                $this->failure_notification();
            }
    }

    public function getStats($groupids)
    {
    	$stats = array();
    	$total= 0;

    	foreach ($groupids as $gid) {
    	    $group = $this->getGroup($gid);
    		$total++;
    		$status = $group->getStatus();
    		if (array_key_exists($status,$stats))
    			$stats[$status]++;
    		else
    			$stats[$status] = 1;

    	}

    	$stats['total'] = $total;
    	return $stats;
    }

    public function markFailed(Exception $e=null)
    {
    	$this->update_status(self::STATUS_FAILED,$e);
    }

    public function getUserDn()
    {
        return $this->init_user;
    }

    public function getUserPw()
    {
        return $this->pw;
    }

    public function prune()
    {
        if ($this->isPruned())
            throw new Zivios_Exception("Cannot Operate on Pruned Transaction id " . $this->id);

        $data = array ( 'itemarray' => null,
                        'password' => null,
                        'flags' => self::FLAG_PRUNED);

        $this->update($data,'id = '.$this->id);

        Zivios_Log::info("Pruned Transaction id " . $this->id);
    }

    public function newGroup($description,$execmode=null)
    {
    	$group = new Zivios_Transaction_Group();
    	$group->setHandler($this);
    	$group->setDescription($description);
    	//$this->dirty_trans_groups[] = $group;
    	if ($execmode == null)
    		$execmode = Zivios_Transaction_Group::EM_SEQUENTIAL ;
    	$group->setExecMode($execmode);
    	$group->add();
    	return $group;
    }

    public function rollback()
    {
        $this->setRollbackMode();

        if ($this->isPruned())
            throw new Zivios_Exception("Cannot Operate on Pruned Transaction id ".$this->id);

        if ($this->status == self::STATUS_FAILED || $this->status == self::STATUS_COMPLETED || $this->rollbackmode) {
            Zivios_Log::info("rolling back transaction id " . $this->id);
            /**
             * roll back in reverse order!!
             */
    	    $item = null;

        	$groups = $this->getAllGroups();
        	$groups = array_reverse($groups);
        	foreach ($groups as $group) {
        		$group->rollback();
        	}

        	$stats = $this->getStats($groups);
            Zivios_Log::debug("Transaction Id " . $this->id . " Stats :");
            Zivios_Log::debug($stats);

            foreach ($groups as $group) {
            	$group->__destruct();
            }
            
            if ($stats[Zivios_Transaction_Group::STATUS_ROLLBACK_COMPLETE] == $stats['total']) {
            	$this->update_status(self::STATUS_ROLLBACK_COMPLETE);
            } else {
            	$this->update_status(self::STATUS_ROLLBACK_FAILED);
            }
        }
        else
            throw new Zivios_Exception("Cannot Rollback Transaction, It MUST have Failed STatus");
    }


    public function getId()
    {
        return $this->id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function isDeferred()
    {
    	return $this->deferred;
    }
    
    public function setDeferred()
    {
        $this->deferred = 1;
    }
    

    public static function getNewHandler($description)
    {
    	$handler = new Zivios_Transaction_Handler();
        $handler->setDescription($description);
        $handler->write();
        return $handler;
    }
}
?>
