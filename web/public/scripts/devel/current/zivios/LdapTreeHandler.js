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

dojo.provide("zivios.LdapTreeHandler");
dojo.require("dojo.rpc.JsonService");
dojo.declare("zivios.LdapTreeHandler", null, {
    treedata: null,
           
    constructor: function(){
        this.treedata = new dojo.rpc.JsonService('/rpc/smd/ini/m/openldap/c/Service_Ldap');
    },

    destroy: function(){
        // summary: destroys this object, releasing connections to the store
    },

    getRoot: function(onItem, onError){
        //onItem({type: "DirectoryContainer", root: true, name: "Zivios Directory"});
        this.treedata.getBase().addCallback(function(data) {
            console.debug(data);
            response = dojo.fromJson(data);
                        
            onItem(response);
        }).addErrback(function(data) {
            response = dojo.fromJson(data);
            onError(response);
        });
    },

    mayHaveChildren: function(item){
        re = /Entry$/;
        if (!re.test(item.type)) {
            return true;
        }
        return false;
    },

    loadItem: function(entry, responsepane, treenode) {
        var ddiv = dijit.byId(responsepane);
        
        if (ddiv) {
            dojo.forEach(ddiv.getDescendants(), function(widget){
                widget.destroyRecursive();
                widget.destroy();
            });
        }

        var treenode = dojo.byId(treenode);
        var url  = entry.module + '/' + entry.controller + '/' + entry.action + '/dn/' + escape(encodeURI(entry.key));

        ddiv.attr('content', 'Loading...');
        dojo.xhrGet({
            url: url,
            timeout: 0,
            handleAs: "text",
            headers: {
                XHR_ERROR_HANDLEAS: "text",
            },

            load: dojo.hitch(this, function(response, ioArgs) {
                ddiv.attr('content', response);
                return response;
            }),

            error: function(response, ioArgs) {
                ddiv.attr('content', "An error occurred. Please check system logs.");
                console.error("HTTP status code: ", ioArgs.xhr.status);
                return response;
            }
        });
    },

    getChildren: function(item, onComplete, onError){
        if (item.type == "DirectoryContainer") {
            // root item. Get all basedn entries.
            this.treedata.getRoot().addCallback(function(data) {
                response = dojo.fromJson(data || null);
                onComplete(response);
            }).addErrback(function(data) {
                dojo.byId('exceptiondata').innerHTML 
                    += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                    + data + "</div>";
                dijit.byId('ztoolbar.exceptions')._openDropDown();
                onComplete([]);
            });
        } else {
            // get all entries for specified parent node.
            this.treedata.getChildren(item.key).addCallback(function(data) {
                response = dojo.fromJson(data || null);
                onComplete(response);
            }).addErrback(function(data) {
                dojo.byId('exceptiondata').innerHTML 
                    += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                    + data + "</div>";
                dijit.byId('ztoolbar.exceptions')._openDropDown();
                onComplete([]);
            });
        }
    },

    getIdentity: function(item){
        return item.key;
    },

    getLabel: function(item){
        return item.name;
    },

    // =======================================================================
    // Write interface
    newItem: function(/* Object? */ args, /*Item?*/ parent){
        // summary
        //            Creates a new item.   See dojo.data.api.Write for details on args.
    },
    pasteItem: function(/*Item*/ childItem, /*Item*/ oldParentItem, /*Item*/ newParentItem, /*Boolean*/ bCopy){
        // summary
        //            Move or copy an item from one parent item to another.
        //            Used in drag & drop.
        //            If oldParentItem is specified and bCopy is false, childItem is removed from oldParentItem.
        //            If newParentItem is specified, childItem is attached to newParentItem.
    },
    // =======================================================================
    // Callbacks
    onChange: function(/*dojo.data.Item*/ item){
        // summary
        //            Callback whenever an item has changed, so that Tree
        //            can update the label, icon, etc.   Note that changes
        //            to an item's children or parent(s) will trigger an
        //            onChildrenChange() so you can ignore those changes here.
    },

    onChildrenChange: function(/*dojo.data.Item*/ parent, /*dojo.data.Item[]*/ newChildrenList){
        // summary
        //            Callback to do notifications about new, updated, or deleted items.
    }
});
