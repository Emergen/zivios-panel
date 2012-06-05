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

class Zivios_Transaction_Item extends Zend_Db_Table
{
    protected $_name = 'trans_items';
    protected $_primary = 'id';

    public $id,$codearray,$position,$groupid,$log,$min,$max,$progress,$agentretrycount,$retry,$agentlastretryts;
    public $rollbackarray, $objarray, $description, $status,$exception_index,$last_exception;
    public $created_ts,$start_ts,$stop_ts,$retry_ts;

    public $tgroup;

    private $deferred;

    const STATUS_FAILED="FAILED";
    const STATUS_COMPLETE="COMPLETE";
    const STATUS_READY="READY";
    const STATUS_ROLLBACK_SKIPPED="RB_SKIPPED";
    const STATUS_ROLLBACK_FAILED="RB_FAILED";
    const STATUS_ROLLBACK_COMPLETE="RB_COMPLETE";
    const STATUS_DEFERRED="DEFERRED";
    const STATUS_AGENT_NORESPONSE="AGENT_NR";
    const STATUS_RUNNING="RUNNING";
    const STATUS_RETRYING="RETRYING";
    const STATUS_INIT= "INITIALIZE";
    const STATUS_TIMEOUT = "TIMEOUT";

    const STATUS_ROLLBACK_DEFERRED=12;
    const STATUS_ROLLBACK_RUNNING=13;
    const STATUS_ROLLBACK_AGENT_NORESPONSE=14;

    public function __construct($id=0)
    {
    	parent::__construct();
    	if ($id == 0) {
	        $this->codearray = array();
	        $this->objarray = array();
	        $this->groupid = 0;
	        $this->rollbackarray = array();
	        $this->status = self::STATUS_INIT;
	        $this->deferred = 0;
	        $this->retry = 0;

    	} else {
    		$this->id = $id;
    	}
    }

    public function processExternalReport($successful,$error)
    {
        if ($successful) {
            $this->status = self::STATUS_COMPLETE;
            $this->markStop();
        	$this->saveState();
        } else {
            $this->status = self::STATUS_FAILED;
            $this->last_exception = $error;
            $this->markStop();
        	$this->saveState();
        }
    }

    public function setGroup(Zivios_Transaction_Group $group)
    {
    	$this->tgroup = $group;
    }

    public function commit()
    {
    	$this->status = self::STATUS_READY ;
    	$this->saveState();
    }

