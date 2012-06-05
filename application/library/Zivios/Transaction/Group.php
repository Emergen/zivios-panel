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

class Zivios_Transaction_Group extends Zend_Db_Table
{
    protected $_name = 'trans_groups';
    protected $_primary = 'id';

    public $handler_id,$id,$exec_mode,$description,$status,
    		$dirty_trans_items,$handler,$start_ts,$stop_ts;

    public $object_store;
    
    const EM_DEFERRED = "Deferred";
    const EM_SEQUENTIAL = "Sequential";

    const STATUS_INIT="INITIALZE";
    const STATUS_READY="READY";
    const STATUS_DEFERRED="DEFERRED";
    const STATUS_RUNNING="RUNNING";
    const STATUS_COMPLETE = "COMPLETE";
    const STATUS_FAILED = "FAILED";
    const STATUS_PARTIAL = "PARTIAL";
    const STATUS_ROLLBACK_FAILED = "RBFAILED";
    const STATUS_ROLLBACK_COMPLETE = "RBCOMPLETE";
    const STATUS_ROLLBACK_RUNNING = "RBRUNNING";


    public function getId()
    {
    	return $this->id;
    }

    public function isNew()
    {
        return ($this->id == 0);
    }
    
    public function setHandler(Zivios_Transaction_Handler $handler)
    {
    	$this->handler = $handler;
    }

    public function getHandler()
    {
    	return $this->handler;
    }
    public function setDescription($desc)
    {
    	$this->description = $desc;
    }

    public function getDescription()
    {
    	return $this->description;
    }
    public function setExecMode($mode)
    {
    	$this->exec_mode = $mode;
    }

    public function getExecMode()
    {
    	return $this->exec_mode;
    }
    public function __construct($id=0)
    {
    	parent::__construct();
    	if ($id == 0) {
    		$this->status = self::STATUS_INIT ;
    		$this->exec_mode = self::EM_SEQUENTIAL;
    		$this->dirty_trans_items = array();
            $this->object_store = new Zivios_Transaction_Store($this);
    	} else {
    		$this->id = $id;
    	}

    }
    
    public function __destruct()
    {
        Zivios_Log::debug("*********DESTROYING TGROUP ID".$this->id." AND OBJECT STORE **********");
        if (isset($this->object_store)) {
            $this->object_store->__destruct();
            unset($this->object_store);
        }
        unset($this->handler);
    }

    public function newTransactionItem($description)
    {
        Zivios_Log::debug("Creating new transaction Item. Object store exists :".($this->object_store != null));
    	$titem = new Zivios_Transaction_Item();
    	$titem->setDescription($description);
    	$titem->setGroup($this);
    	$titem->add();
    	return $titem;
    }

    /**
     * Comitting a group effectively destroys it. This is to make VERY sure no memory leaks occur
    **/
    public function commit()
    {
    	$this->setStatus(self::STATUS_READY);
        $this->object_store->commit();
        $items = $this->getAllItems();

    	foreach ($items as $item) {
    	    if ($item->status != Zivios_Transaction_Item::STATUS_READY) {
    	        throw new Zivios_Exception("Transaction Item Id :".$item->id." Not committed. Statues :".
    	            $item->status);
    	    }
    	}
    	
    	$this->__destruct();
    }
    
    public function revive()
    {
    	 Zivios_Log::debug("Reviving Transaction Group : ".$this->id);
    	 $rows = $this->_db->fetchRow('select * from trans_groups where id='.$this->id);
    	 $this->handler_id = $rows['transaction_id'];
    	 $this->exec_mode = $rows['exec_mode'];
    	 $this->description = $rows['description'];
    	 $this->status = $rows['status'];
         $this->created_ts = new Zend_Date($rows['created_ts'],Zend_Date::ISO_8601);
         $this->stop_ts = new Zend_Date($rows['stop_ts'],Zend_Date::ISO_8601);
         $this->start_ts = new Zend_Date($rows['start_ts'],Zend_Date::ISO_8601);
         Zivios_Log::debug("Done Reviving");
    }

