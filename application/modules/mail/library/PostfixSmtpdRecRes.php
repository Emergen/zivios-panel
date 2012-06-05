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
 * @package		mod_mail
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: PostfixSmtpdRecRes.php 914 2008-08-25 11:31:06Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:31:06 +0600 (Mon, 25 Aug 2008) $
 **/

class PostfixSmtpdRecRes extends Zivios_Parser_Element
{
    public $rbls,$others,$last,$linenum;
    public $all;
	private $parsed,$seen;

	public function __construct(Zivios_Parser_DTD $dtd) {
		parent::__construct($dtd);
		$this->parsed=0;
        $this->rbls = array();
        $this->others = array();
        $this->seen=0;
        $this->linenum=0;
        $this->all = array();
	}


	public function getName()
	{
		return $this->name;
	}

	protected function decipher($str)
	{
		$str = trim($str);

        //Check if this is the first line or not

        $firstline = preg_match("/=/",$str);
        $tokens ="";
        if ($firstline) {
            $this->name = 'smtpd_recipient_restrictions';
            $tokens = explode("=",$str);
            $tokens = trim($tokens[1]);
            Zivios_Log::debug("First str");
            $this->seen = 1;
        }else {
            $tokens = $str;
        }

        //Further tokenize on comma

        $tokens = explode(',',$tokens);

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token != "") {
                $this->decipherKeyword($token);
            }
        }




		//Zivios_Log::debug($this->parvalarray);

	}

    private function decipherKeyword($token)
    {
        Zivios_Log::debug("Deciphering Keyword : $token");


        if (preg_match("/^reject_rbl_client/",$token)) {
            $xplod = explode(" ",$token,2);
            $object = new StdClass();
            $object->string = trim($xplod[1]);
            $object->prio = $this->linenum;
            $object->type = 'rbl';

            $this->rbls[] = $object;
            $this->all[$this->linenum] = $object;
        } else {

            $object = new StdClass();
            $object->string = $token;
            $object->prio = $this->linenum;
            $object->type = 'other';
            $this->others[] = $object;
            $this->all[$this->linenum] = $object;
        }
        $this->linenum++;



    }


	public function canParse($string)
	{
        if ($this->seen) {
            Zivios_Log::debug("String is ::** $string **");
            if (preg_match("/^\s/",$string)) {
                Zivios_Log::debug("String is ::** $string ** Continuationg - Parsing continues");
                return 1;
            }
            else {
                Zivios_Log::debug("Halt Parsing! ");
                return 0;
            }
        } else {
            if(preg_match("/^smtpd_recipient_restrictions/",trim($string))) {
                Zivios_Log::debug("$string contains a PostfixSmtpdRecRes parameter");
                return 1;
            }
		}
		return 0;
	}

    public function getAllRbls()
    {
        $ret = array();
        foreach ($this->rbls as $rbl) {
            if ($rbl->type == 'rbl') $ret[] = $rbl->string;
        }
        return $ret;
    }



    public function removeRbl($string)
    {
        foreach ($this->rbls as $key=>$rbl) {
            if (trim($string) == $rbl->string) {
                $linenum = $rbl->prio;
                unset($this->rbls[$key]);
                array_splice($this->all,$linenum,1);

                // redo ALL prios
                for ($i=$linnum;$i<sizeof($this->all);$i++) {
                    $object = $this->all[$i];
                    $object->prio = $i;
                }

                unset($rbl);
            }
        }
    }



    public function addRbl($string)
    {
        //Get highest prio RBL
        $prio=0;
        foreach ($this->rbls as $rbl) {
            if ($prio < $rbl->prio) $prio = $rbl->prio;
        }


        Zivios_Log::debug("highest Prio is $prio");
        //shift array down starting with last element

        for ($i=(sizeof($this->all)-1);($i>=$prio+1);$i--) {
            $this->all[$i+1] = $this->all[$i];
        }
        $object= new StdClass();
        $object->string = trim($string);
        $object->prio = $prio+1;
        $object->type = 'rbl';
        $this->rbls[] = $object;

        $this->all[$prio+1] = $object;



    }

	public function render()
	{
		//$str="## Courtesy of Zivios_Parser_Cyrus_Parameter, distributed as part of EMS ##\n";
        $str = "smtpd_recipient_restrictions = \n";
        $arraysize = sizeof($this->all);
        $i=0;
        foreach ($this->all as $item) {
            if ($item->type == 'rbl') {
                $rbl = $item->string;
                $str .= "\t reject_rbl_client $rbl";
            } else if ($item->type =='other') {
                $other = $item->string;
                $str .= "\t $other";
            }
            $i++;
            if ($i < $arraysize) {
                $str .= ", \n";
            } else {
                $str .= "\n";
            }



        }


		//$str .= "## End Render output for Zivios_Parser_Cyrus_Parameter. Have a nice day! \n";
		return $str;
	}
}

