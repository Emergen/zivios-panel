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
 * @subpackage  Core
 **/

class Zivios_Ldap_Cache extends Zivios_Ldap_Engine
{
    private $cache_conn, $cacheConfig;

    public function __construct($dn=null,$attr=null,$acls=null)
    {
        if ($attr == null)
            $attr = array();

        $this->cacheConfig = Zend_Registry::get('cacheConfig');

        $attr[] = 'modifytimestamp';
        //$this->cache_conn = new Memcache();
        //$this->init_cache();
        parent::__construct($dn,$attr,$acls);
    }

    private static function init_cache()
    {
        $cacheConfig = Zend_Registry::get('cacheConfig');

        $cache_conn = new Memcache();

        if (!$cache_conn->connect($cacheConfig->host,$cacheConfig->port)) {
            throw new Zivios_Exception("Unable to connect to memcached at host: " . $cacheConfig->host .
                " and port: " . $cacheConfig->port);
        }

        return $cache_conn;
        //$this->setMode();
    }

    public static function loadDn($dn,$classname=null)
    {
        /**
         * check object in cache!
         */
        
        /**
         * Enforce lower case dns, important to prevent case mismatches
         */
        $dn = strtolower($dn);
        //$cache = new Zivios_Ldap_Cache();
        //$cache->setMode();
        $_userSession = new Zend_Session_Namespace("userSession");
        $transmode = 0;
        if (isset($_userSession->transinprocess) && $_userSession->transinprocess > 0) {
            $transmode = 1;
            Zivios_Log::info("Cache operative in Transaction mode for id :".$_userSession->transinprocess);
        }
        
        
        if ($transmode)
            return self::loadDnTransMode($dn,$classname);
        else
            return self::loadDnNormal($dn,$classname);
        
    }

    private static function loadDnTransMode($dn,$classname=null)
    {
        $cache = new Zivios_Ldap_Cache();
        $start = microtime(true);
        $obj = $cache->checkInTransCache($dn);
        if ($obj != null) {
            if (!$obj->checkClassCoherence($classname)) {
                Zivios_Log::debug("Class Coherence check failed in Cache-Transaction Mode, Check Code!");
                return self::loadDnNormal($dn,$classname);
            }

            $objhash = spl_object_hash($obj);

            Zivios_Log::debug("CACHE-HIT :: Found object with hash " . $objhash . " in Transaction Cache for dn : $dn");

            //$obj->init_cache();

            $stop = microtime(true);
            Zivios_Log::info("LoadDnTransMode took : ".($stop-$start)."s");

            return $obj;
        }
        else {
            Zivios_Log::debug("Transaction CACHE-MISS for dn :$dn, Attempting to load from Normal cache");

            return self::loadDnNormal($dn,$classname);
        }
    }

    private static function loadDnNormal($dn,$classname=null)
    {
        //$cache = new Zivios_Ldap_Cache();
        $start = microtime(true);
        $obj = self::checkInCache($dn,$classname);
        $nomodel = 0;
        if ($classname == 'NOMODEL') {
        	$classname = 'Zivios_Ldap_Cache';
        	$nomodel = 1;
        }

        if ($obj != null && $obj->checkCacheCoherence($classname)) {
            $objhash = spl_object_hash($obj);

            Zivios_Log::debug("CACHE-HIT :: Found object with hash " . $objhash ." in Cache for dn : $dn");

            //$obj->init_cache();
            $stop = microtime(true);
            Zivios_Log::info("Load Dn Normal Cache-HIT took : ".($stop-$start)."s");

            return $obj;
        }
        else {
            Zivios_Log::debug("CACHE-MISS for dn : ". $dn);

            if ($nomodel)
            	$classname = 'NOMODEL';

            $obj = parent::loadDn($dn,$classname);
            $obj->store($classname);
            $stop = microtime(true);
            Zivios_Log::info("Load Dn Normal Cache-MISS took : ".($stop-$start)."s");
            return $obj;
        }

    }


    public function checkCacheCoherence($classname)
    {
        if ($this->transmode) {
            Zivios_Log::debug("Cache Coherence always TRUE in Transaction Mode");
            return true;
        }
        $ldapcoherence = $this->checkLdapCoherence();
        $classcoherence = $this->checkClassCoherence($classname);
        return ($ldapcoherence && $classcoherence);
    }

    public function checkLdapCoherence()
    {
        $currmodts = $this->getModifyTime($this->getdn());
        $oldmodts = $this->getProperty('modifytimestamp');
        $iscoherent = ($currmodts == $oldmodts);
        if (!$iscoherent)
            Zivios_Log::info("Ldap coherency Check failed for dn :" . $this->dn);

        return $iscoherent;
    }

    /**
    * This functions checks to make sure that the
    * object found is of the requested CLASS type
    * otherwsie we have a cache miss!
    */
    public function checkClassCoherence($classname)
    {
        if ($classname == 'NOMODEL') {
            $classname = "Zivios_Ldap_Cache";
        } else if ($classname == null) {
            $classname = $this->getProperty('emsmodelclass');
        }

        if (get_class($this) == $classname)
            return true;
        else {
            Zivios_Log::info("CACHE HIT but Consistency Check failed at dn : ".$this->getdn());

            return false;
        }
    }


