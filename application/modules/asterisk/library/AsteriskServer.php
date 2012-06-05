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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: AsteriskServer.php 902 2008-08-25 06:39:02Z gmustafa $
 **/

class AsteriskServer extends Zivios_Plugin_Service
{
	const PLUGIN = 'asterisk';
	const PLUGINDISPLAYNAME = 'Asterisk';

	public function __construct($computerobj)
	{
		parent::__construct($computerobj);
	}

	public function add(Zivios_Transaction_Handler $handler=null)
	{
		if ($handler == null) {
			$handler = $this->_computerobj->getTransaction();
		}
		/**
		 * Add Asterisk plugin DN to transaction handler.
		 */
		$pluginDN = new
			EMSSecurityObject($this->_computerobj->_lobj->respawn(null));

		$pluginDN->setProperty('cn','asterisk');
		$pluginDN->setProperty('emspermission','default');
		$pluginDN->setProperty('emsdescription','EMS Asterisk Plugin');
		$pluginDN->setProperty('emstype',EMSObject::TYPE_SERVICEPLUGIN);
		$pluginDN->_lobj->addItem('objectclass', 'emsIgnore');

		/**
		 * Pass add operation to trans handler
		 */
		$handler = $pluginDN->add($this->_computerobj, $handler);
		return $handler;

	}

	public function listMeetme($conf="")
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/meetme.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('rooms');

		$param = $block->getElement('Zivios_Parser_Asterisk_MeetmeParameter');

