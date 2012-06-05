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
 * @version		$Id: EMSOlcDatabase.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSOlcDatabase extends EMSObject
{
	public $accessarray;

	public function __construct(Zivios_LdapObject $lobj)
	{

        
		parent::__construct($lobj);
		$parameter = $this->addParameter('olcaccess','Access ACLs',1);
        $parameter->forceReplace();

        /*Disabled till further notice, use the AccessObject mechanism

		$this->accessarray = array();
		$params = $this->getProperty('olcaccess');
		foreach ($params as $param) {
			$this->accessarray[] = new Zivios_Ldap_AccessLine($this,$param);
		}
        */

	}

	public function addAccessLine(Zivios_Ldap_AccessLine $access)
	{
		if (!$access->isValid()) throw new Zivios_Exception("Attempt to insert incomplete or invalid AccessLine");
		$this->accessarray[] = $access;
		$param = $this->getParameter('olcaccess');
		$param->addValue($access->render());
		Zivios_Log::debug("Added Access line :".$access->render());
	}

	public static function getCnConfig()
	{
		return new EMSOlcDatabase(Zivios_Ldap_Util::getCnConfig("olcDatabase={2}bdb,cn=config"));

	}

	/*public function updateAccessLine(Zivios_Ldap_AccessLine $access) {
		$prio = $access->prio;
		$index = $this->getAccessLineIndexByPrio($prio);
		$this->accessarray[$index] = $access;




	}
	*/
	public function getAccessLineByPrio($prio)
	{

		foreach ($this->accessarray as $accessline) {
			if ($accessline->prio == $prio) {
				return $accessline;
			}
		}
		return 0;
	}
	public function getMatchingToAccessLines($dn)
	{
		$retarray = array();
		foreach ($this->accessarray as $access) {
			if ($access->appliesToDn($dn)) {
				$retarray[] = $access;
			}
		}
		return $retarray;
	}

	public function getMatchingByAccessLines($dn,$accessparam="read")
	{
		$retarray = array();
		foreach ($this->accessarray as $access) {
			$matchingdirectives = $access->getApplicableDirectivesForAuthDn($dn,$accessparam);
			Zivios_Log::debug("Matching directives =");
			Zivios_Log::debug($matchingdirectives);
			if (($matchingdirectives != 0) && (sizeof($matchingdirectives) > 0)) {
				Zivios_Log::debug("adding");
				$retarray[] = $access;
			}
		}
		return $retarray;
	}

	public function giveSubtreeAccessTo($bydn,$subtree,$accesslevel,Zivios_Transaction_Handler $handler=null)
	{

		$minprio = $this->getMinPrio($subtree);
		Zivios_Log::debug("Minimum prio is at $minprio");
		$accessline = new Zivios_Ldap_AccessLine($this);
		$accessline->addSubtreeAccessTo($bydn,$subtree,$accesslevel);
		$accessline->setPrio($minprio);
		$param = $this->getParameter('olcaccess');
		$param->addValue($accessline->render());
		return $this->update($handler);
	}


	public function getMinPrio($subtree)
	{
		$minprio = 1000;
		$gotone=0;
		$array = $this->getMatchingToAccessLines($subtree);
		foreach ($array as $access) {
			if ($access->prio < $minprio) {
				$minprio = $access->prio;
				$gotone=1;
			}
		}
		if (!$gotone) $minprio=0;

		return $minprio;
	}
	/**
	 * Check whether the user in session has write access to this DN or part thereof
	 *
	 * @param EMSSecurityObject $emsobject
	 */
	public function hasWriteAccessTo(EMSSecurityObject $emsobject)
	{
		$dn = $emsobject->getdn();
		$userdn = Zivios_Ldap_Util::getUserDn();
		$accesslines = $this->getMatchingToAccessLines($dn);
		foreach ($accesslines as $accessline) {
			$str = $accessline->render();
			if ($accessline->hasWriteForAuthDn($userdn)) {
				Zivios_Log::debug("Got write access to $dn using $str");
				return 1;
			}
			Zivios_Log::debug("No write access to $dn usingline $str");
		}

		return 0;
	}

	public function render()
	{
		$stringrender = array();
		foreach ($this->accessarray as $access) {
			$stringrender[] = $access->render();

		}
		return $stringrender;
	}
}


