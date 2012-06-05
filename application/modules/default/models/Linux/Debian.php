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
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Linux_Debian extends EMSLinuxComputer
{
    const PKGTOOL  = "/usr/bin/dpkg";
    const PMPLUGIN = "DebComputerPackage";

    protected $_linuxConfig, $_distroConfig, $_lsb_release = null;

    public function __construct($dn=null,$attrs = null,$acls = null)
    {
        if ($attrs == null)
            $attrs = array();

        if ($acls == null)
            $acls = array();

        parent::__construct($dn,$attrs,$acls);
    }

    public function init()
    {
        parent::init();
    }
    
    public static function getPkgManagerLib()
    {
        return self::PMPLUGIN;
    }

    public function getPackagePlugin()
    {
        if ($this->hasPlugin(self::PMPLUGIN)) {
            return $this->getPlugin(self::PMPLUGIN);
        } else {
            return null;
        }
    }
    
    /**
     * Helper function which works with an external LinuxComputer object to check for required
     * packages via shell cmds.
     *
     * @return array $pkgData | array()
     */
    public function H_probeServerAddPackages(EMSLinuxComputer $comp, Zend_Config_Ini $distroConfig)
    {
        $rqPkgs     = explode (',', $distroConfig->requiredServerAddPkgs);
        $pkgFound   = array();
        $pkgMissing = array();

        if (!empty($rqPkgs)) {
            foreach ($rqPkgs as $pkg) {
                if (trim($pkg) != '') {
                    if (!$this->H_probeForPackage($pkg, $comp)) {
                        Zivios_Log::error('Could not find package: ' . $pkg);
                        $pkgMissing[] = $pkg;
                    } else {
                        Zivios_Log::info('Found package: ' . $pkg);
                        $pkgFound[]   = $pkg;
                    }
                }
            }

            $pkgData = array('pkgMissing' => $pkgMissing, 'pkgFound' => $pkgFound);
            return $pkgData;
        } else {
            Zivios_Log::info('Package probe operation did not find a required package listing.');
            return array();
        }
    }
    
    /**
     * Helper function for package probe.
     * 
     * @return boolean
     */
    private function H_probeForPackage($packageName, $comp)
    {
        $cmd = self::PKGTOOL . ' -s ' . $packageName;
        $output = $comp->execRemoteCmd($cmd, true, 30, '', 0);
        
        if ($output[sizeof($output)-1] == "0") {
            // system has knowledge of the package. Ensure
            // that the page is installed.
            $status = preg_match("/not-installed/i", $output[2]);

            if (preg_match("/not-installed/i", $output[2]) == 0) {
                Zivios_Log::debug('Package appears to be installed.');
                return true;
            } else {
                Zivios_Log::debug('Package is not installed.');
            }
        }

        return false;
    }
}