		$new = $mb->render();
		return $param->getConf($conf);
	}

	public function listQueue($queue)
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);
		$p = $block->getElement();
		return $p;
	}

	public function listQueueMembers($queue)
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);

		$param = $block->getElement('Zivios_Parser_Asterisk_QueueParameter');

		$p = $param->getMember();
		return $p;
	}

	public function listConfExten($conf="")
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('conferences');

		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');

		return $param->getExten($conf);
	}

	public function listQueueExten($queue="")
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('queues');

		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');

		return $param->getExten($queue);
	}

	public function listRoutes($route="") {


		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('routes');

		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');

		$new = $mb->render();
		return $param->getExten($route);
	}

	public function listTrunks($trunk="") {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/trunks.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('trunks');
		$param = $block->getElement($trunk);
		return $param;
	}

	public function getQueue($queue) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);
		return $block->getElement();
	}

	public function getQueueMembers($queue) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);
		$param = $block->getElement('Zivios_Parser_Asterisk_QueueParameter');
		return $param;
	}

	public function getRemoteConfigs()
	{
		$fin = $this->_remoteScp("/etc/asterisk/emsDialPlan.conf","/tmp/emsDialPlan.conf",'recv');
		$fin = $this->_remoteScp("/etc/asterisk/trunks.conf","/tmp/trunks.conf",'recv');
		$fin = $this->_remoteScp("/etc/asterisk/queues.conf","/tmp/queues.conf",'recv');
		$fin = $this->_remoteScp("/etc/asterisk/meetme.conf","/tmp/meetme.conf",'recv');
		$fin = $this->_remoteScp("/etc/zaptel.conf","/tmp/zaptel.conf",'recv');
		$fin = $this->_remoteScp("/etc/asterisk/zapata.conf","/tmp/zapata.conf",'recv');
	}

	public function sendRemoteConfigs()
	{
		$fin = $this->_remoteScp("/tmp/emsDialPlan.conf","/etc/asterisk/emsDialPlan.conf",'send');
		$fin = $this->_remoteScp("/tmp/trunks.conf","/etc/asterisk/trunks.conf",'send');
		$fin = $this->_remoteScp("/tmp/queues.conf","/etc/asterisk/queues.conf",'send');
		$fin = $this->_remoteScp("/tmp/meetme.conf","/etc/asterisk/meetme.conf",'send');
		$fin = $this->_remoteScp("/tmp/zaptel.conf","/etc/zaptel.conf",'send');
		$fin = $this->_remoteScp("/tmp/zapata.conf","/etc/asterisk/zapata.conf",'send');
	}

	public function addPattern($route, $name, $trunk, $sdigit, $sdposi,$edit=0) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('routes');
		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');

		if ($edit)
		{
			$param->rmExten($edit);
		}
		//check if route already exists
		if (!$param->in_extens($route, 1))
		{
			$extObj = $param->createExten($route, 1);
			$extObj->app = 'Macro';
			$extObj->args = 'dialout,' . $trunk .',';
			if ($sdigit <1 ) {
				$extObj->args .= '${EXTEN},'.$name;
			} else {
				$extObj->args .= '${EXTEN:' .$sdigit. '},'.$name;
			}
			$extObj->exten = $route;
			$new = $mb->render();

			$fp = fopen("/tmp/emsDialPlan.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

		} else {
			throw new Zivios_Exception("Route already exists");
		}

	}


	public function addTrunk($name,$trunk,$edit=0) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/trunks.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('trunks');
		//

		if ($edit)
		{
			$block->delElement($edit);

		}
		$param = $block->getElement();
		//check if trunk already exists
		if (!array_key_exists($name, $param))
		{
			$p = $block->newElement($name);
			$p->addValue($trunk);
			$new = $mb->render();
			$fp = fopen("/tmp/trunks.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

		} else {
			throw new Zivios_Exception("Trunk already exists");
		}

	}


	public function addConfExten($extension, $args, $uapasses, $edit=0) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('conferences');
		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');
		//exit;
		if ($edit)
		{
			$param->rmExten($edit);
		}
		//check if route already exists
		if (!$param->in_extens($extension, 1))
		{
			$extObj = $param->createExten($extension, 1);
			$extObj->app = 'MeetMe';
			$extObj->args = $args;
			$extObj->exten = $extension;
			$new = $mb->render();
			$fp = fopen("/tmp/emsDialPlan.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

			$this->addMeetMe($extension, $uapasses, $edit);

		} else {
			throw new Zivios_Exception("Meetme Exten already exists in emsDialPlan.conf");
		}

	}


	public function addQueueExten($extension, $queue, $qargs, $members, $edit=FALSE) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/emsDialPlan.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('queues');
		$param = $block->getElement('Zivios_Parser_Asterisk_ExtenParameter');

		if ($edit)
		{
			$param->rmExten($edit);
		}
		//check if route already exists
		if (!$param->in_extens($extension, 1))
		{
			$extObj = $param->createExten($extension, 1);
			$extObj->app = 'Queue';
			$extObj->args = $queue;
			$extObj->exten = $extension;
			$new = $mb->render();

			$fp = fopen("/tmp/emsDialPlan.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

			$this->addQueue($queue, $qargs);
			$this->flushQueueMembers($queue);

			foreach($members as $member)
			{
				$this->addQueueMember($queue, $member, $edit);
			}

		} else {
			throw new Zivios_Exception("Queue Exten already exists in emsDialPlan.conf");
		}

	}

	public function addMeetMe($confno, $args, $edit) {
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/meetme.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock('rooms');
		$param = $block->getElement('Zivios_Parser_Asterisk_MeetmeParameter');

		if ($edit)
		{
			$param->rmConf($edit);
		}
		//check if route already exists
		if (!$param->in_confs($confno))
		{
			$extObj = $param->createConf($confno);
			$extObj->conf = $confno;
			$extObj->args = $args;
			$new = $mb->render();

			$fp = fopen("/tmp/meetme.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

		} else {
			throw new Zivios_Exception("Meetme Room already exists in meetme.conf");
		}

	}

	public function addQueue($queue, $args) {

		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
//		if (isset($mb->getBlock($queue)) && $edit>0) {
//			throw new Zivios_Exception('Queue already exists...');
//		}
//

		$block = $mb->getBlock($queue);
		//$param = $block->getElement();
		foreach ($args as $key=>$arg) {
			$p = $block->newElement($key);
			$p->addValue($arg);
		}


		$p = $block->newElement("member ");
		$p->addValue("> psuedo");

		$new = $mb->render();

		$fp = fopen("/tmp/queues.conf", 'w');
		fwrite($fp, $new);
		fclose($fp);


	}

	public function flushQueueMembers($queue)
	{
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);
		$param = $block->getElement('Zivios_Parser_Asterisk_QueueParameter');
		$param->rmMember();


		$extObj = $param->createMember('psuedo');
		$extObj->member = 'psuedo';
		$new = $mb->render();

		$fp = fopen("/tmp/queues.conf", 'w');
		fwrite($fp, $new);
		fclose($fp);
	}

	public function addQueueMember($queue, $member, $edit="") {
		$parser = new Zivios_Parser_Generic('../config/Parser/asterisk.ini','/tmp/queues.conf');
		$parser->parse();
		$mb = $parser->getMainBlock();
		$block = $mb->getBlock($queue);
		$param = $block->getElement('Zivios_Parser_Asterisk_QueueParameter');
		$param->rmMember("psuedo");

		//check if route already exists
		if (!$param->in_members($member))
		{
			$extObj = $param->createMember($member);
			$extObj->member = $member;

			$new = $mb->render();

			$fp = fopen("/tmp/queues.conf", 'w');
			fwrite($fp, $new);
			fclose($fp);

		} else {
			throw new Zivios_Exception("Queue Member already exists in queues.conf");
		}

	}

	public function update(Zivios_Transaction_Handler $handler=null)
	{
		return null;
	}

	public function delete(Zivios_Transaction_Handler $handler=null)
	{
		return null;
	}
	public function returnDisplayName() {
		return self::PLUGINDISPLAYNAME;
	}

	public function returnPluginName() {
		return self::PLUGIN;
	}

	public function generateContextMenu()
	{
		/**
		 * Load menu configuration file for AFS and simply return.
		 *
		 * @todo: take care of possible failures here -- however, if an
		 * exception is thrown, it's because the developer is miconfiguring
		 * his/her plugin.
		 */
		return false;
	}

}