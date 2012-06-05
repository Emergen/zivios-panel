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
 * @subpackage  Core
 **/

class Zivios_Ssh {

    private $pub_key_location, $prv_key_location,
            $connection, $hostname, $ip_address, $port,
            $time_start, $time_end, $time_total, $time_cmd_start, $time_cmd_end,
            $time_cmd_total, $username, $password, $auth_type, $auth_connect,
            $_appConfig, $secret_key;

    public  $log, $cmd, $cmd_output, $current_shell = 0, $error_flag = 0;

    /**
     * Default authentication method is via supplied public key.
     * All information needs to be supplied in the options array.
     *
     * Other types of authentication can be added as required.
     * @param $options array
     * @param $ignore_pubkey boolean
     */
    public function __construct($options, $ignore_pubkey = 0)
    {
        $this->time_start = $this->microtime_float();
        Zivios_Log::debug("Time Start: " . $this->time_start .
                        " | SSH constructor initialized");

        $this->_appConfig = Zend_Registry::get('appConfig');

        if (!is_array($options)) {
            self::throwException("Options must be sent as array");
        } else {
            if (empty($options)) {
                self::throwException("Nothing found in options array");
            } else {
               if (!array_key_exists('ip_address', $options) &&
                   !array_key_exists('port', $options)) {
                   self::throwException("Required values hostname (or ip
                        address) and port MUST be set");
               } else {
                   // preference to connect via ip address
                   if (isset($options['ip_address'])) {
                       $this->host = $options['ip_address'];
                   } else {
                       if (!isset($options['hostname'])) {
                           self::throwException("Neither IP address nor Hostname defined.");
                       }
                       // set connect to hostname
                       $this->host = $options['hostname'];
                   }
                   $this->port = $options['port'];
               }
            }
        }

        // Check possible connect calls
        if (!$ignore_pubkey) {
            $this->auth_type = 'PUBKEY';
            $this->pub_key_location = SSH_PUBKEY;
            $this->prv_key_location = SSH_PRIVKEY;
        } else {
            // User / Password must be supplied
            if (isset($options['username']) && isset($options['password'])) {
                $this->auth_type = 'LOGIN';
                $this->username = $options['username'];
                $this->password = $options['password'];
            } else {
                self::throwException("Username & Password are required");
            }
        }
    }

