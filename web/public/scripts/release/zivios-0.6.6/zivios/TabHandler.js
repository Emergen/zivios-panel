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

if(!dojo._hasResource["zivios.TabHandler"]){dojo._hasResource["zivios.TabHandler"]=true;dojo.provide("zivios.TabHandler");dojo.declare("zivios.TabHandler",null,{constructor:function(_1){this.baseUrl=_1;this.ziviosTab=null;},createApplicationTab:function(_2,_3,_4,_5,_6,_7){var _8=1;if(dijit.byId(_4)){this.ziviosTab=dijit.byId(_4);}else{if(undefined===_6){_6="Zivios Application";}if(undefined===_7){var _8=0;}this.ziviosTab=new dijit.layout.ContentPane({id:_4,parseOnLoad:true,title:_6,href:_5,closable:true,refreshOnShow:_8,},dojo.doc.createElement("div"));this.getAppContainer(_3).addChild(this.ziviosTab);}this.getAppContainer(_3).selectChild(this.ziviosTab);},getAppContainer:function(_9){return dijit.byId(_9);},});}