    private static function checkInTransCache($dn)
    {
        $start = microtime(true);
        $mcache = self::init_cache();
        
        $_userSession = new Zend_Session_Namespace("userSession");
        if (isset($_userSession->transinprocess) && $_userSession->transinprocess > 0) {
            $transmode = 1;
            $cachetransid = $_userSession->transinprocess;
            Zivios_Log::info("Cache operative in Transaction mode for id :".$_userSession->transinprocess);
        }
        
        Zivios_Log::debug("In Transaction Mode, using alternative Cache");
        $cid = $cachetransid;
        Zivios_Log::debug("Searching for key : tmode:: " . $cid . " :: " . $dn);
        $obj = $mcache->get("tmode::$cid::$dn");
        $mcache->close();
        $stop = microtime(true);
        Zivios_Log::info("Trans cache lookup took : ".($stop-$start)."s");
        return $obj;
    }

    public static function checkInCache($dn,$model=null)
    {
        if ($dn == null)
            return null;

        /**
        * Enforce lower case dns, important to prevent case mismatches
        */

        $dn = strtolower($dn);

        $session_id = session_id();
        $start = microtime(true);
        $mcache = self::init_cache();
        if ($model != null && $model == 'NOMODEL')  {
        	Zivios_Log::debug("Searching for NOMODEL in Universal Cache dn : " .$dn);
        	$obj = $mcache->get("Universal::$dn");
        	$mcache->close();
            $stop=microtime(true);
            Zivios_Log::info("Normal cache lookup took : ".($stop-$start)."s");
        	return $obj;
        } else {

	        Zivios_Log::debug("Searching for key: " . $session_id . " :: " . $dn);
            
	        $obj = $mcache->get("$session_id::$dn");
	        $mcache->close();
            $stop=microtime(true);
            Zivios_Log::info("Normal cache lookup took : ".($stop-$start)."s");
	        return $obj;
        }
        
    }


    public function store($model=null)
    {
        $start = microtime(true);
        $this->setMode();
        

        if ($this->isNew() || $this->getdn() ==null)
            return null;
        $dn = $this->getdn();
        $session_id = session_id();
        
        $mcache = self::init_cache();
        
        if ($this->transmode) {
            Zivios_Log::debug("In Transaction Mode, using alternative cache to Store");
            $id = $this->cachetransid;
            
            $obj = $mcache->set("tmode::" . $id . "::" . $dn,$this,0,$this->cacheConfig->transexptime);
            Zivios_Log::debug("Stored transaction Object with key : tmode:: " . $id . " :: " . $dn);
            $mcache->close();
            return $obj;
        }

        
        if ($model != null && $model == 'NOMODEL') {
            
        	$obj = $mcache->set("Universal::". $dn,$this,0,$this->cacheConfig->expiretime);
	        $hash = spl_object_hash($this);
	        Zivios_Log::debug("Stored object with hash : $hash at Universal Space with Key : Universal::" . $dn);
            $mcache->close();
            $stop = microtime(true);
            Zivios_Log::info("NOMODEL Cache put took : ".($stop-$start)."s");
	        return $obj;
        } else {
	        $obj = $mcache->set($session_id . "::". $dn,$this,0,$this->cacheConfig->expiretime);
	        $hash = spl_object_hash($this);
	        Zivios_Log::debug("Stored object with hash : $hash at Key : " . $session_id . "::" . $dn);
            $mcache->close();
            $stop = microtime(true);
            Zivios_Log::info("With MODEL Cache put took : ".($stop-$start)."s");
	        return $obj;
        }
        
    }

    public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $group,$description=null)
    {
        $handler = parent::add($parent,$group,$description);
        if ($this->inTransaction())
            $this->store();
        return $group;
    }

    public function delete(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $tgroup = parent::delete($tgroup,$description);
        if ($this->inTransaction())
            $this->store();

        return $tgroup;
    }

    public function update(Zivios_Transaction_Group $group ,$description=null,$namespace='CORE')
    {
        $group = parent::update($group,$description,$namespace);
        if ($this->inTransaction()) {
            Zivios_Log::debug("Update intercepted In Transaction. Saving Object ".get_class($this)." to transaction store!");
            $this->store();
        }

        return $group;
    }
    
    public function postUpdate($namespace='CORE',$groupid=0)
    {
        parent::postUpdate($namespace,$groupid);
        if ($this->inTransaction()) {
            Zivios_Log::debug("Post Update intercepted In Transaction. Saving Object ".get_class($this)." to transaction store!");
            $this->store();
        }
    }
    
    public function postAdd(Zivios_Ldap_Engine $parent,$groupid=0)
    {
        parent::postAdd($parent,$groupid);
        if ($this->inTransaction()) {
            Zivios_Log::debug("Post Add intercepted In Transaction. Saving Object ".get_class($this)." to transaction store!");
            $this->store();
        }
    }
}
