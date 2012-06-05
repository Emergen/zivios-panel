#!/usr/bin/php

<?php
    set_time_limit(50);
    require('phpagi.php');

    $agi = new AGI();
    $exten = $argv[1];
    $callerid = $agi->parse_callerid();
    $agi->verbose("Call to ".$exten." from ".$callerid);
    $ldapcon = ldap_connect("192.168.0.226");
    if (ldap_bind($ldapcon)) {
        $filter = "(&(emsastexten=".$exten.")(objectclass=emsastuser))";
        $sr = ldap_search($ldapcon,'dc=zivios,dc=net',$filter);
        $entries = ldap_get_entries($ldapcon,$sr);
        $count = $entries['count'];
        $dialarray = array();
        if ($count == 1) {
            $uid = $entries[0]['uid'][0];
            $accountlockout = $entries[0]['emsaccountlockout'][0];
            $astlockout = $entries[0]['emsastdisable'][0];
            
            if ($accountlockout != 1 && $astlockout != 1) {
                $agi->verbose("matched to uid :".$uid);
                $agi->set_variable("TODIAL",$uid);
                $agi->exec("Dial","SIP/".$uid);
            } else {
                $agi->set_variable("ERROR","NOTALLOWED");
                $agi->verbose("Call Prohibited by admin");
                $agi->exec("Playback","cannot-complete-as-dialed");
            }
        } else {
            $agi->set_variable("ERROR","NOTFOUND");
            $agi->verbose("Extension Not Found");
            $agi->exec("Playback","no-route-exists-to-dest");
        }
    } else {
        $agi->verbose("Unable to bind to server");
        $agi->exec("Playback","an-error-has-occured");
    }
    ldap_close($ldapcon);
    $agi->verbose("Hello");
?>
