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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/
class Db_Mysql
{
    private     $__link, $__db;
    protected   $_dbhost, $_dbuser, $_dbpass, $_zdbname, $_socket;

    /**
     * Initializes database connection
     *
     * @return void
     */
    public function __construct(array $dbinfo)
    {
        $this->_dbhost  = $dbinfo["dbhost"];
        $this->_dbuser  = $dbinfo["dbuser"];
        $this->_dbpass  = $dbinfo["dbpass"];
        $this->_zdbuser = $dbinfo["zdbuser"];
        $this->_zdbpass = $dbinfo["zdbpass"];
        $dbconfirmpass  = $dbinfo["czdbpass"];
        $this->_zdbname = $dbinfo["dbname"];
        $this->_socket  = $dbinfo["socket"];

        // Ensure passwords match
        if ($this->_zdbpass != $dbinfo['czdbpass']) {
            throw new Zivios_Error('Zivios user passwords do not match.');
        }

        $this->__link = @mysql_connect($this->_dbhost . ':' . $this->_socket, 
            $this->_dbuser, $this->_dbpass);

        if (!$this->__link) {
            $error = strip_tags(mysql_error());
            Zivios_Log::debug('Error: ' . $error);
            throw new Zivios_Error('Connection to database service failed. Error reported: <br/>' .
                $error);
        } else {
            Zivios_Log::debug('Connection to MySQL database system successful');
            Zivios_Log::info('MySQL DB connection successful.', 'clogger');
        }
    }

    /**
     * Initialize database.
     *
     * @return void
     */
    public function iniSetup()
    {
        $this->_iniAdmin();
        $this->_importData();
    }

    /**
     * Initialize Zivios user privileges.
     *
     * @return void
     */
    protected function _iniAdmin()
    {
        if (false === ($this->_db = @mysql_select_db('mysql', $this->__link))) {
            throw new Zivios_Exception("Failed to create DB user. MySQL DB select failed.");
        }

        // Update/Create the user entry.
        $query  = sprintf("REPLACE INTO user (`host`, `user`, `password`) VALUES ('%s', '%s', PASSWORD('%s'))",
                mysql_real_escape_string($this->_dbhost,  $this->__link),
                mysql_real_escape_string($this->_zdbuser, $this->__link),
                mysql_real_escape_string($this->_zdbpass, $this->__link));

        if (mysql_query($query, $this->__link)) {
            Zivios_Log::debug('DB user setup completed.');
            Zivios_Log::info('DB user setup completed.', 'clogger');
        } else {
            throw new Zivios_Exception("Could not setup Zivios database user in MySQL.");
        }

        // Grant all privileges on the specified db to the Zivios db user.
        $query  = sprintf("REPLACE INTO db (`host`, `db`, `user`, `select_priv`, `insert_priv`, `update_priv`,
                `delete_priv`, `create_priv`, `drop_priv`, `index_priv`) VALUES
                ('%s', '%s', '%s', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y')",
                mysql_real_escape_string($this->_dbhost,  $this->__link),
                mysql_real_escape_string($this->_zdbname, $this->__link),
                mysql_real_escape_string($this->_zdbuser, $this->__link));

        if (mysql_query($query, $this->__link)) {
            Zivios_Log::debug('Zivios DB user privileges set.');
            Zivios_Log::info('Zivios DB user privileges set.');
        } else {
            throw new Zivios_Exception("Could not set Zivios database user privileges.");
        }

        // Flush privileges
        if (!mysql_query('FLUSH PRIVILEGES', $this->__link)) {
            throw new Zivios_Exception("Refresh of privileges on database system failed.");
        }
    }

    /**
     * Initialize Zivios database.
     *
     * @return void
     */
    protected function _importData()
    {
        if (!mysql_query("CREATE DATABASE ".$this->_zdbname, $this->__link)) {
            // It is possible that the database already exists; try switching
            // to it.
            if (!$this->_db = mysql_select_db($this->_zdbname)) {
                throw new Zivios_Exception("Could not ceate MySQL database for Zivios.");
            }
        } else {
            if (!$this->_db = mysql_select_db($this->_zdbname)) {
                throw new Zivios_Exception("Could not switch to MySQL database.");
            }
        }

        // Load SQL data into Zivios db.
        require_once dirname(__FILE__) . '/sql.php';

        if (!is_array($sqlInstall) || empty($sqlInstall)) {
            throw new Zivios_Exception("Could not locate required SQL for DB initialization.");
        }

        foreach ($sqlInstall as $query) {
            if (!mysql_query($query, $this->__link)) {
                Zivios_Log::err("MySQL Query failed: " . $query);
                Zivios_Log::err("Sql Error: " . mysql_error());
                throw new Zivios_Exception("SQL query failed. Please consult the logs.");
            }
        }
        
        Zivios_Log::info('Zivios data import complete.', 'clogger');
    }
}
