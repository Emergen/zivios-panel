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

class Os_Linux_Suse extends Os_Linux_Redhat
{
    public function __construct()
    {
        parent::__construct();
    }

    public function iniCaSetup($data)
    {
        // Initialize CA details for distribution
        $distroConfig = $this->_getDistroDetails();
        $caBase       = $distroConfig->pki_base;
        $caCerts      = $distroConfig->anchors;
        $caKeys       = $distroConfig->anchorkey;
        $pubCerts     = $distroConfig->publicCerts;
        $prvKeys      = $distroConfig->privateCerts;

        // System localhost public & private keys. Will be linked to 
        // generated host keys by the Zivios CA
        $lcpubcert = $pubCerts . '/servercert.crt';
        $lcprvcert = $prvKeys  . '/servercert.key';

        // Ensure we backup system PKI dir.
        $cbFolder = $this->linuxConfig->backupFolder . '/CA';

        if (!is_dir($cbFolder)) {
            $cmd = $this->_session->_cmds['mkdir'] . ' ' . $cbFolder; 
            $rc  = $this->_runLinuxCmd($cmd, true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception ("Could not create backup folder for CA data.");
            }
        }

        $sbFolder = $cbFolder . '/ssl';

        if (!is_dir($sbFolder)) {
            $cmd = $this->_session->_cmds['mv'] . ' ' . $caBase . ' ' . $cbFolder;
            $rc = $this->_runLinuxCmd($cmd,true);

            if ($rc['exitcode'] != 0) {
                throw new Zivios_Exception("Could not backup ".$caBase." to Zivios CA backup folder.");
            }
        }

        // Ensure 'caBase dir' does not exist -- we will be creating a symlink to it at
        // a later stage.
        if (is_dir($caBase)) {
            $this->_removeRecursive($caBase);
        }
        
        // Recreate PKI folder structure
        $this->_createFolder($caBase,  '0755', 'root', 'root');
        $this->_createFolder($caBase . '/certs', '0755', 'root', 'root');
        
        // Get the Heimdal Service and initialize CA.
        $webuser       = $distroConfig->webuser;
        $webgroup      = $distroConfig->webgroup;
        
        // Instantiate krb5 handler and initialize CA.
        $krb5i = $this->getKrb5Handler()
                      ->checkCaStatus()
                      ->initializeCa($webgroup)
                      ->generateCaCert($data["califetime"])
                      ->generateWebCert()
                      ->generateKdcCert();
                                                
        /** 
         * For SuSE, SSL Locations are as follows:
         *
         *   /etc/ssl/certs <- has all anchors
         *   /etc/ssl/servercerts <- has service pub certs
         *   /etc/ssl/private <- has CA & service private keys
         */
        $caConfig        = $krb5i->getCaConfig();
        $cacertpubkeyloc = $caConfig->anchors    . '/' . $caConfig->rootPubCert;
        $cacertprvkeyloc = $caConfig->anchorsprv . '/' . $caConfig->rootPrvCert;

        $publicCerts     = $caConfig->publicCerts;
        $privateCerts    = $caConfig->privateCerts;
        
        // Link the public and private server certs
        $this->_softLink($publicCerts,  $pubCerts);
        $this->_softLink($privateCerts, $prvKeys);
        
        // Link CA Certs
        $this->_softLink($cacertpubkeyloc, $caCerts);
        $this->_softLink($cacertprvkeyloc, $caKeys);

        // Rehash CA certs.
        $cmd = $this->_session->_cmds['c_rehash'];
        $rc = $this->_runLinuxCmd($cmd, true);

        if ($rc['exitcode'] != 0) {
            throw new Zivios_Error('Could not rehash anchor certs.');
        }
    }

    protected function _probePackages()
    {
        $packageManager = $this->_getPackageManager();

        // Get required packages for suse
        $susePkgs = new Zend_Config_Ini(APPLICATION_PATH . 
            '/config/installer.config.ini', 'suse_packages');
        
        // get pkg listing
        $requiredPackages = explode(",", $susePkgs->requiredPackages);

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

