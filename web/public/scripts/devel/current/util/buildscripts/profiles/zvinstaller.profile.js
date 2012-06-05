dependencies ={
    action:        "release", 
    version:       "0.6.6", 
    releaseName:   "zvinstaller-0.6.6",
    releaseDir:    "../../../../release/",
    loader:        "default", 
    cssOptimize:   "comments", 
    optimize:      "shrinksafe", 
    layerOptimize: "shrinksafe", 
    copyTests:     false, 

    layers:  [
        {
        name: "../installer/layer.js",
        layerDependencies: [],
        dependencies: [
            "installer.layer",
        ]
        }
    ],
    prefixes: [
        [ "dijit", "../dijit" ],
        [ "dojox", "../dojox" ],
        [ "installer", "../installer", "../../zivios/copyright.txt" ]
    ]
};
