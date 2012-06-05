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

dojo.provide("zivios.core");

(function() {
    dojo.require("zivios.TabHandler");
    dojo.require("zivios.AutocompleteReadStore");
    dojo.require("dijit.layout.ContentPane");
    dojo.require("dijit.layout.BorderContainer");
    dojo.require("dijit.layout.TabContainer");
    dojo.require("dijit.layout.AccordionContainer");
    dojo.require("dijit.form.FilteringSelect");
    dojo.require("dijit.form.ValidationTextBox");
    dojo.require("dijit.form.SimpleTextarea");
    dojo.require("dijit.form.Button");
    dojo.require("dijit.form.CheckBox");
    dojo.require("dijit.form.NumberSpinner");
    dojo.require("dijit.form.DateTextBox");
    dojo.require("dijit.form.TimeTextBox");
    dojo.require("dijit.form.Slider");
    dojo.require("dijit.form.Form");
    dojo.require("dijit.Dialog");
    dojo.require("dijit.Toolbar");
    dojo.require("dijit.Tooltip");
    dojo.require("dijit.ProgressBar");
    dojo.require("dijit.Tree");
    dojo.require("dojox.dtl.Context");
    dojo.require("dojox.form.DropDownSelect");
    dojo.require("dojo.data.ItemFileReadStore");
    dojo.require("dojo.parser");
    dojo.require("dojo.fx");
    dojo.require("dojox.form.FileInput");
    dojo.require("dojo.io.iframe");

    dojo.addOnLoad(function() {
        
        // initialize Zivios tab handler
        zivios.tabs   = new zivios.TabHandler(zivios.baseUrl);

        // initialize clock & toggle primary screen visibility
        zivios.js_clock();
        zivios.hideDiv('preloader');
        zivios.showDiv('zvPrimaryContainer');

        // resize the layout
        dijit.byId('layout').resize();
    });

    dojo.mixin(zivios, {

        loadApp: function(url,ptab,ctab,title,refreshOnLoad) {
            // If the directory service is being loaded,
            // include required handler.
            if (ctab == "zdirectory") {
                dojo.require("zivios.LdapTreeHandler");
                zivios.treeHandler = new zivios.LdapTreeHandler();
            }
            zivios.tabs.createApplicationTab(zivios.baseUrl,ptab,ctab,url,title,refreshOnLoad);
        },

        redirectUrl: function(url) {
            window.location=url;
        },

        /**
         * Change the display property of a div to "none"
         *
         * @param string div 'div that needs to be "switched off" from display'         
         * @return void
         */
        hideDiv: function(div) {
            var adiv;
            if (null !== (adiv = dojo.byId(div))) {
                dojo.style(adiv, "display", "none");
            }
        },
        
        /**
         * Change the display property of a div to "block"
         *
         * @param string div: 'div that need to "switched on" from display'
         * @return void
         */
        showDiv: function(div) {
            var adiv;
            if (null !== (adiv = dojo.byId(div))) {
                dojo.style(adiv, "display", "block");
            }
        },        

        callRemoteRender: function(data,rdiv) {
            if (undefined === rdiv) {
                console.log("Error: Response div not defined.");
                dojo.byId('exceptiondata').innerHTML += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                    + "Callback defined did not specify a target node for data population.</div>";
                dijit.byId('ztoolbar.exceptions')._openDropDown();
            }

            // Call the load page function.
            data = dojo.fromJson(data);
            dojo.byId(rdiv).innerHTML = "<br />please wait...";
            zivios.loadPage(data.loadaction, rdiv);
        },

        callAction: function(url, content, attrSubmit) {

            var attrSubmitLabel = dijit.byId(attrSubmit);
            var hrefspan;

            if (attrSubmitLabel == undefined) {
                var hrefspan = dojo.byId(attrSubmit);
                if (hrefspan == undefined) {
                    alert('Either a attrSubmit of hrefSpan must be defined when using callAction');
                    return;
                } else {
                    hrefspan = hrefspan.innerHTML;
                }
            } else {
                attrSubmitLabel = dijit.byId(attrSubmit).label;
            }


            if (hrefspan == undefined) {
                dijit.byId(attrSubmit).attr('disabled', true);
                dijit.byId(attrSubmit).attr('label', 'Wait...');
            } else {
                dojo.byId(attrSubmit).innerHTML = 'Wait...';
            }            

            dojo.xhrPost({
                url: url,
                content: content,
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: "json",
                },

                load: dojo.hitch(this, function(response, ioArgs) {
                    //showLoading.style.display = "none";
                    try {
                        response = dojo.fromJson(response || null);
                        // re-enable the form
                        if (hrefspan == undefined) {
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                        } else {
                            dojo.byId(attrSubmit).innerHTML = hrefspan;
                        }

                        //Check for errors and populate the install log div
                        if (response.sysErr) {
                            dojo.byId('alertdata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 40px;\">Error: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.alerts')._openDropDown();
                            if (hrefspan == undefined) {
                                dijit.byId(attrSubmit).attr('disabled', false);
                                dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            } else {
                                dojo.byId(attrSubmit).innerHTML = hrefspan;
                            }
                            return response;
                        }

                        // Check for System Exceptions
                        if (response.sysExp) {
                            dojo.byId('exceptiondata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Exception: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">File: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.file + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Line: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.line + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Trace: </div>" +
                                "<div style=\"padding: 2px; float: left;\">Check Zivios Log.</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.exceptions')._openDropDown();
                            if (hrefspan == undefined) {
                                dijit.byId(attrSubmit).attr('disabled', false);
                                dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            } else {
                                dojo.byId(attrSubmit).innerHTML = hrefspan;
                            }
                            return response;
                        }

                        // Check for notifications
                        if (response.sysNotify) {
                            dojo.byId('notifydata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px;\">" + response.sysNotify + "</div>" +
                                "</div>";

                            dijit.byId('ztoolbar.notifications')._openDropDown();
                        }

                        // check response for possible callbacks and accompanying arguments.
                        // all callbacks are executed in the order they were registered.
                        if (response.callbacks) {
                            for (var i = 0; i < response.callbacks.length; i++) {
                                var callbackFunc = response.callbacks[i]['func'].toString() + "(";
                                if (response.callbacks[i]['args']) {
                                    var argArray = response.callbacks[i]['args'];
                                    for (var a = 0; a < argArray.length; a++) {
                                        callbackFunc += "'" + argArray[a].toString() + "'";
                                        if (a + 1 < argArray.length) {
                                            callbackFunc += ",";
                                        }
                                    }
                                }
                                callbackFunc += ")";
                                var doCallback = new Function(callbackFunc);
                                doCallback();
                            }
                        }

                        // check for possible div updates
                        if (response.divdata) {
                            for (var d = 0; d < response.divdata.length; d++) {
                                var datadiv = dojo.byId(response.divdata[d]['div']);
                                if (undefined === response.divdata[d]['append']) {
                                    datadiv.innerHTML = response.divdata[d]['content'];
                                } else {
                                    datadiv.innerHTML += response.divdata[d]['content'];
                                }
                            }
                        }

                        return response;

                    } catch (ex) {
                        // The return response was not valid Json.
                        zivios.createErrorDialog(ex.message);
                        if (hrefspan == undefined) {
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                        } else {
                            dojo.byId(attrSubmit).innerHTML = hrefspan;
                        }
                        return response;
                    }
                }),

                // Communication errors are directed to the console (for now)
                error: function (response, ioArgs) {
                    if (response.message) {
                        zivios.createErrorDialog(response.message);
                    }
                    if (hrefspan == undefined) {
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                    } else {
                        dojo.byId(attrSubmit).innerHTML = hrefspan;
                    }
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                }
            });
        },

         cpaneAction: function(cpid, url, content, attrSubmit) {

            var attrSubmitLabel = dijit.byId(attrSubmit);
            var hrefspan;

            if (attrSubmitLabel == undefined) {
                var hrefspan = dojo.byId(attrSubmit);
                if (hrefspan == undefined) {
                    alert('Either a attrSubmit of hrefSpan must be defined when using callAction');
                    return;
                } else {
                    hrefspan = hrefspan.innerHTML;
                }
            } else {
                attrSubmitLabel = dijit.byId(attrSubmit).label;
            }


            if (hrefspan == undefined) {
                dijit.byId(attrSubmit).attr('disabled', true);
                dijit.byId(attrSubmit).attr('label', 'Wait...');
            } else {
                dojo.byId(attrSubmit).innerHTML = 'Wait...';
            }            
            
            cp = dijit.byId(cpid);
            cp.attr('content',cp.onDownloadStart());

            dojo.xhrPost({
                url: url,
                content: content,
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: "text",
                },

                load: dojo.hitch(this, function(response, ioArgs) {
                    //showLoading.style.display = "none";
                    
                        // re-enable the form
                        if (hrefspan == undefined) {
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                        } else {
                            dojo.byId(attrSubmit).innerHTML = hrefspan;
                        }

                        //Update the content pane with new innerHTML
                        dijit.byId(cpid).attr('content',response);
                        
                }),

                // Communication errors are directed to the console (for now)
                error: function (response, ioArgs) {
                    if (response.message) {
                        zivios.createErrorDialog(response.message);
                    }
                    if (hrefspan == undefined) {
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                    } else {
                        dojo.byId(attrSubmit).innerHTML = hrefspan;
                    }
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                }
            });
        },
        
        // Refresh a content pane by div id.
        cpaneRefresh: function (id, href) {

            var cpane = dijit.byId(id) || null;

            if (cpane) {
                if (undefined !== href) {
                    cpane.href=href;
                }
                cpane.refresh();
            }
        },
        
        loadPage: function(url, div, rtimeout, loadingDiv) {

            if (undefined === rtimeout)
                var rtimeout = 0;

            if (undefined !== loadingDiv)
                var showLoading = dojo.byId(loadingDiv);

            if (showLoading)
                showLoading.style.display = "block";

            dojo.xhrGet({
                url: url,
                timeout: rtimeout,

                load: dojo.hitch(this, function(response, ioArgs) {
                    // Check if json object has been returned
                    if (showLoading)
                        showLoading.style.display = "none";
                    try {
                        // If response is JSON, an XHR request resulted in an error.
                        // This function provides only basic rendering.
                        response = dojo.fromJson(response || null);
                        if (response.sysErr) {

                            for (var i = 0; i < response.sysErr.length; i++) {
                                var errItem = response.sysErr[i];
                                dojo.byId('alertdata').innerHTML += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                                    + errItem.errmsg + "</div>";
                            } dijit.byId('itoolbar.alerts')._openDropDown();

                            return response;
                        }

                        // Check for System Exceptions
                        if (response.sysExp) {
                            for (var i = 0; i < response.sysExp.length; i++) {
                                var expItem = response.sysExp[i];
                                dojo.byId('exceptiondata').innerHTML += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                                    + expItem.expmsg + "</div>";
                            } dijit.byId('itoolbar.exceptions')._openDropDown();

                            return response;
                        }
                    } catch (ex) {
                        dojo.byId(div).innerHTML = response;
                        dojo.parser.parse(div);
                        return response;
                    }
                }),

                error: function(response, ioArgs) {
                    if (showLoading)
                        showLoading.style.display = "none";
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                }
            });
        },

        feedConsole: function(log) {
                this.stopFeed();
                this._intervalId = setInterval(function() {
                var cdata = new dojo.data.ItemFileReadStore({url: "/index/getconsoledata/log/" + log});
                console.log("feedconsole registered.");
                console.log("sending request");
                var request = cdata.fetch({query: {type:"logline"}, onComplete: function(items, request) {
                        console.log("Response received");
                        //dojo.byId("consoleLog").innerHTML = "";

                        if (items.length > 0) {
                            for (var i = 0; i < items.length; i++) {
                                var item = items[i];
                                innerHtml = dojo.byId("consoleLog").innerHTML;
                                newHtml = cdata.getLabel(item);

                                dojo.byId("consoleLog").innerHTML = "";
                                dojo.byId("consoleLog").innerHTML = newHtml + "<br/>" + innerHtml;

                            }
                            this._previousLog = items;
                        } else {
                            dojo.byId("consoleLog").innerHTML = "";
                        }
                    },
                });
            }, 5000);
        },

        stopFeed: function() {
            clearInterval( this._intervalId );
        },

        refreshTreeNode: function (dn) {

            var tree = dijit.byId('ztreedir');

            if (tree !== undefined && tree !== "") {
                tn = tree._itemNodeMap[dn];
                if (tn != null) {
                    arrlength = tn.getChildren().length;
                    console.debug('refreshing dn : '+dn);
                    console.debug(tn);
                    tn.state = 'UNCHECKED';
                    tree._collapseNode(tn);
                    tn.isExpandable = true;
                    tree._expandNode(tn);
                }
            } else {
                return false;
            }
        },

        closeTab: function (thandlerid, tabid) {
            tabhandler = dijit.byId(thandlerid);
            tab = dijit.byId(tabid);
            tabhandler.closeChild(tab);
        },

        selectTab: function (thandlerid, tabid) {
            tabhandler = dijit.byId(thandlerid);
            tab = dijit.byId(tabid);
            tabhandler.selectChild(tab);
        },

        clearDiv: function(divid) {
            dojo.byId(divid).innerHTML = '';
        },

        notifications: function() {
        },

        alerts: function() {
        },

        exceptions: function() {
        },

        doXhrPost: function(formid,url) {
            var attrSubmit = formid + '-submit';
            var attrSubmitLabel = dijit.byId(attrSubmit).attr('label');
            
            dijit.byId(attrSubmit).attr('disabled', true);
            dijit.byId(attrSubmit).attr('label', 'Please wait...');

            //showLoading.style.display = "block";
            dojo.xhrPost({
                url: url,
                form: dojo.byId(formid),
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: "json",
                },

                load: dojo.hitch(this, function(response, ioArgs) {
                    //showLoading.style.display = "none";
                    try {
                        response = dojo.fromJson(response || null);
                        // re-enable the form
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);

                        //Check for errors and populate the install log div
                        if (response.sysErr) {
                            dojo.byId('alertdata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 40px;\">Error: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.alerts')._openDropDown();
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            return response;
                        }

                        // Check for System Exceptions
                        if (response.sysExp) {
                            dojo.byId('exceptiondata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Exception: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">File: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.file + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Line: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.line + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Trace: </div>" +
                                "<div style=\"padding: 2px; float: left;\">Check Zivios Log.</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.exceptions')._openDropDown();
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            return response;
                        }

                        // Check for notifications
                        if (response.sysNotify) {
                            dojo.byId('notifydata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px;\">" + response.sysNotify + "</div>" +
                                "</div>";

                            dijit.byId('ztoolbar.notifications')._openDropDown();
                        }

                        // check response for possible callbacks and accompanying arguments.
                        // all callbacks are executed in the order they were registered.
                        if (response.callbacks) {
                            for (var i = 0; i < response.callbacks.length; i++) {
                                var callbackFunc = response.callbacks[i]['func'].toString() + "(";
                                if (response.callbacks[i]['args']) {
                                    var argArray = response.callbacks[i]['args'];
                                    for (var a = 0; a < argArray.length; a++) {
                                        callbackFunc += "'" + argArray[a].toString() + "'";
                                        if (a + 1 < argArray.length) {
                                            callbackFunc += ",";
                                        }
                                    }
                                }
                                callbackFunc += ")";
                                var doCallback = new Function(callbackFunc);
                                doCallback();
                            }
                        }

                        // check for possible div updates
                        if (response.divdata) {
                            for (var d = 0; d < response.divdata.length; d++) {
                                var datadiv = dojo.byId(response.divdata[d]['div']);
                                if (undefined === response.divdata[d]['append']) {
                                    datadiv.innerHTML = response.divdata[d]['content'];
                                } else {
                                    datadiv.innerHTML += response.divdata[d]['content'];
                                }
                            }
                        }

                        return response;

                    } catch (ex) {
                        // The return response was not valid Json.
                        zivios.createErrorDialog(ex.message);
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                        return response;
                    }
                }),

                // Error.
                error: function (response, ioArgs) {
                    if (response.message) {
                        zivios.createErrorDialog(response.message);
                    }
                    dijit.byId(attrSubmit).attr('disabled', false);
                    dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                }
            });
        },

        formXhrPost: function(formid,url) {
            var form = dijit.byId(formid);
            if (form.isValid()) {
                zivios.doXhrPost(formid,url);
            } else {
                dojo.byId('alertdata').innerHTML += "<div style=\"margin-top: 5px; padding: 5px; border-bottom: 1px solid #7eabcd;\">"
                    + "Missing or invalid entries detected in form.</div>";
                dijit.byId('ztoolbar.alerts')._openDropDown();
            }
        },

        createErrorDialog: function (errorMsg) {
            var dialog = new dijit.Dialog({
                title:   "System Error.",
                content: errorMsg,
            });

            dojo.body().appendChild(dialog.domNode);
            dialog.startup();
            dialog.show();
        },
        
        showTransaction: function (id) {
            var trantab = dijit.byId('transsrvtabs01');
            if (trantab == undefined) {
                //Transaction tab not yet open!
                zivios.loadApp('/transaction/index/id/'+id,
                                            'ztabs','ztransactions',
                                            'Zivios Transactions');
            } else {
                zivios.loadApp('/transaction/transdetail/id/'+id,
                               'transsrvtabs01','transdetail'+id,'Transaction #'+id);
            }
        },
            
        createInfoDialog: function (content,title) {
            var dialog = new dijit.Dialog({
                title:   title,
                content: content,
            });

            dojo.body().appendChild(dialog.domNode);
            dialog.startup();
            dialog.show();
        },
        
        createDialogFromUrl: function (href,title) {
            var dialog = new dijit.Dialog({
                title:   title,
                href: href,
            });

            dojo.body().appendChild(dialog.domNode);
            dialog.startup();
            dialog.show();
            dialog.refresh();
        },
        
		ioIframeGetJson: function (formid,url){
            var attrSubmit = formid + '-submit';
            var attrSubmitLabel = dijit.byId(attrSubmit).attr('label');
            
            dijit.byId(attrSubmit).attr('disabled', true);
            dijit.byId(attrSubmit).attr('label', 'Please wait...');

			var td = dojo.io.iframe.send({
				url: url,
				form: formid,
				method: "post",
				timeoutSeconds: 5,
				preventCache: true,
				handleAs: "json",
				handle: function(res, ioArgs){
                    console.debug("Json recieved:");
                    console.debug(res);
					try {
                        response = dojo.fromJson(res || null);
                        // re-enable the form
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);

                        //Check for errors and populate the install log div
                        if (response.sysErr) {
                            dojo.byId('alertdata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 40px;\">Error: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.alerts')._openDropDown();
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            return response;
                        }

                        // Check for System Exceptions
                        if (response.sysExp) {
                            dojo.byId('exceptiondata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Exception: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.message + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">File: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.file + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Line: </div>" +
                                "<div style=\"padding: 2px; float: left;\">" + response.line + "</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "<div style=\"padding: 2px; float:left; width: 70px;\">Trace: </div>" +
                                "<div style=\"padding: 2px; float: left;\">Check Zivios Log.</div>" +
                                "<div style=\"clear: both;\"></div>" +
                                "</div>";

                            dijit.byId('ztoolbar.exceptions')._openDropDown();
                            dijit.byId(attrSubmit).attr('disabled', false);
                            dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                            return response;
                        }

                        // Check for notifications
                        if (response.sysNotify) {
                            dojo.byId('notifydata').innerHTML +=
                                "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                                "<div style=\"padding: 2px;\">" + response.sysNotify + "</div>" +
                                "</div>";

                            dijit.byId('ztoolbar.notifications')._openDropDown();
                        }

                        // check response for possible callbacks and accompanying arguments.
                        // all callbacks are executed in the order they were registered.
                        if (response.callbacks) {
                            for (var i = 0; i < response.callbacks.length; i++) {
                                var callbackFunc = response.callbacks[i]['func'].toString() + "(";
                                if (response.callbacks[i]['args']) {
                                    var argArray = response.callbacks[i]['args'];
                                    for (var a = 0; a < argArray.length; a++) {
                                        callbackFunc += "'" + argArray[a].toString() + "'";
                                        if (a + 1 < argArray.length) {
                                            callbackFunc += ",";
                                        }
                                    }
                                }
                                callbackFunc += ")";
                                var doCallback = new Function(callbackFunc);
                                doCallback();
                            }
                        }

                        // check for possible div updates
                        if (response.divdata) {
                            for (var d = 0; d < response.divdata.length; d++) {
                                var datadiv = dojo.byId(response.divdata[d]['div']);
                                if (undefined === response.divdata[d]['append']) {
                                    datadiv.innerHTML = response.divdata[d]['content'];
                                } else {
                                    datadiv.innerHTML += response.divdata[d]['content'];
                                }
                            }
                        }

                        return response;

                    } catch (ex) {
                        // The return response was not valid Json.
                        zivios.createErrorDialog('From IoFrame Json Function: '+ex.message);
                        dijit.byId(attrSubmit).attr('disabled', false);
                        dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                        return response;
                    }
                },							
				error: function (response,ioArgs) {
                    if (response.message) {
                        zivios.createErrorDialog(response.message);
                    }
                    dijit.byId(attrSubmit).attr('disabled', false);
                    dijit.byId(attrSubmit).attr('label', attrSubmitLabel);
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                }
			});
		},

        /**
         * works off a global div.
         */
        toggleLoading: function() {
            var loading;
            if (null !== (loading = dojo.byId('loadingmsg'))) {
                if (loading.style.display == "block") {
                    zivios.hideDiv('loadingmsg');
                } else {
                    zivios.showDiv('loadingmsg');
                }
            }
        },

        /**
         * perform an xhr post.
         */
        postxhr: function(call, div, content, contentType, returnResultType, showDivsPostLoad, hideDivsPostLoad, 
                          showDivsPreLoad, hideDivsPreLoad, callerId) {
            var url = call;
            var container;
            var dataSend;
            var caller;
            var enableCallerAfterCall = false;
            var isDijit;
            var formContent = '';


            if (undefined === (container = dijit.byId(div))) {
                if (null !== (container = dojo.byId(div))) {
                    isDijit = false;
                } else {
                    if (div == "") {
                        // no div specified for return context loading. Check responseType
                        if (returnResultType != 'json') {
                            alert('Invalid call to function postxhr. Missing data: "div" for requested responseType.');
                            return false;
                        } else {
                            // no div is specified; return context is json. Allow request processing.
                            isDijit = false;
                        }
                    } else {
                        // response div not found by ID; try querying by class.
                        var tdiv = "." + div;
                        if ("" == dojo.query(tdiv)) {
                            alert('Response "div" unavailable. Please check your call params to function.');
                            return false;
                        } else {
                            dojo.query(tdiv).forEach(function(node, index, arr){
                                container = node;
                            });
                        }

                        isDijit = false;
                    }
                }
            } else {
                isDijit = true;
            }

            if (undefined !== (caller = dijit.byId(callerId))) {
                // caller is a dijit
                caller.attr('disabled', true);
                enableCallerAfterCall = true;
            }

            switch (contentType) {
                case "form": 
                    // dojo form
                    formData = dijit.byId(content);

                    if (!formData.validate()) {
                        zivios.flashMessage('Missing or invalid data in form fields.', 'error');

                        if (enableCallerAfterCall) {
                            caller.attr('disabled', false);
                        }

                        return false;
                    }

                    formContent = content;
                    break;

                case "text": 
                    dataSend = content;
                    break;

                case "json": 
                    dataSend = {'data': content};
                    break;

                case "json_encoded": 
                    try {
                        // for json sent via callbacks as arguments;
                        var testJson = dojo.fromJson(content);
                        dataSend = testJson;
                    } catch (e) {
                        dataSend = content;
                    }
                    break;

                default: 
                    // create error object and pass to error Handler. This is a developer
                    // level error. 
                    alert('Error. Unknown contentType.');
                    return;
            }

            

            if (hideDivsPreLoad instanceof Array && hideDivsPreLoad.length > 0) {
                for (ac = 0; ac < hideDivsPreLoad.length; ac++) {
                    zivios.hideDiv(hideDivsPreLoad[ac]);
                }
            }

            if (showDivsPreLoad instanceof Array && showDivsPreLoad.length > 0) {
                for (ac = 0; ac < showDivsPreLoad.length; ac++) {
                    zivios.showDiv(showDivsPreLoad[ac]);
                }
            }

            zivios.toggleLoading();

            dojo.xhrPost({
                url: url,
                form: formContent,
                content: dataSend,
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: returnResultType,
                },

                load: dojo.hitch(this, function(response, ioArgs) {
                    zivios.toggleLoading();
                    try {
                        responseTest = dojo.fromJson(response);
                        if (responseTest.sysErr == 1 || responseTest.sysExp == 1) {

                            // system error or exception raised. Pass response object to
                            //zivios.handleErrorDisplay(responseTest, ioArgs);
                            if (responseTest.sysErr == 1) {
                                zivios.flashMessage(responseTest.message, "error");
                            } else {
                                zivios.flashMessage(responseTest.message, "exception");
                            }

                            if (enableCallerAfterCall) {
                                caller.attr('disabled', false);
                            }

                            return;
                        } else {
                            // response is valid json, however, not an error obj.
                            // output request must be json -- continue processing.
                            // @note: we do not override returnType based on this test. 
                            //        returnType is honored regardless of detection.
                        }
                    } catch (e) {
                        // exception caught when force converting string to json.
                        // continue processing request, output rendering must be text.
                    }

                    // scroll to page top
                    scroll(0, 0);

                    // check rendering options.

                    switch (returnResultType) {
                        case "text": case "html":  
                            if (isDijit) {
                                container.attr('content', response);
                            } else {
                                container.innerHTML = response;
                                dojo.parser.parse(container);
                            }

                            break;

                        case "json": 
                            // check for response messages
                            response = dojo.fromJson(response);

                            // Check for notifications
                            if (response.sysNotify) {
                                zivios.flashMessage(response.sysNotify, "notify");
                            }
                            
                            // handle callbacks.
                            if (response.callbacks) {
                                for (var i = 0; i < response.callbacks.length; i++) {
                                    var callbackFunc = response.callbacks[i].func.toString() + "(";
                                    if (response.callbacks[i].args) {
                                        var argArray = response.callbacks[i].args;
                                        for (var a = 0; a < argArray.length; a++) {
                                            if (argArray[a].substring(0,1) == '[') {
                                                // arrays are not encapsulated in single quotes
                                                callbackFunc += argArray[a].toString();
                                            } else {
                                                callbackFunc += "'" + argArray[a].toString() + "'";
                                            }
                                            if (a + 1 < argArray.length) {
                                                callbackFunc += ",";
                                            }
                                        }
                                    }
                                    callbackFunc += ")";
                                    var doCallback = new Function(callbackFunc);
                                    doCallback();
                                }
                            }

                            break;
                    }

                    // hide divs postload
                    if (hideDivsPostLoad instanceof Array && hideDivsPostLoad.length > 0) {
                        for (ac = 0; ac < hideDivsPostLoad.length; ac++) {
                            zivios.hideDiv(hideDivsPostLoad[ac]);
                        }
                    }

                    // show divs postload
                    if (showDivsPostLoad instanceof Array && showDivsPostLoad.length > 0) {
                        for (ac = 0; ac < showDivsPostLoad.length; ac++) {
                            zivios.showDiv(showDivsPostLoad[ac]);
                        }
                    }
                    
                    // ensure the dijit has not been destroyed by a callback
                    if (undefined !== (caller = dijit.byId(callerId))) {
                        if (enableCallerAfterCall) {
                            caller.attr('disabled', false);
                        }
                    }
                }),
    
                error: function (response, ioArgs) {
                    //zivios.handleErrorDisplay(response, ioArgs);
                    zivios.flashMessage(response, "error");
                }
            });
        },

        flashMessage: function (msg, msgType) {

            switch (msgType) {
                case "exception":
                dojo.byId('exceptiondata').innerHTML +=
                   "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                   "<div style=\"padding: 2px; float:left; width: 70px;\">Exception: </div>" +
                   "<div style=\"padding: 2px; float: left;\">" + msg + "</div>" +
                   "<div style=\"clear: both;\"></div>" + 
                   "</div>";

                    dijit.byId('ztoolbar.exceptions')._openDropDown();
                    break;

                case "error":
                   dojo.byId('alertdata').innerHTML +=
                        "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                        "<div style=\"padding: 2px; float:left; width: 40px;\">Error: </div>" +
                        "<div style=\"padding: 2px; float: left;\">" + msg + "</div>" +
                        "<div style=\"clear: both;\"></div>" +
                        "</div>";

                    dijit.byId('ztoolbar.alerts')._openDropDown();
                    break;

                case "notify": 
                    dojo.byId('notifydata').innerHTML +=
                        "<div style=\"margin-top: 10px; padding: 2px; border-bottom: 1px solid #7eabcd;\">" +
                        "<div style=\"padding: 2px;\">" + msg + "</div>" +
                        "</div>";

                    dijit.byId('ztoolbar.notifications')._openDropDown();
                    break;
            }
        },

        
        // Keeping it simple and not integrating with notification / alert / exception
        // system. The handler needs to be isolated first.
        logout: function() {
            dojo.xhrGet ({
                url: '/default/index/logout',
                handleAs: 'json',
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: "json",
                },

                load: function (response, ioArgs) {
                    if (response.logout == '1') {
                        // redirect to app root.
                        zivios.redirectUrl(response.url);
                    } else {
                        // logout failed...
                        zivios.createInfoDialog('The logout appears to have failed. Contact a system administrator', 'Logout failed');
                    }

                    return response;
                },

                error: function (response, ioArgs) {
                    if (response.message) {
                        zivios.createErrorDialog('HTTP status code: ' + response.message, 'Error');
                    } else {
                        zivios.createErrorDialog(ioArgs.xhr.status, 'Error');
                    }
                    console.error("HTTP status code: ", ioArgs.xhr.status);
                    return response;
                },
            });
        },

        js_clock:  function() {
            if (dojo.byId('clock_div') == null) {
                return false;
            }
            var clock_time = new Date();
            var clock_hours = clock_time.getHours();
            var clock_minutes = clock_time.getMinutes();
            var clock_seconds = clock_time.getSeconds();
            var clock_suffix = "AM";

            if (clock_hours > 11) {
                clock_suffix = "PM";
                clock_hours = clock_hours - 12;
            }

            if (clock_hours == 0)
                clock_hours = 12;

            if (clock_hours < 10)
                clock_hours = "0" + clock_hours;

            if (clock_minutes < 10)
                clock_minutes = "0" + clock_minutes;

            if (clock_seconds < 10)
                clock_seconds = "0" + clock_seconds;

            var clock_div = document.getElementById('js_clock');
            clock_div.innerHTML = clock_hours + ":" + clock_minutes + ":" +
            clock_seconds + " " + clock_suffix;

            setTimeout("zivios.js_clock()", 1000);
        },
    });
})();

