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
if (!defined('PVERSION') || !defined('ZVERSION')) {
    exit('Required version data missing. Cannot run update.');
}

// Ensure the administrator has unlocked the update process and set
// the update IP for his local workstation.
$updaterConfig = APPLICATION_PATH . '/config/updater.ini';
if (file_exists($updaterConfig) && is_readable($updaterConfig)) {
    if (!$updaterInfo = parse_ini_file($updaterConfig,true)) {
        exit('Could not parse updater configuration.');
    }
    
    $status = $updaterInfo['general']['status'];
    $authIp = $updaterInfo['general']['auth_ip'];

    if (!include_once(APPLICATION_PATH . '/updater/helpers/base.php')) {
        exit('Could not include common helpers for update process.');
    }

    if ($status != 'unlocked') {
        startBody('Zivios Update');
        echo '<div class="layout"><div class="note">';
        echo 'Zivios has been updated, however, the updater has been locked by the system administrator.<br/><br/>';
        echo 'Please contact your system administrator, or; if you are the system administrator, please consult';
        echo ' the <a href="http://wiki.zivios.org" target="_blank">Zivios wiki</a> on how to proceed.';
        echo '</div></div>';
        endBody();
        exit();
    } elseif ($_SERVER['REMOTE_ADDR'] != $authIp) {
        startBody('Zivios Update');
        echo '<div class="layout"><div class="note">';
        echo 'You are not authorized to run Zivios update. Please contact your system administrator.<br/><br/>';
        echo 'If you are the system administrator, please consult';
        echo ' the <a href="http://wiki.zivios.org" target="_blank">Zivios wiki</a> on how to proceed.';
        echo '</div></div>';
        endBody();
        exit();
    }
}

if (isset($_GET['runUpdate'])) {

    if (!include_once(APPLICATION_PATH . '/updater/helpers/base.php')) {
        exit('Could not include common helpers for update process.');
    }

    $pVersion = trim(strip_tags(escapeshellcmd(PVERSION)));
    $pVersion = preg_replace('/\./', '', $pVersion);

    $cVersion = trim(strip_tags(escapeshellcmd(ZVERSION)));
    $cVersion = preg_replace('/\./', '', $cVersion);

    if ($cVersion < $pVersion) {
        startBody('Zivios Upgrade Process');
        echo '<div class="layout"><div class="alert">';
        echo 'The system appears to have been downgraded. No automatic fix is possible here.<br /><br />';
        echo '&nbsp;&nbsp;Your current Zivios version is: ' . ZVERSION . '<br />';
        echo '&nbsp;&nbsp;The prior Zivios version was: ' . PVERSION . '<br /><br />';
        echo 'Contact your system administrator or a Zivios support channel.';
        echo '</div></div>';
        endBody();
        exit();
    } elseif ($cVersion == $pVersion) {
        startBody('Zivios Upgrade Process');
        echo '<div class="layout"><div class="info">';
        echo 'You system appears to be up-to-date, however, the prior version file was not removed ';
        echo 'by the update process. This could be a bug in Zivios, or, your update process did not ';
        echo 'complete successfully. You can try bypassing this process by removing the file: <br /><br />';
        echo '&nbsp;&nbsp;&nbsp;' . INTCONFIG_PATH . '/prior_release<br/>';
        echo '<br />Refresh this page after removing the file.';
        echo '</div></div>';
        exit();
    } else {
        $numScripts = $cVersion - $pVersion;
        if (substr($cVersion, 0,1) == '0') {
            $aZero = '0';
        } else {
            $aZero = '';
        }
        
        $scripts = array();
        for ($c=0; $c < $numScripts; $c++) {
            $pVersion++;
            $scripts[] = 'rupdate' . $aZero . $pVersion . '.php';
        }
        
        // Ensures all scripts exist
        $rScripts = checkReqScripts($scripts);

        // Execute scripts sequentially
        execScripts($rScripts);

        // Cleanup prior release data
        doUpdatecleanUp();
        exit();
    }
} else {
    session_name('zvUpdate');
    session_start();
    $_SESSION['confirmUpdate'] = '1';

    if (!include_once(APPLICATION_PATH . '/updater/helpers/base.php')) {
        exit('Could not include common helpers for update process.');
    }
    startBody('Zivios update');
    ?>
    <div class="layout">
    <div class="updateNotice">
    The Zivios base system has been changed from version <b><?php echo PVERSION; ?></b> to 
    <b><?php echo ZVERSION; ?></b>. <br /><br />
    Zivios needs to ensure that underlying updates are applied in a systematic manner. This process is
    automated and does not generally require input. It is however possible that some scripts may halt
    and ask you for additional information.
    <br /><br />
    Before you begin the process, it is recommended you check the <a href="http:/wiki.zivios.org" target="_new">Zivios Wiki</a>*
    and ensure that you have taken appropriate backups. 
    <br /><br />
    When you are ready, please click on the link below: 
    <ul>
       &raquo; <a href="<?php echo $_SERVER['PHP_SELF']; ?>?runUpdate=1"><b>Being Processing Updates</b></a> &laquo;
    </ul>
    *Link opens in new window.
    </div>
    </div>
    <?php
    endbody();
}
