<?php
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

class Os_Linux_Services_Bind extends Os_Linux
{
    protected $_bindConfig;

    public function __construct()
    {
        parent::__construct();
        
        if (null === $this->_session->_bindcmds) {
            // Set base targets.
            $bindConfig = $this->getBindConfig();
            $bindcmds = array();
            $bindcmds['dnskeygen'] = $bindConfig->sbin . '/dnssec-keygen';
            
            // Link command array to session.
            $this->_session->_bindcmds = $bindcmds;
        }

        return $this;
    }

    public function iniBindConfig($data,$binduserPass,$webuser,$webgroup)
    {
        Zivios_Log::info('Initializing Bind configuration...', 'clogger');
        $dnsConfig = $this->getBindConfig();
        
        // Copy across required zone data to bind's etc folder.
        $configData = explode(',', $dnsConfig->etcConfigData);
        $confDir    = $dnsConfig->etc;

        foreach ($configData as $cdata) {
            $srcPath   = APPLICATION_PATH . 
                 '/library/Zivios/Install/Templates/bind/' . $cdata;
            $cdatapath = $confDir . '/' . $cdata;

            $this->_copyFile($srcPath, $cdatapath, '0644', 'root', 'root');
        }

       // Generate rndckey and template data.
       $cwd = getcwd();
       $workFolderTmp = $this->linuxConfig->tmpFolder . '/bind';
       $this->_createFolder($workFolderTmp, '0750', $webuser, $webgroup);

       if (!chdir($workFolderTmp)) {
           throw new Zivios_Exception('Could not change directory to: ' . $workFolderTmp);
       }

        $cmd = $this->_session->_bindcmds['dnskeygen'] . ' -a hmac-md5 -b 256 -n HOST rndckeydata';
        $this->_runLinuxCmd($cmd);

        if (!chdir($cwd)) {
            throw new Zivios_Exception('Could not restore directory change. Check logs.');
        }

        $d = dir($workFolderTmp);
        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..') {
                if (substr($entry, strrpos($entry, '.') + 1) == 'private') {
                    $fh = fopen($workFolderTmp.'/'.$entry, "r");
                    $contents = fread($fh, filesize($workFolderTmp.'/'.$entry));
                    fclose($fh);
                }
            }
        }

        $allC = split("\n",$contents);
        foreach ($allC as $c) {
            $len = (strlen($c) - 4) * -1;

            if (substr($c, 0, $len) == 'Key:') {
                $rndcKeyString = substr($c, 4);
                break;
            }
        }

        $templates   = array();
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/bind/named.conf.local.tmpl';
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/bind/named.conf.options.tmpl';
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/bind/rndc.key.tmpl';
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/bind/defaults.tmpl';
        $templates[] = APPLICATION_PATH . 
            '/library/Zivios/Install/Templates/bind/resolv.conf.tmpl';

        foreach ($templates as $template) {
            if (!file_exists($template) || !is_readable($template)) {
                throw new Zivios_Exception('Error: could not find / read bind template:' . 
                    $template);
            }
        }

        $vals = array();
        $vals['bind_user'] = 'zdnsuser';
        $vals['base_dn']   = $this->_session->localSysInfo['basedn'];
        $vals['master_ip'] = $this->_session->localSysInfo['ip'];
        $vals['bind_pass'] = $binduserPass;

        $namedlocaldata = Zivios_Util::renderTmplToCfg($templates[0], $vals);
        $namedlocaltmpl = $this->linuxConfig->tmpFolder . '/' . 'named.conf.local.tmpl';

        if (!$fp = fopen($namedlocaltmpl, 'w')) {
            throw new Zivios_Exception('Could not open file: ' . $namedlocaltmpl . ' for writing.');
        }

        if (fwrite($fp, $namedlocaldata) === FALSE) {
            throw new Zivios_Exception('Could not write data to file: ' . $namedlocaltmpl);
        }
        fclose($fp);

        $forwarderLine = '';
        if (isset($data['forwarder1']) && $data['forwarder1'] != "") {
            $forwarderLine .= "\t\t" . $data['forwarder1'] . ";\n";
        }
        
        if (isset($data['forwarder2']) && $data['forwarder2'] != "") {
            $forwarderLine .= "\t\t" . $data['forwarder2'] . ";\n";
        }

        $vals = array('forwarders' => $forwarderLine);
        $namedoptionsdata = Zivios_Util::renderTmplToCfg($templates[1], $vals);
        $namedoptionstmpl = $this->linuxConfig->tmpFolder . '/' . 'named.conf.options.tmpl';

        if (!$fp = fopen($namedoptionstmpl, 'w')) {
            throw new Zivios_Exception('Could not open file: ' . $namedoptionstmpl . ' for writing.');
        }

        if (fwrite($fp, $namedoptionsdata) === FALSE) {
            throw new Zivios_Exception('Could not write data to file: ' . $namedoptionstmpl);
        }
        fclose($fp);

        $vals = array('rndc_key' => ltrim($rndcKeyString));
        $rndckeydata = Zivios_Util::renderTmplToCfg($templates[2], $vals);
        $rndckeytmpl = $this->linuxConfig->tmpFolder . '/' . 'rndc.key.tmpl';

        if (!$fp = fopen($rndckeytmpl, 'w')) {
            throw new Zivios_Exception('Could not open file: ' . $rndckeytmpl . ' for writing.');
        }

        if (fwrite($fp, $rndckeydata) === FALSE) {
            throw new Zivios_Exception('Could not write data to file: ' . $rndckeytmpl);
        }
        fclose($fp);

        $vals = array('bind_user' => 'zdnsuser');
        $binddefaultsdata = Zivios_Util::renderTmplToCfg($templates[3], $vals);
        $defaultstmpl     = $this->linuxConfig->tmpFolder . '/' . 'defaults.tmpl';

        if (!$fp = fopen($defaultstmpl, 'w')) {
            throw new Zivios_Exception('Could not open file: ' . $defaultstmpl . ' for writing');
        }

        if (fwrite($fp, $binddefaultsdata) === FALSE) {
            throw new Zivios_Exception('Could not write data to file: ' . $defaultstmpl);
        }
        fclose($fp);

        $vals = array(
                    'search_domain' => $this->_session->localSysInfo['bindzone'],
                    'local_domain'  => $this->_session->localSysInfo['bindzone']
                );
        $resolvconfdata = Zivios_Util::renderTmplToCfg($templates[4], $vals);
        $resolvconftmpl = $this->linuxConfig->tmpFolder . '/' . 'resolvconf.tmpl';

        if (!$fp = fopen($resolvconftmpl, 'w')) {
            throw new Zivios_Exception('Could not open file: ' . $defaultstmpl . ' for writing');
        }

        if (fwrite($fp, $resolvconfdata) === FALSE) {
            throw new Zivios_Exception('Could not write data to file: ' . $resolvconftmpl);
        }
        fclose($fp);

        // Copy files across to their target locations.
        $rndc         = $dnsConfig->etc . '/rndc.key';
        $namedconf    = $dnsConfig->etc . '/named.conf.local';
        $namedoptions = $dnsConfig->etc . '/named.conf.options';
        $defaults     = $dnsConfig->etc . '/defaults';
        $resolvconf   = '/etc/resolv.conf';

        $this->_copyFile($namedlocaltmpl,   $namedconf,    '0640', 'zdnsuser', 'zdns');
        $this->_copyFile($namedoptionstmpl, $namedoptions, '0640', 'zdnsuser', 'zdns');
        $this->_copyFile($rndckeytmpl,      $rndc,         '0600', 'zdnsuser', 'zdns');
        $this->_copyFile($defaultstmpl,     $defaults,     '0600', 'zdnsuser', 'zdns');
        $this->_copyFile($resolvconftmpl,   $resolvconf,   '0644', 'root', 'root');
        
        Zivios_Log::info('Bind configuration initialized successfully', 'clogger');
        return $this;
    }

    public function serviceAction($script, $action)
    {
        $cmd = escapeshellcmd($script) . ' ' . escapeshellcmd($action);
        $this->_runLinuxCmd($cmd, true);
        sleep(2);
        return $this;
    }


    public function getBindConfig()
    {
        if (null === $this->_bindConfig) {
            $this->_bindConfig = new Zend_Config_Ini(APPLICATION_PATH .
                '/config/installer.config.ini', "bind");
        }
        
        return $this->_bindConfig;
    }
}

