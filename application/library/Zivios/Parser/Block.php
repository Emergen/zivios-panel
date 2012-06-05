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
 * @version		$Id: Block.php 908 2008-08-25 11:03:00Z fkhan $
 * @deprecated
 *
 **/

class Zivios_Parser_Block extends Zivios_Parser_Element
{


	private $elements;
	private $mainblock;
	public $name;

	public function __construct(Ecl_Parser_DTD $dtd)
	{
		parent::__construct($dtd);
		$this->elements = array();
	}

    public function getElements()
    {
        return $this->elements;
    }

	public function getElement($name)
	{
		return $this->elements[$name];
	}

    public function removeElement($name)
    {
        array_splice($this->elements,$name,1);
    }

	public function setMainBlock()
	{
		$this->mainblock = 1;
		$this->name = "MAINBLOCK";

	}
	public function addParameter($name,$value) {
		$parameter = new Zivios_Parser_Parameter($this->dtd);
		$parameter->setId($name);
		$parameter->setValue($value);
		$this->elements[$parameter->name] = $parameter;
		return $parameter;
	}

	public function addPlugin(Ecl_Parser_Element $element) {
		$this->elements[get_class($element)] = $element;
	}

	public function delete(Ecl_Parser_Element $element) {
		if ($element instanceof  Zivios_Parser_Parameter ) {
			$indextodelete = $element->name;
			// delete from array directly
		} else {
			$indextodelete = get_class($element);
			// element is a plugin, delete the correct index
		}
		// TODO: Actual deletion!!!.

	}
	public function isBlockStart($string)
	{
		$string = trim($string);
		Ecl_Log::debug("Searching for block at line : $string*");
		$match=0;
		$blockstart = $this->dtd->blockdstart;
		$blockend = $this->dtd->blockdend;
		$scopestart = $this->dtd->blockscopestart;
		if (($blockstart != "") && ($scopestart != "")) {
			$match = preg_match("/^\$blockstart+[a-z0-9._-]+\$blockend\s+$scopestart/",$string);
			if ($match) {
				Ecl_Log::debug("Block found at $string");
				return $match;
			}
		} else if (($blockstart != "")) {
			Ecl_Log::debug("Only blockstart available... using");
			$match = preg_match("/^$blockstart+[a-z0-9._-]+$blockend/",$string);
			if ($match) {
				Ecl_Log::debug("Block found at $string");
				return $match;
			}
		}
		else {
			Ecl_Log::debug("Null blockstart, going for scope start");
			$match = preg_match("/^\w+\s+$scopestart/",$string);
			if ($match) {
				Ecl_Log::debug("Block found at $string");
				return $match;
			}
		}
		return 0;
	}
	public function getName()
	{
		return $this->name;
	}

	public function isBlockEnd($string)
	{
		/** two ways this is a end block are :
		* 1. another block starts
		* 2. Scope terminator  encountered
		*/
		$scopeend = $this->dtd->blockscopeend;
		$newblock = $this->isBlockStart($string);
		if ($newblock) {
			return 1;
		} else if ($scopeend != "") {
			$match = preg_match("/^$scopeend$/",trim($string));
			if ($match) {
					Ecl_Log::debug("Block ended at $string");
					return $match;
				}
		}

		return 0;
	}


	public function seekBlockStart(&$filepointer)
	{
		/** Block seeking may have two modes, in mode 1
		* if the block delimiter is defined it should seek [block]
		* otherwise it should try seeking block { }.
		* However the { can be on a different line. Hence it must try to seek
		* the { first
		* Cool regex match can find the [block] using a single command.
		*/

		if ($this->dtd->singleblock) {
			return 1;
		}
		$lastseenline = "";
	}


	public function isParameter($string) {
		$string=trim($string);
		$override = $this->dtd->paramoverride;
		if ($override != null) {
			Ecl_Log::debug("override plugin $override called");
			$plugin = new $override($this->dtd);
			return $plugin->canParse($string);
		}
		$equal = $this->dtd->equalsign;
		$match = preg_match("/$equal/",$string);
		if ($match) {
			Ecl_Log::debug ("$string contains a Parameter");
		}
		return $match;
	}

	public function isComment($string) {
		$string = trim($string);
		$comment = $this->dtd->comment;
		$match = preg_match("/^$comment/",$string);
		if ($match) {
			Ecl_Log::debug ("$string contains a Comment");
		}
		return $match;
	}


