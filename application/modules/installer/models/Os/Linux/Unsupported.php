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

class Os_Linux_Unsupported extends Os_Linux
{
    protected $_krb5Handler, $_ldapHandler, $_ziviosHandler;

    public function __construct()
    {
        parent::__construct();
        $this->sysDistro = strtolower($this->_session->osDetails['distro']);
        return $this;
    }

    public function runSystemTests()
    {
        /**
         * Probe for required packages and ensure package level
         * configuration is kosher (as deemed by Zivios requirements).
         */
        $this->_probePackages();
    }

    public function iniCaSetup($formData)
    {
        Zivios_Log::debug("Skipping Ca Setup for Unsupported Distro");
    }

    public function iniWebssl()
    {
        Zivios_Log::debug("Skipping Webssl Setup for Unsupported Distro");
    }

    /**
     * Initialize LDAP setup and start the service.
     *
     * @return void
     */
    public function iniLdapSetup($data)
    {
        $ubuntuConfig  = $this->_getDistroDetails();

        /**
         * Note: the zadmin password is received as part of this
         * data array. We need to store this in our session as kerberos
         * initialization will requite it.
         */
        $this->_session->zadminPass  = $data['zadminpass'];
        $this->_session->companyName = $data['scompany'];

        $ldapi = $this->getLdapHandler()->importLdifs($data);
    }

    /**
     * Initialize Kerberos setup and start the service
     *
     * @return void
     */
	 
    public function iniKrb5Setup($data)
    {
        Zivios_Log::debug("Skipping Kerberos Setup for Unsupported Distro");
    }

    /**
     * Initialize Bind setup and start the service
     *
     * @return void
     */
	 
    public function iniBindSetup($data)
    {
        Zivios_Log::debug("Skipping Bind Setup for Unsupported Distro");
    }

    public function getLdapConfig()
    {

    }

    /**
     * Initialize Zivios agent and web panel setup.
     *
     * @return void
     */
    public function iniZiviosSetup($data)
    {
        Zivios_Log::debug("No Final Setup required for Unsupported Distro"); 
    }

    public function getLdapHandler()
    {
        if (null === $this->_ldapHandler) {
            require_once dirname(__FILE__) . '/Services/Openldap.php';
            $this->_ldapHandler = new Os_Linux_Services_Openldap();
        }

        return $this->_ldapHandler;
    }

    public function getKrb5Handler()
    {
        if (null === $this->_krb5Handler) {
            require_once dirname(__FILE__) . '/Services/Heimdal.php';
            $this->_krb5Handler = new Os_Linux_Services_Heimdal();
        }

        return $this->_krb5Handler;
    }

    public function getZiviosHandler()
    {
        if (null === $this->_ziviosHandler) {
            require_once dirname(__FILE__) . '/Services/Zivios.php';
            $this->_ziviosHandler = new Os_Linux_Services_Zivios();
        }

        return $this->_ziviosHandler;
    }

    public function discoverLdapCmds()
    {
        $cmd = "whereis -b ldapadd";
        $rc  = $this->_runLinuxCmd($cmd, true);

        if ($rc['exitcode'] != 0) {
            throw new Zivios_Exception("Could not find ldap_add utility.");
        }
        $cmd_bin = explode(':', $rc['output'][0]);
        $cmd_bin = trim($cmd_bin[1]);
        $cmd = array();
        $cmd['ldapadd'] = $cmd_bin;
        return $cmd;
    }
}
