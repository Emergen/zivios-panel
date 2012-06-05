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
 * @package     ZiviosInstaller
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class Os_Linux_Centos extends Os_Linux_Redhat
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function _probePackages()
    {
        $packageManager = $this->_getPackageManager();

        // Get required packages for centos.
        $centosPkgs = new Zend_Config_Ini(APPLICATION_PATH . 
            '/config/installer.config.ini', 'centos_packages');
        
        // Get all redhat based packages.
        $requiredPackages = explode(",", $centosPkgs->requiredPackages);

        if (trim($centosPkgs->additionalPackages) != '') {
            // Get additionally required packages for centos & merge
            // with required pkgs array.
            $additionalPackages = explode(",", $centosPkgs->additionalPackages);
            $requiredPackages = array_merge($requiredPackages, $additionalPackages);
        }
        
        // Ensure all required pkgs are in place.
        foreach ($requiredPackages as $package) {
            if ($package != '') {
                if (!$packageManager->hasPackage($package)) {
                    throw new Zivios_Error("Required package: " . $package . " not found.");
                }
            }
        }
    }
}