    public function getObjectStore()
    {
        return $this->object_store;
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
    public function add()
    {
    	Zivios_Log::debug("Starting Add");
    	$this->handler_id = $this->handler->getId();

    	$data = array ('transaction_id' => $this->handler_id,
    					'description' => $this->description,
    					'status' => $this->status,
    					'exec_mode' => $this->exec_mode);

		Zivios_Log::debug("About to add group with data ");
		Zivios_Log::debug($data);
		$this->id = $this->insert($data);
		Zivios_Log::debug("After Insert");
        //$this->object_store->write();

    }

    public function getAllItems($status=null)
    {
        $sql = 'select id from trans_items where trans_group_id='.$this->id;
        if ($status != null) {
            $sql .= " and status=".$this->status;
        }
        $sql .= ' order by id';
        Zivios_Log::debug("Internal Cache Miss. Running Sql : ".$sql);
        $rows = $this->_db->fetchCol($sql);
        $items = array();
        foreach ($rows as $row) {
            $item = new Zivios_Transaction_Item($row);
            $item->setGroup($this);
            $item->revive();
            $items[] = $item;
        }

        return $items;

    }

    public function getAllItemsForRetry()
    {
    	$sql = 'select id from trans_items where trans_group_id='.$this->id.' and retry=1';
		$rows = $this->_db->fetchCol($sql);
    	$items = array();
    	foreach ($rows as $row) {
    		$item = new Zivios_Transaction_Item($row);
    		$item->setGroup($this);
            $item->revive();
    		$items[] = $item;
    	}
    	$this->trans_items = $items;
    	return $items;
    }


    
    private function runItems($items)
    {
    	Zivios_Log::debug("Got a total of " . sizeof($items) . " for this Transaction ");
    	foreach ($items as $item) {
    		$item->run();
            if ($item->getStatus() == Zivios_Transaction_Item::STATUS_FAILED) {
                Zivios_Log::error("Item returned FAILED STATUS, Not processing other ITEMS");
                return;
            }

            if ($item->getStatus() != Zivios_Transaction_Item::STATUS_COMPLETE &&
                $this->getExecMode() == self::EM_SEQUENTIAL) {
                Zivios_Log::error("Item returned NON complete status for a SEQUENTIAL Group, cannot proceed");
                return;
            }
    	}


    }
    /**
    * This method is run by the cron routinely, checking groups which have a
    * DEFERRED status for items that have been reported successful.
    * If all items are reported successful then this Group is marked as complete or
    * Partial or failed
    *
    * This function DOES 
    NOT Update status until ALL items are marked as either
    * COMPLETE or FAILED
    */
    
    

    public function routineItemStatusUpdate()
    {


        $sql = 'select id from trans_items where trans_group_id='.$this->id.' and ' .
        '(status="'.Zivios_Transaction_Item::STATUS_DEFERRED.'" OR status = "'.
        Zivios_Transaction_Item::STATUS_AGENT_NORESPONSE.'" OR status = "'.
        Zivios_Transaction_Item::STATUS_RUNNING.'") order by id';

        $col = $this->_db->fetchCol($sql);

        $transConfig = Zend_Registry::get('transactionConfig');
        $defaulttimeout = $transConfig->itemtimeout;

        if (sizeof($col) == 0) {
            //All items are now accounted for - proceed with status update!
            $this->markStop();
            $items = $this->getAllItems();
            $stats = $this->getStats($items);
            $this->generateStatus($stats);
        } else {
            //Some items are STILL deferred or the Agent hasnt responded yet,
            //checked for start time and do TIMEOUTS
            foreach ($col as $id) {
                $item = new Zivios_Transaction_Item($id);
                $item->revive();
                $item->setGroup($this);
                $starttime = $item->start_ts;
                $starttime->add($defaulttimeout,Zend_Date::HOUR);
                $now = Zend_Date::now();
                if ($starttime->isEarlier($now)) {
                    //Timeout!!
                    $item->doTimeout();
                } else {
                    if ($item->getStatus() == Zivios_Transaction_Item::STATUS_AGENT_NORESPONSE) {
                        $item->run();
                    }
                }
            }
        }
    }

    public function getStats($items)
    {

    	$stats = array();
    	$failcount = $deferredcount = $completecount = $agentnoresponsecount = $total= 0;

    	foreach ($items as $item) {
    		$total++;
    		$status = $item->getStatus();
    		Zivios_Log::debug("Item description: ".$item->getDescription()."  status: ".$status);
    		if (array_key_exists($status,$stats))
    			$stats[$status]++;
			else
				$stats[$status] = 1;
    	}

    	$stats['total'] = $total;
    	return $stats;
    }

    private function reviveObjectStore()
    {
        if ($this->object_store == null) {
            $this->object_store = new Zivios_Transaction_Store($this);
            $this->object_store->revive();
        }
    }
    public function run()
    {
        Zivios_Log::debug("Group id ".$this->id." Starting EXECUTION");
    	if ($this->getStatus() != self::STATUS_READY)
    		throw new Zivios_Exception("Group id :" .$this->id. " must be in READY state to run");

        $this->markStart();
        
        // Object store would ONLY be null if this group was revived from DB
        // We only load the object store from the DB when we REALLY need it
        $this->reviveObjectStore();
        
        
    	$items = $this->getAllItems();
		$this->runItems($items);

		// Clean up memory!
        $this->closeObjects();
        
        
        
        
		/**
    	 * Check status of all items, if some are failed, change groups status!.
	 	 *
	 	 **/

		$stats = $this->getStats($items);

		Zivios_Log::debug("Transaction Group ". $this->id . " stats :");
		Zivios_Log::debug($stats);

    	if ($this->getExecMode() == self::EM_DEFERRED) {
    		$this->setStatus(self::STATUS_DEFERRED);
    	} else {
            $this->markStop();
			/**
			 * Lenient conditioning. Call partial for even a single success
			 */
             $this->generateStatus($stats);

    	}
    }

    public function generateStatus($stats)
    {
        if ($stats[Zivios_Transaction_Item::STATUS_COMPLETE] > 0 &&
					$stats[Zivios_Transaction_Item::STATUS_COMPLETE] < $stats['total']) {
				$this->setStatus(self::STATUS_PARTIAL);
			}
			else if ($stats[Zivios_Transaction_Item::STATUS_COMPLETE] == $stats['total']) {
				$this->setStatus(self::STATUS_COMPLETE );
			}
			else if ($stats[Zivios_Transaction_Item::STATUS_COMPLETE] == 0 &&
					$stats[Zivios_Transaction_Item::STATUS_DEFERRED] == 0) {
				$this->setStatus(self::STATUS_FAILED);
			}
			else if ($stats[Zivios_Transaction_Item::STATUS_COMPLETE] == 0 &&
					$stats[Zivios_Transaction_Item::STATUS_DEFERRED] > 0) {
				$this->setStatus(self::STATUS_DEFERRED);
			}
    }




    public function rollback()
    {
    	if ($this->getStatus() == self::STATUS_FAILED  || $this->getStatus() == self::STATUS_PARTIAL) {
            $this->reviveObjectStore();
    		$items = $this->getAllItems();
    		$items = array_reverse($items);
    		$this->setStatus(self::STATUS_ROLLBACK_RUNNING);
    		foreach ($items as $item) {
    			$item->rollback();
    		}
	    	/**
	    	 * Check status of all items, if some are failed, change groups status!.
		 	 *
		 	 **/

			$stats = $this->getStats($items);

			Zivios_Log::debug("Transaction Group ". $this->id . "Rollback stats :");
			Zivios_Log::debug($stats);

	    	if ($this->getExecMode() == self::EM_DEFERRED) {
	    		$this->setStatus(self::STATUS_DEFERRED);
	    	} else {
				/**
				 * Lenient conditioning. Call partial for even a single success
				 */
				if ($stats[Zivios_Transaction_Item::STATUS_ROLLBACK_COMPLETE ] +
					$stats[Zivios_Transaction_Item::STATUS_ROLLBACK_SKIPPED] == $stats['total']) {

						$this->setStatus(self::STATUS_ROLLBACK_COMPLETE);
				}
				else {
					$this->setStatus(self::STATUS_ROLLBACK_FAILED);
				}
	    	}



    	} else {
    		throw new Zivios_Exception("Cannot rollback transaction Group in state : ".$this->getStatus());
    	}
    }

    public function retry()
    {
    	if ($this->getStatus() != self::STATUS_PARTIAL) {
    		throw new Zivios_Exception("Only a group with STATUS PARTIAL can be resumed -- Group ID:" . $this->id .
    			" status : " . $this->getStatus());
    	}
    	$items = $this->getAllItemsForRetry();
    }

    public function setStatus($status)
    {
        $this->status = $status;
        $data = array('status' => $this->status);
        $this->update($data,'id = '.$this->id);
    }

    public function getStatus()
    {
    	return $this->status;
    }
    
    public function closeObjects()
    {
        $this->object_store->__destruct();
        unset($this->object_store);
    }
}