    public function isDeferred()
    {
    	return $this->deferred;
    }
    public function add()
    {
		$gid = $this->tgroup->getId();

		/**
		 * Only parallel groups would be allowed with deferred transactions. Make sure you do not push
		 * deferring transactions into a sequential group!!!
		 */
		if ($this->tgroup->getExecMode() == Zivios_Transaction_Group::EM_SEQUENTIAL)
			$this->deferred = 0;
		else
			$this->deferred = 1;

		if ($gid >0) {
			$this->groupid = $gid;

			$data = array('trans_group_id' => $this->groupid,
						'description' => $this->description,
						'status' => $this->status,
						'deferred' => $this->deferred,
						'created_ts' => null);

			$this->id = $this->insert($data);

		}
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($desc)
    {
    	$this->description = $desc;
    }

    public function getObject($name)
    {
    	$os = $this->getObjStore();
        return $os->get($this->id,$name);
    }

    public function getObjStore()
    {
    	return $this->tgroup->getObjectStore();
    }

    public function addObject($id,$object)
    {
    	$os = $this->getObjStore();
    	$os->put($this->id,$id,$object);
    }

    public function object_setup()
    {
    	$os = $this->getObjStore();
    	$objarray = $os->getArrayForItem($this->id);
    	foreach ($objarray as $key => $value)
    		$this->$key = $value;
    }

    public function getCodeArray()
    {
        return $this->codearray;
    }

    public function getId()
    {
    	return $this->id;
    }

    public function addCommitLine($line)
    {
        $bt = debug_backtrace();
        $class = $bt[2]['class'];
        $ln = "0";
        if (array_key_exists('line',$bt[1]))
            $ln = $bt[1]['line'];

        Zivios_Log::debug($class . " (" . $ln .") :::: code ::: " . $line);

        $this->codearray[] = $line;
    }

    public function addRollbackLine($line)
    {
        $this->rollbackarray[] = $line;
    }

    public function run()
    {
        /**
         * Setup objects first, their id's being variable names
         */

        $this->setStatus(self::STATUS_RUNNING);
        $this->markStart();

		$this->object_setup();

        try {
	        foreach ($this->codearray as $index => $code) {
	            Zivios_Log::debug('evaluating : ' . $code);

	            $retval = eval($code);
	            Zivios_Log::debug("eval returned " . $retval);

	            if ($retval === FALSE) {
	                Zivios_Log::error("Returning FALSE as eval outpt");
	                $this->status = self::STATUS_FAILED;
	                $this->saveState();
	                return FALSE;
	            }
	        }
        } catch (Zivios_Comm_Exception $e) {
        		Zivios_Log::exception($e);
        		if ($e->faultcode == Zivios_Comm_Exception::UNABLE_TO_CONNECT) {
        			$this->status = self::STATUS_AGENT_NORESPONSE;
        			$this->retry = 1;
				} else {
                    $this->status = self::STATUS_FAILED;
                    $this->retry = 0;
                }
                
                $eheading = $e->getMessage();
                $etrace = $e->getTraceAsString();
                $this->last_exception = $eheading . "\n" . $etrace;
                Zivios_Log::exception($e);
                $this->markStop();
                $this->saveState();
                return -1;
                
        } catch (Exception $e) {
        		Zivios_Log::exception($e);
    			$this->status = self::STATUS_FAILED;
    			$this->exception_index = $index;
                $eheading = $e->getMessage();
                $etrace = $e->getTraceAsString();
    			$this->last_exception = $eheading . "\n" . $etrace;
                Zivios_Log::exception($e);
                $this->markStop();
    			$this->saveState();
    			return -1;

        }

        if (!$this->isDeferred()) {
        	$this->status = self::STATUS_COMPLETE;
            $this->markStop();
        	$this->saveState();
        } else {
        	$this->status = self::STATUS_DEFERRED;
        	$this->saveState();
        }

        /**
         * Hardcoded check to return unless there is an exception
         */
        return 1;
    }

    public function saveState()
    {
    	$data = array(	'description' => $this->description,
						'status' => $this->status,
						'codearray' => serialize($this->codearray),
						'rollbackarray' => serialize($this->rollbackarray),
						'progress' => $this->progress,
						'deferred' => $this->deferred,
						'exception_index' => $this->exception_index,
						'last_exception' => $this->last_exception,
						'retry' => $this->retry);

		$this->update($data,'id = '.$this->id);
        //$this->getObjStore()->write();

    }

    public function getGroup()
    {
        if ($this->tgroup == null) {
            $this->tgroup = new Zivios_Transaction_Group($this->groupid);
            $this->tgroup->revive();
        }
        return $this->tgroup;
    }

    public function revive()
    {
    	 Zivios_Log::debug("Reviving Transaction Item id :".$this->id);
    	 $rows = $this->_db->fetchRow('select * from trans_items where id='.$this->id);
    	 $this->groupid = $rows['trans_group_id'];
    	 $this->description = $rows['description'];
    	 $this->deferred = $rows['deferred'];
    	 $this->codearray = unserialize($rows['codearray']);
    	 $this->rollbackarray = unserialize($rows['rollbackarray']);
    	 $this->last_exception = $rows['last_exception'];
    	 $this->min = $rows['min'];
         $this->status = $rows['status'];
    	 $this->max = $rows['max'];
    	 $this->progress = $rows['progress'];
    	 $this->retry = $rows['retry'];
    	 $this->agentlastretryts = $rows['agentlastretryts'];
    	 $this->agentretrycount = $rows['agentretrycount'];
         $this->created_ts = new Zend_Date($rows['created_ts'],Zend_Date::ISO_8601);
         $this->stop_ts = new Zend_Date($rows['stop_ts'],Zend_Date::ISO_8601);
         $this->start_ts = new Zend_Date($rows['start_ts'],Zend_Date::ISO_8601);
    }


    public function doTimeout()
    {
        $this->status = self::STATUS_TIMEOUT;
        $this->markStop();
        $this->saveState();
    }

    public function setStatus($status)
    {
        $this->status = $status;
        $data = array('status' => $this->status);
        $this->update($data,'id = '.$this->id);

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


    public function markFailed()
    {
        $this->setStatus(self::STATUS_FAILED);
    }

    public function markComplete()
    {
        $this->setStatus(self::STATUS_COMPLETE);
    }


    public function getStatus()
    {
        return $this->status;
    }

    public function rollback()
    {
        if ($this->status == self::STATUS_COMPLETE && sizeof($this->rollbackarray) > 0) {
            /**
     		* Setup objects first, their id's being variable names
     		*/

	        $this->setStatus(self::STATUS_ROLLBACK_RUNNING);
			$this->object_setup();
            Zivios_Log::debug("Rolling back item id : ".$this->id." description:::: ".$this->description);

	        try {
		        foreach ($this->rollbackarray as $index => $code) {
		            Zivios_Log::debug('evaluating : ' . $code);

		            $retval = eval($code);
		            Zivios_Log::debug("eval returned " . $retval);

		            if ($retval === FALSE) {
		                Zivios_Log::error("Returning FALSE as eval outpt");
		                $this->status = self::STATUS_ROLLBACK_FAILED;
		                $this->saveState();
		                return FALSE;
		            }
		        }
	        } catch (Zivios_Comm_Exception $e) {
	        		Zivios_Log::exception($e);
	        		if ($e->faultcode == Zivios_Comm_Exception::UNABLE_TO_CONNECT) {

	        			$this->status = self::STATUS_ROLLBACK_AGENT_NORESPONSE;
	        			$this->retry = 1;
                    } else {
                        $this->status = self::STATUS_ROLLBACK_FAILED;
                    }
                    
                    
                    $this->exception_index = $index;
                    $eheading = $e->getMessage();
                    $etrace = $e->getTraceAsString();
                    $this->last_exception = $eheading . "\n" . $etrace;
                    Zivios_Log::exception($e);
                    $this->saveState();
                    return -1;
					
	        } catch (Exception $e) {
	        		Zivios_Log::exception($e);
	    			$this->status = self::STATUS_ROLLBACK_FAILED;
	    			$this->exception_index = $index;
	    			$eheading = $e->getMessage();
                    $etrace = $e->getTraceAsString();
                    $this->last_exception = $eheading . "\n" . $etrace;
                    Zivios_Log::exception($e);
	    			$this->saveState();
	    			return -1;

        	}

	        if (!$this->isDeferred()) {
	        	$this->status = self::STATUS_ROLLBACK_COMPLETE ;
	        	$this->saveState();
	        } else {
	        	$this->status = self::STATUS_ROLLBACK_DEFERRED;
	        	$this->saveState();
	        }

	        /**
	         * Hardcoded check to return unless there is an exception
	         */
	        return 1;
        }
        else {
            $this->status = self::STATUS_ROLLBACK_SKIPPED;
            Zivios_Log::info("Item did not succeed or does not specify a rollback line, skipping rollback");
            $this->saveState();
            return 0;
        }
    }

    public function dump()
    {
        $objarray = array();
        foreach ($this->objarray as $key=>$obj)
            $objarray[$key] = get_class($obj);

        Zivios_Log::info("****************Failed Transaction Item Dump******************");
        Zivios_Log::info($this->objarray);
        Zivios_Log::info("Commit Array:::::::::::");
        Zivios_Log::info($this->codearray);
        Zivios_Log::info("Rollback Array::::::::");
        Zivios_Log::info($this->rollbackarray);

    }


}
?>