	private function extractBlockName($string)
	{
		$string = trim($string);
		if ($this->dtd->blockscopestart != null) {
			$param = explode($this->dtd->blockscopestart,$string,2);
			$string = trim($param[0]);
		}
		// now we just need to strip the [ and ] from block name
		if ($this->dtd->blockdstart != null) {
			$length = strlen($string);
			$last = $length-2;
			$blockname = trim(substr($string,1,$last));
		}
		else {
			$blockname = $string;
		}
		Ecl_Log::debug("Block name $blockname extracted");
		return $blockname;


	}


	public function parse(&$filepointer,$str)
	{
		/** it is assumed that the file pointer is correctly positioned inside this function
		* when execution starts. this would assume its a block and expect the first
		* string to be a block unless marked as MAIN BLOCK
		*/
		$singleblock = $this->dtd->singleblock;

		if (!$this->mainblock) {
			$this->name = $this->extractBlockName($str);
		}

		$nameblock = $this->name;
		Ecl_Log::debug("In block $nameblock Parsing start");
		$lastparam=0;
		$lastseenline ="";
		while (!feof($filepointer)) {
			if ($lastseenline == "") {
				$str = fgets($filepointer);
				Ecl_Log::debug("Getting new line : $str");

			} else {
				Ecl_Log::debug("Reusing Last Token : $str");
				$str = $lastseenline;
				$lastseenline="";
			}
			//$str = trim($str);
			Ecl_Log::debug("in block $nameblock Parsing : $str");
			//first try probing plugins
			$class = $this->dtd->getPluginByMatchingPreg($str);
			if ($this->isComment($str)) {
				$lastseenline=$this->newComment($filepointer,$str);
			}

			else if ($class != null) {
				Ecl_Log::debug("Block found class $class for handling $str");
				if (array_key_exists($class,$this->elements)) {
					$lastseenline = $this->elements[$class]->parse($filepointer,$str);
					Ecl_Log::debug("updating existing element $class");
				} else {
					$element = new $class($this->dtd);
					$lastseenline = $element->parse($filepointer,$str);
					$this->elements[$class] = $element;
					Ecl_Log::debug("adding New element : $class");
				}
			}
			else if ($this->isParameter($str)) {
				$lastseenline = $this->newParameter($filepointer,$str);
			}
			else if (!$singleblock && $this->isBlockStart($str)) {
				if (!$this->mainblock) {
					Ecl_Log::debug("End of block : ".$this->name);
					return $str;
				}

				Ecl_Log::debug("Adding new Block : $str");
				$block = new Zivios_Parser_Block($this->dtd);
				$lastseenline= $block->parse($filepointer,$str);
				$name = $block->getName();
				Ecl_Log::debug("Parsed block $name completed");
				$this->elements["BLOCK_$name"] = $block;

			}
			else if (!$singleblock && $this->isBlockEnd($str) && !$this->mainblock) {
                return $str;
			}





			Ecl_Log::Debug("Lastseenline was : $lastseenline");

		}
	}

	private function newParameter(&$filepointer,$str) {
		$override = $this->dtd->paramoverride;
		if ($override != null) {
			Ecl_Log::debug("Using override parameter class $override for $str");
			$plugin = new $override($this->dtd);
			$last = $plugin->parse($filepointer,$str);
			$this->elements[$plugin->getName()] = $plugin;
			return $last;
		}

		$param = new Zivios_Parser_Parameter($this->dtd);
		$last = $param->parse($filepointer,$str);
		Ecl_Log::debug("Inserting parameter : *".$param->name."* into elements");
		$this->elements[$param->name] = $param;
		return $last;
	}

	private function newComment(&$filepointer,$str) {
		$comment = new Zivios_Parser_Comment($this->dtd);
		$last = $comment->parse($filepointer,$str);
		Ecl_Log::debug("Inserting Comments : $str");
		$this->elements[] = $comment;
		return $last;
	}
	public function getBlock($name)
	{
		return $this->elements["BLOCK_$name"];
	}

	public function render()
	{
		$out="";
		$blockstart = $this->dtd->blockdstart;
		$blockend = $this->dtd->blockdend;
		$scopestart = $this->dtd->blockscopestart;
		$scopeend = $this->dtd->blockscopeend;
		if ($this->mainblock) {
			// Ignore block defs
			foreach ($this->elements as $element) {
				$out.= $element->render();
			}
		} else {
			$out .= "$blockstart".$this->name."$blockend $scopestart\n";
			foreach ($this->elements as $element) {
				$out.= $element->render();
			}
			$out .= "$scopeend\n";
		}
		return $out;
	}







}

