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
 * @version		$Id: EMSConfigFile.php 911 2008-08-25 11:06:13Z fkhan $
 **/

class EMSConfigFile
{
	public $filename,$computer,$parserconf,$tmpname;
	public $writeback;
    public $mode;
	private $generic_parser,$transaction;


	public function __construct(EMSComputer $computer,$filename,$parserconf,$mode=0)
	{
        if ($mode == 0) {
            Zivios_Log::debug("EMSConfigfile using NetFile Plugin!");
        }

		$this->filename = $filename;
		$this->computer = $computer;
		$this->parserconf = $parserconf;
		$this->copyOver();
	}

	public function copyOver()
	{

		$name = $this->getCachedFileName();
		if ($name == null) {
			$this->tmpname = tempnam(TEMPDIR,"parser");
			//$this->tmpfullpath = TEMPDIR."/".$this->tmpname;
			Ecl_Log::debug("Generating new tmp file : $this->tmpname");
			//$this->computer->_remoteScp($this->filename,$this->tmpname,"recv");
            $this->computer->getFile($this->filename,$this->tmpname);
			$this->updateCache($this->tmpname);
		}
		else {
			$this->tmpname = $name;
		}

		$this->generic_parser = new Zivios_Parser_Generic($this->parserconf,$this->tmpname);
		$this->generic_parser->parse();
		Ecl_Log::debug("Parsing of file $this->tmpname Complete");
	}

	private function updateCache($tmpname)
	{
		$keyname = "EMSConfig_".$this->computer->getdn()."_".$this->filename;
		$userSession = new Zend_Session_Namespace("userSession");

		$configs = $userSession->cachedConfigNames;
		if ($configs == null) {
			$configs = array();
			$userSession->cachedConfigNames = $configs;
		}
		Ecl_Log::debug("Making new cache key: $keyname  *** tmpname: $tmpname");
		$userSession->cachedConfigNames[$keyname] = $tmpname;
	}

	private function getCachedFileName()
	{

		$userSession = new Zend_Session_Namespace("userSession");
		$configs = $userSession->cachedConfigNames;
		Ecl_log::debug("Configs:::");
		Ecl_Log::debug($configs);
		$keyname = "EMSConfig_".$this->computer->getdn()."_".$this->filename;

		if ($configs != null && array_key_exists($keyname,$configs)) {
			$tmpnam = $configs[$keyname];

			if (file_exists($tmpnam)) {
				Ecl_Log::debug("Using cached copy for $this->filename  tmpnam : $tmpnam");
				return $tmpnam;
			}
		}
		return null;
	}

	public function getMainBlock()
	{
		return $this->generic_parser->getMainBlock();
	}

	public function update(Ecl_Transaction_Handler $handler=null)
	{
		if ($handler == null) {
			$handler = $this->getTransaction();
		}

		$this->writeback = $this->generic_parser->getMainBlock()->render();
		$transaction = $handler;
		$titem = new Zivios_Transaction_Item();
		$titem->addObject('emsconfigfile',$this);
		$titem->addCommitLine('$this->emsconfigfile->_update();');
		//$titem->addRollbackLine('$this->emsobject->radd();');
		$transaction->addTransactionItem($titem);
		return $transaction;


	}
	public function getTransaction()
	{
		if ($this->transaction == null || $this->transaction->isCommitted()) {
			$this->transaction = new Zivios_Transaction_Handler();
		}
		return $this->transaction;
	}
	public function _update()
	{
		$rendername = tempnam(TEMPDIR,"parser_render");
		$file = fopen($rendername,'w');
		fwrite($file,$this->writeback);
		fclose($file);
		//Copy file over to tempnam so as to invalidate the older file.

        $this->computer->putFile($rendername,$this->filename);
		//$this->computer->_remoteScp($rendername,$this->filename,"send");
		copy($rendername,$this->tmpname);
		Ecl_Log::debug("Cached file updated");

		Ecl_Log::debug("file $rendername sent to server ".$this->computer->getdn());
	}

}