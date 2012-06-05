/**
 * Copyright (c) 2008-2010 Zivios, LLC.
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
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/
dojo.provide("zivios.TabHandler");
dojo.declare("zivios.TabHandler", null, {
    constructor: function(baseUrl) {
        this.baseUrl     = baseUrl;
        this.ziviosTab   = null;
    },

    createApplicationTab: function(baseUrl,ptab,ctab,url,title,dynRefresh) {
        
        var ros = 1; // unless dynRefresh is defined, refresh on show will 
                     // will default to false.

        if (dijit.byId(ctab)) {
            this.ziviosTab = dijit.byId(ctab);
        } else {
            // apply defaults as required.
            if (undefined === title) {
                title = 'Zivios Application';
            }
            if (undefined === dynRefresh) {
               var ros = 0;
            }

            this.ziviosTab = new dijit.layout.ContentPane({
                    id: ctab,
                    parseOnLoad: true,
                    title: title,
                    href: url,
                    closable: true,
                    refreshOnShow: ros,
                },
                dojo.doc.createElement("div")
            );
            
            this.getAppContainer(ptab).addChild(this.ziviosTab);
        }
        
        this.getAppContainer(ptab).selectChild(this.ziviosTab);
    },

    getAppContainer: function(parentTab) {
        return dijit.byId(parentTab);
    },
});

