
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

class Zivios_Ldap_SchemaObjClass
{
    private $origline,$emsolcschema;
    public $prio,$name,$desc,$sup,$abstract,$structural,$aux,$mayarray,$mustarray,$oidfamily,$oidvalue;

    public function __construct($emsolcschema,$objline='')
    {
        $this->origline = $objline;
        $this->emsolcschema = $emsolcschema;
        Zivios_Log::debug("Schema Object Classes Parser started with $objline");
        if ($objline != '') {
            $this->parse();
        }

    }
    public function setOid($family,$value=null)
    {
        if ($value == null) {
            $this->oidvalue = $this->emsolcschema->getMaxOidObjs($family);
        } else {
            $this->oidvalue = $value;
        }

        $this->oidfamily = $family;

    }

    public function delete(Ecl_Transaction_Handler $handler=null)
    {
        $param = $this->emsolcschema->getParameter('olcobjectclasses');
        $param->removeValue($this->origline);
        return $this->emsolcschema->update();
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
        $this->abstract = preg_match("/ ABSTRACT /",$line);
        $this->structural = preg_match("/ STRUCTURAL /",$line);
        $this->aux = preg_match("/ AUXILIARY /",$line);


        $musttemp = $this->parseParenthised($line,'MUST');
        $musttemp = preg_replace("/ /","",$musttemp);
        $this->mustarray = explode("$",$musttemp);

        $maytemp = $this->parseParenthised($line,'MAY');
        $maytemp = preg_replace("/ /","",$maytemp);
        $this->mayarray = explode("$",$maytemp);

        Zivios_Log::debug("Read in Schema Object class entry with prio: $this->prio OID: $this->oidfamily $this->oidvalue   Name: $this->name \
                Desc: $this->desc sup: $this->sup ---- MUST And May Arrays follow this message");
        Zivios_Log::debug($this->mustarray);
        Zivios_Log::debug($this->mayarray);



    }

    private function parseParenthised($str,$maymust)
    {
        $matches = array();
        preg_match_all("/ $maymust \((.+?)\) | $maymust (.+?) /",$str,$matches);
        if ($matches[1][0] == '') {
            return $matches[2][0];
        } else return $matches[1][0];
    }

    public function isValid()
    {
        //Add validation here
        return 1;
    }

    public function render()
    {


        $line = "";
        $oid = $this->oidfamily.'.'.$this->oidvalue;
        Zivios_Log::debug("helo : $this->prio");
        $str = '{'.$this->prio.'}';
        Zivios_Log::debug("STR IS $str");
        $str .= "( $oid NAME '$this->name' DESC '$this->desc'";

        if ($this->sup != '') {

            $str .= " SUP $this->sup ";
        }

        if ($this->abstract) {
            $str .= " ABSTRACT ";
        }

        if ($this->structural) {
            $str .= " STRUCTURAL ";
        }

        if ($this->aux) {
            $str .= " AUXILIARY ";
        }

        if (sizeof($this->mustarray) > 0) {
            $str .= " MUST ( ";
            $first =1;
            foreach ($this->mustarray as $must) {
                if ($first)     {
                    $str .= " $must ";
                    $first = 0;
                } else {
                    $str .= "$ $must";
                }
            }

            $str .= " ) ";
        }

        if (sizeof($this->mayarray) > 0) {
            $str .= " MAY ( ";
            $first =1;
            foreach ($this->mayarray as $may) {
                if ($first)     {
                    $str .= " $may ";
                    $first = 0;
                } else {
                    $str .= "$ $may";
                }
            }
            $str .= " ) ";
        }
        $str .= ")";



        return $str;
    }

}
