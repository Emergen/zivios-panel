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
 **/

if(!dojo._hasResource["zivios.LdapTreeHandler"]){dojo._hasResource["zivios.LdapTreeHandler"]=true;dojo.provide("zivios.LdapTreeHandler");dojo.require("dojo.rpc.JsonService");dojo.declare("zivios.LdapTreeHandler",null,{treedata:null,constructor:function(){this.treedata=new dojo.rpc.JsonService("/rpc/smd/ini/m/openldap/c/Service_Ldap");},destroy:function(){},getRoot:function(_1,_2){this.treedata.getBase().addCallback(function(_3){console.debug(_3);response=dojo.fromJson(_3);_1(response);}).addErrback(function(_4){response=dojo.fromJson(_4);_2(response);});},mayHaveChildren:function(_5){re=/Entry$/;if(!re.test(_5.type)){return true;}return false;},loadItem:function(_6,_7,_8){var _9=dijit.byId(_7);if(_9){dojo.forEach(_9.getDescendants(),function(_a){_a.destroyRecursive();_a.destroy();});}var _8=dojo.byId(_8);var _b=_6.module+"/"+_6.controller+"/"+_6.action+"/dn/"+escape(encodeURI(_6.key));_9.attr("content","Loading...");dojo.xhrGet({url:_b,timeout:0,handleAs:"text",headers:{XHR_ERROR_HANDLEAS:"text",},load:dojo.hitch(this,function(_c,_d){_9.attr("content",_c);return _c;}),error:function(_e,_f){_9.attr("content","An error occurred. Please check system logs.");console.error("HTTP status code: ",_f.xhr.status);return _e;}});},getChildren:function(_10,_11,_12){if(_10.type=="DirectoryContainer"){this.treedata.getRoot().addCallback(function(_13){response=dojo.fromJson(_13||null);_11(response);}).addErrback(function(_14){dojo.byId("exceptiondata").innerHTML+="<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"+_14+"</div>";dijit.byId("ztoolbar.exceptions")._openDropDown();_11([]);});}else{this.treedata.getChildren(_10.key).addCallback(function(_15){response=dojo.fromJson(_15||null);_11(response);}).addErrback(function(_16){dojo.byId("exceptiondata").innerHTML+="<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"+_16+"</div>";dijit.byId("ztoolbar.exceptions")._openDropDown();_11([]);});}},getIdentity:function(_17){return _17.key;},getLabel:function(_18){return _18.name;},newItem:function(_19,_1a){},pasteItem:function(_1b,_1c,_1d,_1e){},onChange:function(_1f){},onChildrenChange:function(_20,_21){}});}