    public function connect()
    {
        switch($this->auth_type) {
            case "PUBKEY" :
            Zivios_Log::Debug("Attempting Public Key Login");
            $this->connection = ssh2_connect($this->host, $this->port,
                array('hostkey'=>'ssh-rsa'));

            if (!ssh2_auth_pubkey_file($this->connection, 'root',
                $this->pub_key_location, $this->prv_key_location,
                $this->secret_key)) {
                self::throwException('SSH authentication failed. Ensure your
                    setup works | Type: Public Key.');
            } else {
                $this->time_end = $this->microtime_float();
                Zivios_Log::debug("Time since start: " .
                    ($this->time_end - $this->time_start) .
                    " | Authentication successful. Type: Public Key.");
                $this->auth_connect = 1;
            }
            break;

            case "LOGIN" :
            Zivios_Log::Debug("Attempting User/Pass Login");
            $this->connection = ssh2_connect($this->host, $this->port);

            if (ssh2_auth_password($this->connection, $this->username,
                $this->password)) {
                Zivios_log::debug("Time since start: " .
                    ($this->time_end - $this->time_start) .
                 " | Authentication successful. Login/Pass for user " .
                 $this->username);
                 $this->auth_connect = 1;
            } else {
                throw new Zivios_Exception("Authentication Failure for User " .
                    $this->username . " Type: Login/Pass");
            }
            break;
        }
    }

    public function getShell()
    {
        //if(!($shell = ssh2_shell($this->connection, 'vt102', null, 80, 40,
        if(!($shell = ssh2_shell($this->connection, "xterm"))) {
            self::throwException("Could not open Shell");
        } else {
            stream_set_blocking($shell,false);
            return $shell;
        }
    }

    public function openShell()
    {
        if ($this->current_shell == null) {
            $this->current_shell = $this->getShell();

            //Clean out output buffer which is read via stdout upon command exec.
            Zivios_Log::debug("Opening Shell and cleaning output buffer");
            $out = $this->execShellCmd('pwd');
            Zivios_Log::debug("Shell cleanup output: " . $out);
        }
    }

    /**
     * Execute a shell command
     *
     * @param string $cmd
     * @param boolean $trim_last
     * @param int $timeout
     * @param string $expect
     * @param boolean $bypassCmdLog
     * @param boolean $returnExitCode
     * @return string
     */
    public function shellCmd($cmd, $trim_last=false, $timeout=10, $expect="", 
                             $bypassCmdLog=0, $returnExitCode=0)
    {
        if ($this->current_shell == null) {
            // Valid handler but no shell..
            Zivios_log::debug("No shell found -- trying to open shell..");
            $this->openShell();
        }

        // Unless an expect parameter is passed, we assume we're working
        // with in-built expect of "Command End Execution" (_CEE_)
        if (!$bypassCmdLog)
            Zivios_Log::DEBUG('SSH Command: ' . $cmd);

        if (!is_array($expect)) {
            if ($expect == "") {
                $check_for = "_CEE_";
                $expect = "echo \"".$check_for."\"\n";
            } else {
                $check_for = $expect;
                $expect = '';
            }
        } else {
            if (!empty($expect)) {
                $check_for = $expect;
                $expect = '';
            }
        }

        $time_start = time();
        $data = '';
        
        // If the user simply wants the exit code of a command, we
        // ensure that is trapped.
        if ($returnExitCode) {
            $cmd .= ' ; echo $?';
        }

        fwrite($this->current_shell,$cmd."\n".$expect);
        sleep(1);

        while (true) {
            $data .= fread($this->current_shell, 4096);

            if (!is_array($check_for)) {
                if(strpos($data,$check_for) !== false) {
                    $data .= "\n_ECO_";
                    break;
                }
            } else {
                foreach ($check_for as $check) {
                    if (strpos($data,$check) !== false) {
                        $data .= "\n_ECO_";
                        break 2;
                    }
                }
            }

            if((time() - $time_start) > $timeout) {
                // Maximum allowed time for execution has elasped.
                $data .= ' ... Timeout waiting for response.';

                // Log
                if (!$bypassCmdLog) {
                    Zivios_Log::alert("SSH Command Timed out. Command was: " .
                    $cmd . "Expected Output was: " . $check_for);
                    Zivios_Log::debug("Shell CMD Output: $data");
                }

                self::throwException("Remote command timeout while waiting
                    for response. See system log.");
            }
        }

        if (!$bypassCmdLog)
            Zivios_Log::debug("Shell CMD Output: $data");

        $this->cmd_output = $this->sanitizeOutput($cmd, $data, $check_for,
            $trim_last);

        unset($data);
        
        // if the caller just wants the exit code, we send back only the last
        // character.
        if ($returnExitCode) {
            return substr($this->cmd_output, -1, 1);
        } else {
            return $this->cmd_output;
        }
    }
    
    /**
     * Execute a command via SSH on a remote system. This method deprecates
     * the shellCmd() call. The exitcode of any executed command is always
     * returned alongside command output in an array.
     */
    public function execShellCmd($cmd, $trim_last=false, $timeout=10, $expect="", $bypassCmdLog=0)
    {
        if ($this->current_shell == null) {
            // Valid handler but no shell..
            Zivios_log::debug("No shell found -- trying to open shell..");
            $this->openShell();
        }

        // Unless an expect parameter is passed, we assume we're working
        // with in-built expect of "Command End Execution" (_CEE_)
        if (!$bypassCmdLog) {
            Zivios_Log::DEBUG('SSH Command: ' . $cmd);
        }

        if (!is_array($expect)) {
            if ($expect == "") {
                $check_for = "_CEE_";
                $expect = "echo \"" . $check_for . "\"\n";
            } else {
                $check_for = $expect;
                $expect = '';
            }
        } else {
            if (!empty($expect)) {
                $check_for = $expect;
                $expect = '';
            }
        }

        $time_start = time();
        $data = '';
        $cmd .= ' ; echo $?';

        fwrite($this->current_shell,$cmd."\n" . $expect);
        sleep(1);

        while (true) {
            $data .= fread($this->current_shell, 4096);

            if (!is_array($check_for)) {
                if(strpos($data,$check_for) !== false) {
                    $data .= "\n_ECO_";
                    break;
                }
            } else {
                foreach ($check_for as $check) {
                    if (strpos($data,$check) !== false) {
                        $data .= "\n_ECO_";
                        break 2;
                    }
                }
            }

            if((time() - $time_start) > $timeout) {
                // Maximum allowed time for execution has elasped.
                $data .= ' ... Timeout waiting for response.';

                // Log
                if (!$bypassCmdLog) {
                    Zivios_Log::alert("SSH Command Timed out. Command was: " .
                    $cmd . "Expected Output was: " . $check_for);
                    Zivios_Log::debug("Shell CMD Output: $data");
                }

                self::throwException("Remote command timeout while waiting
                    for response. See system log.");
            }
        }

        if (!$bypassCmdLog) {
            Zivios_Log::debug("Shell CMD Output: $data");
        }

        $this->cmd_output = $this->sanitizeData($cmd, $data, $check_for, $trim_last);

        return $this->cmd_output;
    }

    private function sanitizeData($cmd, $output, $check_for, $trim_last)
    {
        $s_output = array();
        
        // If the "expect" param was specified, the output is returned 
        // as a string to the caller.
        if ($check_for != "_CEE_") {
            return $output;
        }

        $out_array = explode("\n", $output);

        $c=1;
        foreach ($out_array as $val) {
            Zivios_Log::debug($val);
            if (!ereg($cmd, $val) && !ereg('_CEE_', $val)
                && !ereg('_ECO_', $val)) {
                if ($trim_last) {
                    if ($c < sizeof($out_array) - 1) {
                        $s_output[] = trim($val);
                    }
                } else {
                    $s_output[] = trim($val);
                }
            }
            $c++;
        }

        return $s_output;
    }

    /**
     * isolates the actual command output from system resp.
     */
    private function sanitizeOutput($cmd, $output, $check_for, $trim_last)
    {
        if ($check_for != "_CEE_") {
            // We don't really need to clean anything -- let the caller sort it out.
            return $output;
        }

        $out_array = explode("\n", $output);
        Zivios_Log::debug('sanitizing...');
        $s_output = '';
        $c=1;
        foreach ($out_array as $val) {
            Zivios_Log::debug($val);
            if (!ereg($cmd, $val) && !ereg('_CEE_', $val)
                && !ereg('_ECO_', $val)) {
                if ($trim_last) {
                    if ($c < sizeof($out_array) - 1) {
                        $s_output .= $val;
                    }
                } else {
                    $s_output .= $val;
                }
            }
            $c++;
        }
        return trim($s_output);
    }

    public function closeShell()
    {
        fclose($this->current_shell);
        $this->current_shell = null;
    }

    public function remoteScp($srcFile, $dstFile, $direction="send")
    {
        if (!$this->auth_connect) {
            throw new Zivios_Exception("No SSH Connection exists");
        }

        $scpfunc = ($direction=="recv") ? "ssh2_scp_recv":"ssh2_scp_send";

        $this->closeShell();
        $this->connect();
        if (!$scpfunc($this->connection, $srcFile, $dstFile,0644)) {
            throw new Zivios_Exception("SCP Command Failed.");
        } else {
            /**
             * This may need a bit of work. SSH Debug reports file is busy
             * if we try and run it too quickly. One decent way of ensuring
             * everything is kosher is checking md5sum of local and remote
             * file after transfer with a max sleep cycle and a retry period.
             */
            sleep(1);
        }
    }

    private function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    static public function throwException($msg)
    {
        throw new Zivios_Exception($msg);
    }
}

