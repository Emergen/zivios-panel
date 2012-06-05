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

class Os_Linux_PackageHelper_Deb extends Os_Linux
{
    public function __construct()
    {
        parent::__construct();

        /**
         * Set paths for required programs: 
         *   dpkg, apt-get, etc.
         */
        if (null === $this->_session->_pkgcmds) {
            $this->_session->_pkgcmds = array('apt-get' => '','dpkg' => '');

            foreach ($this->_session->_pkgcmds as $cmdname => $path) {
                $this->_session->_pkgcmds[$cmdname] = $this->_setCommandPath($cmdname);
                if ($this->_session->_pkgcmds[$cmdname] == '') {
                    Zivios_Log::error("_CONSOLEDATA_Required program: " . $cmdname . " not found.");
                    throw new Zivios_Exception("Required program: " . $cmdname . " not found.");
                } else {
                    Zivios_Log::debug("Required program: " . $cmdname . " found: " .
                        $this->_session->_pkgcmds[$cmdname]);
                }
            }
        }
    }

    /**
     * Checks if a package is installed on the system.
     *
     * @params string $package
     * @return boolean
     */
    public function hasPackage($package)
    {
        Zivios_Log::debug("Checking for package: " . $package);
        $cmd = $this->_session->_pkgcmds['dpkg'] . " -s " . $package;
        $out = $this->_runLinuxCmd($cmd);

        if ($out['exitcode'] != 0) {
            // No package level information is available.
            Zivios_Log::error($package . ' not found on system.', 'clogger');
            throw new Zivios_Error('Package ' . $package . ' not found on system.');
        } else {
            if (strtolower(trim($out['output'][1])) != 'status: install ok installed') {
                Zivios_Log::error($package . ' not found on system.', 'clogger');
                throw new Zivios_Error('Package ' . $package . ' not found on system.');
            }
        }
        
        Zivios_Log::debug("Package: " . $package . ": install ok.");
        return true;
    }

    /**
     * Installs a package on the system
     * 
     * @return boolean
     */
    public function installPackage()
    {}

    /**
     * Removes a package from the system
     *
     * @return
     */
    public function removePackage()
    {}
}
