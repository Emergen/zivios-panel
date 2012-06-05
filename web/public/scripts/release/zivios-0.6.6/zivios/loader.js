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

var js_clock=function(){var _1=new Date();var _2=_1.getHours();var _3=_1.getMinutes();var _4=_1.getSeconds();var _5="AM";if(_2>11){_5="PM";_2=_2-12;}if(_2==0){_2=12;}if(_2<10){_2="0"+_2;}if(_3<10){_3="0"+_3;}if(_4<10){_4="0"+_4;}var _6=document.getElementById("js_clock");_6.innerHTML=_2+":"+_3+":"+_4+" "+_5;setTimeout("js_clock()",1000);};