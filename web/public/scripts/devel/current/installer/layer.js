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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

dojo.provide("installer.layer");

(function() {
    dojo.require("dijit.layout.ContentPane");
    dojo.require("dijit.layout.BorderContainer");
    dojo.require("dijit.layout.TabContainer");
    dojo.require("dijit.layout.AccordionContainer");
    dojo.require("dijit.form.FilteringSelect");
    dojo.require("dijit.form.ValidationTextBox");
    dojo.require("dijit.form.SimpleTextarea");
    dojo.require("dijit.form.Button");
    dojo.require("dijit.form.NumberSpinner");
    dojo.require("dijit.form.Form");
    dojo.require("dijit.Dialog");
    dojo.require("dijit.Toolbar");
    dojo.require("dijit.Tooltip");
    dojo.require("dojox.data.QueryReadStore");
    dojo.require("dojo.back");
    dojo.require("dojo.data.ItemFileReadStore");
    dojo.require("dojo.parser");

    dojo.addOnLoad(function() {
        // check base paths & hash
        var path = window.location.pathname;
        var qs   = window.location.search.substring(1);
        var hash = window.location.hash;
        var resume = false;

        // no uri paths or query strings are accepted; system works entirely off hashes
        if (path != "/" || qs != "") {
            dojo.doc.location = "/#/installer/index/main";
        } else {
            if (hash == "") {
                hash = "#/installer/index/main";
                window.location.hash = "/installer/index/main";
            }

            var call = hash.substring(1);
        }

        // Initialize console log feed
        installer.feedConsole();
        
        // perform xhr call upon primary layout load.
        installer.getxhr(call, 'primaryContent', '', 'text', ['installScreen'], ['preloader'], [], [], true, true);
    });

    /**
     * Installer class
     */
    dojo.mixin(installer, {

        /**
         * Perform an XHR GET call. Simple http links or dojo buttons should make calls to this function.
         * For form posts, please see: 'postxhr'.
         *
         * @param string call:             'url to call -- this is the hash read from the uri component'
         * @param string div:              'target div to populate return result in. Valid only if content type is text'
         * @param string content:          'json encoded data to send server side'
         * @param string returnResultType: 'json or text'
         * @param array showDivsPostLoad:  'loops non-empty array and switchs display to block AFTER xhr call'
         * @param array hideDivsPostLoad:  'loops non-empty array and switchs display to hide AFTER xhr call'
         * @param array showDivsPreLoad:   'loops non-empty arrays and switchs display to block BEFORE xhr call'
         * @param array hideDivsPreLoad:   'loops non-empty arrays and switchs display to hide BEFORE xhr call'
         * @param boolean skipHistory:     'do not record call in history object'
         * @param boolean resizeLayout:    'call resize on "primaryContent" dijit'
         * @param string callerId:         'the "id" of the calling dijit. Dijit is disabled onclick'
         *
         * @return void
         */
        getxhr: function(call, div, content, returnResultType, showDivsPostLoad, hideDivsPostLoad, showDivsPreLoad, 
                         hideDivsPreLoad, skipHistory, resizeLayout, callerId) {

            // check and set required params
            var url = call;
            var container;
            var caller;
            var isDijit = false;
            var callerIsdijit = false;
            var enableCallerAfterCall = false;

            if (undefined === (container = dijit.byId(div))) {
                container = dojo.byId(div);
            } else {
                isDijit = true;
            }

            if (undefined === (caller = dijit.byId(callerId))) {
                if (null !== (caller = dojo.byId(callerId))) {
                    // caller ID specified. Is a div
                    enableCallerAfterCall = true;
                }
            } else {
                // caller is a dijit
                caller.attr('disabled', true);
                callerIsdijit = true;
                enableCallerAfterCall = true;
            }

            if (undefined !== returnResultType) {

                var returnType;
                var dataSend;

                switch (returnResultType) {
                    case "json":
                        returnType  = 'json';
                        break;

                    case "text": case "html":
                        returnType  = 'text';
                        break;

                    default: 
                        returnType  = 'text';
                }
            }

            if (undefined === content) {
                dataSend = '';
            } else {
                dataSend = content;
            }

            if (hideDivsPreLoad instanceof Array && hideDivsPreLoad.length > 0) {
                for (ac = 0; ac < hideDivsPreLoad.length; ac++) {
                    installer.hideDiv(hideDivsPreLoad[ac]);
                }
            }

            if (showDivsPreLoad instanceof Array && showDivsPreLoad.length > 0) {
                for (ac = 0; ac < showDivsPreLoad.length; ac++) {
                    installer.showDiv(showDivsPreLoad[ac]);
                }
            }

            // xhr request processing
            dojo.xhrGet({
                url: url,
                content: dataSend,
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: returnType,
                },

                load: dojo.hitch(this, function(response, ioArgs) {

                    // ensure that return content (regardless of output request) 
                    // is not a json encoded error object.
                    try {
                        responseTest = dojo.fromJson(response);
                        if (responseTest.sysErr == 1 || responseTest.sysExp == 1) {
                            // system error or exception raised. Pass response object to
                            // error handler.
                            installer.handleErrorDisplay(responseTest, ioArgs);
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

                    // enable caller button
                    if (enableCallerAfterCall) {
                        if (callerIsdijit) {
                            caller.attr("disable", false);
                        } else {
                            // re-enable div.

                        }
                    }
                    
                    // scroll to the top
                    scroll(0, 0);

                    if (returnType == "text") {
                        if (isDijit) {
                            container.attr('content', response);
                        } else {
                            container.innerHTML = response;
                            dojo.parser.parse(container);
                        }

                    } else {
                        // handle json response object.
                        alert("json response");
                        alert(response);
                    }

                    // hide divs postload
                    if (hideDivsPostLoad instanceof Array && hideDivsPostLoad.length > 0) {
                        for (ac = 0; ac < hideDivsPostLoad.length; ac++) {
                            installer.hideDiv(hideDivsPostLoad[ac]);
                        }
                    }
                    
                    // show divs postload
                    if (showDivsPostLoad instanceof Array && showDivsPostLoad.length > 0) {
                        for (ac = 0; ac < showDivsPostLoad.length; ac++) {
                            installer.showDiv(showDivsPostLoad[ac]);
                        }
                    }
                    
                    if (resizeLayout == true) {
                        dijit.byId('primaryLayout').resize();
                    }

                    // handle callbacks here.
                }),

                error: function (response, ioArgs) {
                    installer.handleErrorDisplay(response, ioArgs);
                }
            });
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
                        installer.flashMessage('Missing or invalid data in form fields.', 'error');

                        if (enableCallerAfterCall) {
                            caller.attr('disabled', false);
                        }
                        
                        return false;
                    }

                    formContent = content;
                    break;

                case "text": 
                    // json format
                    dataSend = content;
                    break;

                case "json": 
                    // text format
                    dataSend = {'data': content};
                    break;

                default: 
                    // create error object and pass to error Handler. This is a developer
                    // level error. 
                    alert('Error. Unknown contentType.');
                    return;
            }

            if (hideDivsPreLoad instanceof Array && hideDivsPreLoad.length > 0) {
                for (ac = 0; ac < hideDivsPreLoad.length; ac++) {
                    installer.hideDiv(hideDivsPreLoad[ac]);
                }
            }

            if (showDivsPreLoad instanceof Array && showDivsPreLoad.length > 0) {
                for (ac = 0; ac < showDivsPreLoad.length; ac++) {
                    installer.showDiv(showDivsPreLoad[ac]);
                }
            }

            dojo.xhrPost({
                url: url,
                form: formContent,
                content: dataSend,
                timeout: 0,
                headers: {
                    XHR_ERROR_HANDLEAS: returnResultType,
                },

                load: dojo.hitch(this, function(response, ioArgs) {
                    try {
                        responseTest = dojo.fromJson(response);
                        if (responseTest.sysErr == 1 || responseTest.sysExp == 1) {

                            // system error or exception raised. Pass response object to
                            installer.handleErrorDisplay(responseTest, ioArgs);

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

                            if (response.success) {
                                if (response.msg) {
                                    installer.flashMessage(response.msg, 'success');
                                }
                            } else {
                                if (response.msg) {
                                    installer.flashMessage(response.msg, 'error');
                                }
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
                            installer.hideDiv(hideDivsPostLoad[ac]);
                        }
                    }
                    
                    // show divs postload
                    if (showDivsPostLoad instanceof Array && showDivsPostLoad.length > 0) {
                        for (ac = 0; ac < showDivsPostLoad.length; ac++) {
                            installer.showDiv(showDivsPostLoad[ac]);
                        }
                    }
                    
                    // enable caller
                    if (enableCallerAfterCall) {
                        caller.attr('disabled', false);
                    }
                }),

                error: function (response, ioArgs) {
                    installer.handleErrorDisplay(response, ioArgs);
                }
            });
        },
        
        /**
         * Display a message by making visible the global 'flashgmsg' div.
         * 
         * @param string msg: 'the message to be displayed'
         * @param string type: 'either "success" or "error"
         */
        flashMessage: function(msg, type) {
            var div = dojo.byId('flashmsg');
            var setdelay = 1000;
            div.innerHTML = msg;

            switch(type) {
                case "success": 
                    dojo.style(div, {
                        "backgroundColor": "#54ff59",
                        "color": "#333333",
                        "border": "2px solid #43CD80"
                    });
                    break;

                case "error": 
                    setdelay = 2000;
                    dojo.style(div, {
                        "backgroundColor": "#CD5555",
                        "color": "white",
                        "border": "2px solid #DC143C"
                    });
                    break;
            }

            dojo.style(div, "display", "block");

            var anim0 = dojo.fadeIn({ node: div, duration:700 });
            var anim1 = dojo.fadeOut({ node: div, duration:700, delay: setdelay });
            var anim2 = dojo.fadeOut({ node: div, 
                properties: {
                    display: { end: "none" }
                }
            });
            
            dojo.fx.chain([anim0,anim1,anim2]).play();
        },

        handleErrorDisplay: function(response, ioArgs) {
            //alert("Error message: " + response.message);
            //alert("Args: " + ioArgs);

            var priDiv = dojo.byId('installScreen');
            var loader = dojo.byId('preloader');
            var loadingmsg = dojo.byId('loadingmsg');
            
            if (loader.style.display != "none") {
                dojo.style(loader, "display", "none");
            }

            if (priDiv.style.display != "block") {
                dojo.style(priDiv, "display", "block");
            }

            if (loadingmsg.style.display != "none") {
                installer.hideDiv('loadingmsg');
            }

            // resize the layout (just in case)
            dijit.byId('primaryLayout').resize();
            
            // display the error div with the message.
            dijit.byId('systemError').show();
            dojo.byId('errorMessage').innerHTML = response.message;
            
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

        feedConsole: function() {
            var intervalId = setInterval(function() {
                var cdata = new dojo.data.ItemFileReadStore({url: "/index/getconsoledata"});
                var request = cdata.fetch({query: {type:"logline"}, onComplete: function(items, request) {
                        if (items.length > 0) {
                            dojo.byId("consoleLog").innerHTML = "";
                            for (var i = 0; i < items.length; i++) {
                                var item = items[i];
                                dojo.byId("consoleLog").innerHTML += cdata.getLabel(item) + "<br/>";
                            }
                            dojo.byId('consoleLog').innerHTML += '<div style="height: 1px; width: 99%; border-bottom: 1px dashed #ccc;"></div>';
                        }
                    },
                });
            }, 10000);
        },

        // clear contents from a div
        clearDiv: function(divid) {
            dojo.byId(divid).innerHTML = '';
        },

        notifications: function() {
            console.debug('notify called.');
        },

        alerts: function() {
            console.debug('alerts called');
        },

        exceptions: function() {
            console.debug('exceptions called');
        },
    });
})();

