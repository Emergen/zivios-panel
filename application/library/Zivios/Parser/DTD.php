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
 * @package		Parser
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: DTD.php 908 2008-08-25 11:03:00Z fkhan $
 * @deprecated
 **/

class Zivios_Parser_DTD
{
	public $singleblock,$blockdstart,
			$blockdend,$blockscopestart,
			$blockscopeend,$parametervsep,$parameterterm,$equalsign,$comment,$loosetermination,$implicitinheritance;
	public $pluginlist,$pregmatch;
	public $paramoverride;

	public function __construct($conffile)
	{
		$this->paramoverride= null;
		// take inputs from configuration file
		$general = new Zend_Config_Ini(APP_PARSER . '/'.$conffile,'general');
		$block = new Zend_Config_Ini(APP_PARSER . '/'.$conffile,'block');
		$parameter = new Zend_Config_Ini(APP_PARSER . '/'.$conffile,'parameter');
		$plugins = new Zend_Config_Ini(APP_PARSER . '/'.$conffile,'plugins');
		$this->singleblock = $general->singleblock;
		$this->blockdstart = $block->delimiter->start;
		$this->blockdend = $block->delimiter->end;
		$this->blockscopestart = $block->delimiter->scopestart;
		$this->blockscopeend = $block->delimiter->scopeend;
		$this->parameterterm = $this->escape($parameter->terminator);
		$this->parametervsep = $parameter->valueseparator;
		$this->equalsign = $parameter->equalsign;
		$this->comment = $general->comment;
		$this->loosetermination = $parameter->loosetermination;
		$this->implicitinheritance = $parameter->implicitinheritance;
		$this->paramoverride = $parameter->overrideclass;
		$this->paramautoadvance = $parameter->autoadvance;
		$pluginlist = explode(',',$general->plugins);
		Ecl_Log::debug("Plugin list :");
		Ecl_Log::debug($pluginlist);
		$this->pregmatch = array();
		if ($plugins != null) {
			foreach ($pluginlist as $plugin) {
				$match = $plugins->$plugin->pregmatch;
				if (trim($match) != "") {
				Ecl_Log::debug("pregmatch $match saved for plugin $plugin");
				$this->pregmatch[$match] = $plugin;
				}
			}
		}
		Ecl_Log::debug("DTD Read from $conffile complete");
	}

	private function escape($chr) {
		Ecl_Log::debug("Processing config value : $chr");
		if (preg_match('/\\\\/',$chr)) {
			$str = substr($chr,1);
			$str = chr($str);
			Ecl_Log::debug("Escapign and returning : *$str*");
			return $str;
		} else {
			return $chr;
		}

	}

	public function getPluginByMatchingPreg($str) {
		$keys = array_keys($this->pregmatch);
		foreach ($keys as $key) {
			Ecl_Log::debug("Key : $key, STR: $str");
			$match = preg_match($key,$str);
			if ($match) {
				$class = $this->pregmatch[$key];
				Ecl_Log::debug("Preg $key matches $str, returning $class");
				return $class;
			}
		}
		return null;
	}

}

