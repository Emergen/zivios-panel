dependencies ={
    action:        "clean,release", 
    version:       "0.6.6", 
    releaseName:   "zivios-0.6.6", 
    releaseDir:    "../../../../release/",
    loader:        "default", 
    cssOptimize:   "comments", 
    optimize:      "shrinksafe", 
    layerOptimize: "shrinksafe", 
    copyTests:     false, 

    layers:  [
        {
        name: "../zivios/core.js",
        layerDependencies: [],
        dependencies: [
            "zivios.core",
        ]
        }
    ],
    prefixes: [
        [ "dijit", "../dijit" ],
        [ "dojox", "../dojox" ],
        [ "zivios", "../zivios", "../../zivios/copyright.txt" ]
    ]
};
