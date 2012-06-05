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
 * @subpackage  Schema
 **/

class Zivios_Ldap_SchemaAttribute
{
    private $origline,$emsolcschema;
    public $prio,$name,$desc,$sup,$syntax,$equality,$substr,$singlevalue,$oidfamily,$oidvalue;

    public function __construct($emsolcschema,$attribline='')
    {
        $this->origline = $attribline;
        $this->emsolcschema = $emsolcschema;
        Zivios_Log::debug("Schema Parser started with $attribline");
        if ($attribline != '') {
            $this->parse();
        }

    }
    public function setOid($family,$value=null)
    {
        if ($value == null) {
            $this->oidvalue = $this->emsolcschema->getMaxOidAttrib($family);
        } else {
            $this->oidvalue = $value;
        }

        $this->oidfamily = $family;
    }
    private function parseOid($oid)
    {
        $reversed = strrev($oid);
        $oidtokenized = explode('.',$reversed,2);
        $this->oidvalue=strrev($oidtokenized[0]);
        $this->oidfamily=strrev($oidtokenized[1]);
    }
    private function parse()
    {
        $line = $this->origline;
        $matches = array();
        preg_match_all("/^\{(\d+?)\}/",$line,$matches);
        $this->prio = $matches[1][0];
        preg_match_all("/\( ([\d\.]+?) /",$line,$matches);
        $this->parseOid($matches[1][0]);
        $this->name = Zivios_Util::magicParseValue($line,'NAME');
        $this->desc = Zivios_Util::magicParseValue($line,'DESC');
        $this->sup = Zivios_Util::magicParseValue($line,'SUP');
        $this->syntax = Zivios_Util::magicParseValue($line,'SYNTAX');
        $this->equality = Zivios_Util::magicParseValue($line,'EQUALITY');
        $this->substr = Zivios_Util::magicParseValue($line,'SUBSTR');
        $this->singlevalue = preg_match("/ SINGLE-VALUE /",$line);
        Zivios_Log::debug("Read in Schema entry with prio: $this->prio OID: $this->oidfamily $this->oidvalue   Name: $this->name \
                Desc: $this->desc sup: $this->sup SYNTAX: $this->syntax EQUALITY: $this->equality \
                substr: $this->substr Singlevalue=$this->singlevalue");


    }
    public function isValid()
    {
        // Stub function. Add validation here
        return 1;
    }
    public function render()
    {


        $line = "";
        $oid = $this->oidfamily.'.'.$this->oidvalue;
        Zivios_Log::debug("helo : $this->prio");
        $str = '{'.$this->prio.'}';
        Zivios_Log::debug("STR IS $str");
        Zivios_Log::debug("hello");
        if ($this->sup != '') {

            $str .= "( $oid NAME '$this->name' DESC '$this->desc' SUP $this->sup )";
        }
        else {

            //$str = "SYNTAX $this->syntax";
            $str .= '( '.$oid.' NAME \''.$this->name.'\' DESC \''.$this->desc.'\' EQUALITY '.$this->equality.' SUBSTR '.$this->substr.' SYNTAX '.$this->syntax;
            if ($this->singlevalue) {
                $str .= " SINGLE-VALUE ";
            }
            $str .= ")";


        }
        return $str;
    }

}
