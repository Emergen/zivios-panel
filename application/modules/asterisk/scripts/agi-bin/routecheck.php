#!/usr/bin/php

<?php
    set_time_limit(50);
    require('phpagi.php');

    $agi = new AGI();
    $route = $argv[1];
    $cidname = $agi->request['agi_calleridname'];
    $cidnum = $agi->request['agi_callerid'];
    $agi->verbose("Call from ".$cidname." num : ".$cidnum." for route ".$route);
    $ldapcon = ldap_connect("192.168.0.226");
    if (ldap_bind($ldapcon)) {
        $filter = "(&(emsastexten=".$cidnum.")(objectclass=emsastuser))";
        $sr = ldap_search($ldapcon,'dc=zivios,dc=net',$filter);
        $entries = ldap_get_entries($ldapcon,$sr);
        ldap_close($ldapcon);
        $count = $entries['count'];
        $dialarray = array();
        if ($count == 1) {
            $uid = $entries[0]['uid'][0];
            $accountlockout = $entries[0]['emsaccountlockout'][0];
            $astlockout = $entries[0]['emsastdisable'][0];
            $routes = $entries[0]['emsastroutesallowed'];

            if (in_array($route,$routes))
                $allowed = 1;
            else
                $allowed = 0;
            
            if ($accountlockout != 1 && $astlockout != 1 && $allowed) {
                $agi->verbose("matched to uid :".$uid);
                $agi->exec_goto('allowed');
            } else {
                $agi->set_variable("ERROR","NOTALLOWED");
                $agi->verbose("Call Prohibited by admin");
                $agi->exec("Playback","cannot-complete-as-dialed");
                $agi->exec_goto('disallowed');

            }
        } else {
            $agi->verbose("Couldnt find calling extension!!! Caller id Mangling!");
            $agi->exec("Playback","an-error-has-occured");
            $agi->exec_goto('disallowed');
        }
    } else {
           $agi->verbose("Couldnt Connect to Ldap Server");
           $agi->exec("Playback","an-error-has-occured");
            $agi->exec_goto('disallowed');
    }

    
            
