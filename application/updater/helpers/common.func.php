<?php
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

function getCnConfig()
{
    $secKey = APPLICATION_PATH . '/security/keys/zp01';

    $zadminConfig = APPLICATION_PATH . '/config/zadmin.ini';
    if (!file_exists($zadminConfig) || !is_readable($zadminConfig)) {
        exit('Administrative user configuration file not found (or could not be read).');
    }

    if (!$zadminInfo = parse_ini_file($zadminConfig, true)) {
        exit('Could not parse admin configuration.');
    }
    
    
    $basedn = 'cn=config';
    $dn     = 'cn=config';
    $pass   = $zadminInfo['credentials']['password'];
    
    // A bug in the installer results in the password hash getting
    // (base64) encoded twice. Issue fixed in 0.6.2
    if (PVERSION <= "0.6.1") {
        $pass = base64_decode($pass);
    }

    $dpass = Zivios_Security::decrypt($pass, $secKey);
    $conn = ldap_connect('localhost', 389);
    ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);

    if (!ldap_bind($conn, $dn, $dpass)) {
        exit('Could not bind to LDAP server. Is zvSlapd running?');
    }
    
    return $conn;
}

function addToConfig($schemaData,$rdn,$parent)
{
    $conn = getCnConfig();
    $targetDn = $rdn . ',' . $parent; 

    if (!ldap_add($conn, $targetDn, $schemaData)) {
        echo "Schema addition failed. Please check system logs.";
        exit();
    }
    return 1;
}

function updateSchema($schema, $attrs, $action)
{
    $conn = getCnConfig();

    switch ($action) {
        case "add":
            $call = 'ldap_mod_add';
            break;

        case "replace":
            $call = 'ldap_mod_replace';
            break;
    }
    
    if (!$call($conn, $schema, $attrs)) {
        echo "Schema modification failed. Please check logs.";
        exit();
    }

    return 1;
}

function getDbResource()
{
    $aconfig = getAppConfig();
    $dbhost = $aconfig['database']['host'];
    $dbuser = $aconfig['database']['username'];
    $dbpass = $aconfig['database']['password'];
    $dbname = $aconfig['database']['name'];

    if (!$conn = mysql_connect($dbhost, $dbuser, $dbpass)) {
        exit('Connection to MySQL could not  be established. Please ensure the database'.
            ' service is running.');
    }

    if (!$db = mysql_select_db($dbname, $conn)) {
        exit('Could not select Zivios database.');
    }

    return $conn;
}

function getAppConfig()
{
    $appConfigIni = APPLICATION_PATH . '/config/app.config.ini';

    if (!file_exists($appConfigIni) || !is_readable($appConfigIni)) {
        exit('Zivios master configuration file not found or could not be read.');
    }

    if (!$appConfig = parse_ini_file($appConfigIni, true)) {
        exit('Could not parse master configuration file.');
    }

    return $appConfig;
}

function runSql($sqldata)
{
    if (!is_array($sqldata)) {
        exit('function requires SQL statements to be passed as an array.');
    }

    $resource = getDbResource();

    foreach ($sqldata as $sql) {
        if (!mysql_query($sql, $resource)) {
            exit('Failed executing SQL statement: ' . $sql);
        }
    }

    return 1;
}

function checkReqScripts($scripts)
{
    if (!is_array($scripts) || empty($scripts)) {
        exit('Error::Could not check required scripts.');
    }
    
    $execScripts = array();
    foreach ($scripts as $script) {
        $scriptName = U_SCRIPT_BASE . '/' . $script;
        if (!file_exists($scriptName)) {
            exit('Required update script not found:<br/><ul> ' . $scriptName . '</ul><br/>Cannot continue.');
        } else {
            $execScripts[] = $scriptName;
        }
    }

    return $execScripts;
}

function execScripts($scripts)
{
     if (!is_array($scripts) || empty($scripts)) {
        exit('Error::Could not check required scripts.');
    }

    foreach ($scripts as $script) {
        if (!include_once($script)) {
            exit('Could not include / execute script: <ul>' . $script . '</ul>.<br/>Update failed.');
        }
    }
}

function doUpdateCleanup()
{
    $pRelease = U_INTCONF_BASE . '/prior_release';

    if (file_exists($pRelease)) {
        if (!unlink($pRelease)) {
            echo 'The update was completed successfully, however, the following file could not be removed: ';
            echo '<ul>'.$pRelease.'</ul>';
            echo '<br/>Please remove this file manually and simply <a href="'.$_SERVER['PHP_SELF'].'">Continue to Zivios</a>';

            exit();
        }
    }

    startBody('Zivios Updated!');
    renderTemplate('updateCompleted');
    endBody();
}

function renderTemplate($template)
{
    $templateFile =  U_TEMPLATE_BASE . '/' . $template . '.php';

    if (!include_once($templateFile)) {
        exit('Error: could not include requested template file: <ul>' . 
            $templateFile . '</ul>');
    }
}

function startBody($title)
{
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>'.$title.'</title> 
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />      
    <style type="text/css">
    <!--
    @import "public/css/updater/base.css";
    -->
    </style>
</head>
<body>
';
}

function endBody()
{
echo '</body>
</html>';
}


