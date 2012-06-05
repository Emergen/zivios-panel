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
 * @package     Zivios
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class SquidService extends EMSService
{
    protected $_module = 'squid';

    public function init()
    {
        parent::init();
    }

    public function __construct($dn=null,$attrs=null,$acl=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emssquidport';
        $attrs[] = 'emssquidhostname';
        $attrs[] = 'emssquidcachemem';
        $attrs[] = 'emssquidvisiblehost';
        $attrs[] = 'emssquidaclsactive';
        $attrs[] = 'emssquidsafeports';
        $attrs[] = 'emssquiddebuglevel';
        $attrs[] = 'emssquidcachepool';
        $attrs[] = 'emssquidtrustednetworks';

        parent::__construct($dn,$attrs,$acl);
    }
    
    /**
     * Add squid service to Zivios.
     *
     * @return Zivios_Transaction_Group
     */
    public function add(Zivios_Ldap_Engine $parent, Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emssquidservice');

        $this->setProperty('emssquidhostname',   $this->mastercomp->getProperty("cn"));
        $this->setProperty('emssquidvisiblehost',$this->mastercomp->getProperty("cn"));
        $this->setProperty('emsdescription',     'Zivios Squid Service');
        $this->setProperty('emssquidaclsactive', 'TRUE');

        // rfc1918 -- Address allocation for private internet
        // see: http://www.faqs.org/rfcs/rfc1918.html
        $trustedNetworks = array('10.0.0.0/8','192.168.0.0/16','172.16.0.0/12');
        $this->setProperty('emssquidtrustednetworks', $trustedNetworks);

        parent::add($parent,$tgroup);
        
        $this->_aclHelperUpdate($tgroup, 'Sending Squid ACL Helper to remote server');
        $this->_configUpdate($tgroup,'Updating squid configuration.');
        $this->_trustedNetworksUpdate($tgroup, 'Updating Trusted Networks.');
    }

    /**
     * Sends the acl helper script to the remote Squid server
     *
     * @return void
     */
    public function aclHelperUpdate()
    {
        $this->getMasterComputer();

        $squidHelper = $this->_serviceCfgGeneral->squid_acl_helper;
        $destination = $this->_serviceCfgGeneral->rmt_helper_path . '/' . $squidHelper;
        
        // Local source for squid helper.
        $source = APPLICATION_PATH. '/modules/' . $this->_module . 
            '/scripts/' . $squidHelper;
        
        // Send file to squid master computer.
        $this->mastercomp->putFile($source, $destination, 0755, 0, 0);
    }
    
    /**
     * Updates the trusted networks file on squid server.
     *
     * @return void
     */
    public function trustedNetworksUpdate()
    {
        $sysconfig  = $this->getTargetComputerConfig();
        $tnetworks = $this->getProperty('emssquidtrustednetworks');
        if (!is_array($tnetworks)) {
            $tnetworks = array($tnetworks);
        }

        $tnetworks = implode("\n", $tnetworks) . "\n";
        $this->mastercomp->putFileFromString($tnetworks, $sysconfig->trusted_networks);
    }

    /**
     * Generate configuration file and copy across to squid service
     * alongside Zivios squid ACL helper script.
     *
     * @return void
     */
    public function configUpdate()
    {
        $ldapConfig = Zend_Registry::get('ldapConfig');
        $appConfig  = Zend_Registry::get('appConfig');
        $masterComp = $this->getMasterComputer();
        $hostname   = $masterComp->getProperty('cn');
        $sysconfig  = $this->getTargetComputerConfig();
        
        // Initial default (disk based) cache pool config.
        $cacheSize   = $this->getProperty('emssquidcachepool');
        $cacheDir    = $sysconfig->cache_default_spool;
        $cachel1dirs = $sysconfig->cache_dir_l1;
        $cachel2dirs = $sysconfig->cache_dir_l2;

        // Vars for template.
        $srvvals = array();
        $srvvals['ldap_host']           = $ldapConfig->host;
        $srvvals['base_dn']             = $ldapConfig->basedn;
        $srvvals['port']                = $this->getProperty('emssquidport');
        $srvvals['hostname']            = $hostname;
        $srvvals['visible_host']        = $hostname;
        $srvvals['cache_mem']           = $this->getProperty('emssquidcachemem');
        $srvvals['krb_realm']           = $appConfig->kerberosmaster->realm;
        $srvvals['debug_level']         = $this->getProperty('emssquiddebuglevel');
        $srvvals['cache_dir']           = $cacheDir;
        $srvvals['cache_size']          = $cacheSize;
        $srvvals['cache_dirs_l1']       = $cachel1dirs;
        $srvvals['cache_dirs_l2']       = $cachel2dirs;
        $srvvals['cache_log']           = $sysconfig->cache_log;
        $srvvals['cache_access_log']    = $sysconfig->cache_access_log;
        $srvvals['cache_store_log']     = $sysconfig->cache_store_log;
        $srvvals['hosts_file']          = $sysconfig->hosts_file;

        // On service addition, acls are enabled by default.
        $srvvals['auth_param']          = 'auth_param';
        $srvvals['external_acl_type']   = 'external_acl_type';
        $srvvals['safe_ports']          = '';
        $srvvals['acl_allow']           = 'local webauth zcheck';
        $srvvals['acl']                 = 'acl';
        $srvvals['trusted_net_config']  = $sysconfig->trusted_networks;
        $srvvals['auth_ldap_program']   = $sysconfig->auth_ldap_program;

        // Picking up defaults for now (can still be edited directly via template).
        $srvvals['max_obj_size']        = $this->_serviceCfgGeneral->max_obj_size;
        $srvvals['max_obj_size_in_mem'] = $this->_serviceCfgGeneral->max_obj_size_in_mem;
        $srvvals['fqdncache_size']      = $this->_serviceCfgGeneral->fqdncache_size;

        $template = APPLICATION_PATH. '/modules/' . $this->_module . '/config/squid.conf.tmpl';
        $file = Zivios_Util::renderTmplToCfg($template, $srvvals);
        $this->mastercomp->putFileFromString($file, $sysconfig->squid_conf);
    }

    public function stopService()
    {
        $this->_initCommAgent();
        if (!$this->_commAgent->serviceStatus()) {
            Zivios_Log::warn("Stop command sent to Squid Service -- Service not Running!");
            return false;
        } else {
            if ($this->_commAgent->stopService()) {
                Zivios_Log::info("Squid service halted.");
                return true;
            } else {
                Zivios_Log::error("Could not stop Squid Service.");
                return false;
            }
        }
    }

    public function startService()
    {
        $this->_initCommAgent();
        if ($this->_commAgent->serviceStatus()) {
            Zivios_Log::warn("Trying to Start Squid Service... already running.");
            return false;
        } else {
            if ($this->_commAgent->startService()) {
                Zivios_Log::info("Squid service started.");
                return true;
            } else {
                Zivios_Log::error("Could not start Squid Service.");
                return false;
            }
        }
    }

    public function getTrustedNetworks()
    {
        $this->_initCommAgent();
        $tn = $this->_commAgent->getnetcfg();
        $network = array();
        foreach ($tn as $ne) {
            $network[] = trim($ne);
        }
        return $network;
    }

    public function setTrustedNetworks($data)
    {
        $this->_initCommAgent();
        return $this->_commAgent->setnetcfg($data);
    }

    public function getServiceStatus()
    {
        if ($this->pingZiviosAgent()) {
            $status = $this->_commAgent->serviceStatus();
            Zivios_Log::debug('Service Status: ' . $status);
            return $status;
        } else {
            Zivios_Log::error('Zivios Agent appears to be off-line.');
            return false;
        }
    }

    /**
     * Returns service add form.
     * 
     * @param string (dn) $serviceContainer
     * @return Zend_Dojo_Form $form
     */
    public function getAddServiceForm($dn)
    {
        // Initialize add service form. 
        $form = new Zend_Dojo_Form();
        $form->setName('addserviceform')
             ->setElementsBelongTo('addserviceform')
             ->setMethod('post')
             ->setAction('#');

        // Add subforms.
        $configForm = $this->getServiceConfigForm($dn);
        $form->addSubForm($configForm, "serviceconfigform");

        $form->addElement('submitButton', 'submit', array(
           'required'    => false,
           'ignore'      => true,
           'label'        => 'Add Squid Service',
           'onclick'     => "zivios.formXhrPost('addserviceform','squid/service/doaddservice'); return false;",
        ));

        // Add hidden field for service container dn.
        
        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        $form->addElement($hfdn);
        return $form;
    }

    public function setAddServiceForm($formdata)
    {
        if (!isset($formdata['dn'])) {
            throw new Zivios_Exception('Missing DN in request.');
        } else {
            $dn = strip_tags(urldecode($formdata['dn']));
        }

        if (!isset($formdata['serviceconfigform']) || !is_array($formdata['serviceconfigform']) ||
            empty($formdata['serviceconfigform'])) {
            throw new Zivios_Exception('Invalid call to setAddServiceForm. Missing data array.');
        } else {
            $configdata = $formdata['serviceconfigform'];
        }
        
        // Get squid service config form and call set
        $this->setServiceConfigForm($configdata, $dn);
    }

    public function getServiceConfigForm($dn=null)
    {
        $regexLib = $this->_getRegexLibrary();

        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'serviceconfigform',
            'legend' => 'Service Configuration',
            'dijitParams' => array(
                'title' => 'Service Configuration From',
            ),
        ));
        
        // check if configuration is for a new service and add
        // elements accordingly.
        if ($this->isNew()) {
            $serviceContainer = Zivios_Ldap_Cache::loadDn($dn);
            $compatComputers  = $this->_getCompatibleComputers($serviceContainer);

            if (!is_array($compatComputers) || empty($compatComputers)) {
                throw new Zivios_Exception('No compatible computers found for service.');
            }

            $compArray = array('-1' => '<Select Server>');
            foreach ($compatComputers as $computer) {
                $compArray[$computer->getdn()] = $computer->getProperty('cn');
            }

            $form->addElement('ValidationTextBox', 'cn', array(
                'required'          => true,
                'disabled'          => false,
                'label'             => 'Service Label',
                'regExp'            => $regexLib->exp->alnumwithspaces,
                'invalidMessage'    => 'Invalid characters in service label field.',
                'filters'           => array('StringTrim'),
                'validators'        => array(
                                           array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/')),
                                       ),
                'value'             => 'Zivios Squid',
            ));

            $form->addElement('FilteringSelect', 'emsmastercomputerdn', Array(
                'required'      => true,
                'value'         => '-1',
                'multiOptions'  => $compArray,
                'label'         => 'Select Server',
                'autocomplete'  => false
            ));
            
            // Additionally, set form values based on service status.
            $memcache  = $this->_serviceCfgGeneral->cache_mem;
            $diskcache = $this->_serviceCfgGeneral->cache_disk;
            $port      = $this->_serviceCfgGeneral->port;
            $debuglvl  = $this->_serviceCfgGeneral->debug_level;

        } else {
            // Not a new service -- set form values based on existing properties.
            $memcache  = $this->getProperty('emssquidcachemem');
            $diskcache = $this->getProperty('emssquidcachepool');
            $port      = $this->getProperty('emssquidport');
            $debuglvl  = $this->getProperty('emssquiddebuglevel');
        }

        $form->addElement(
            'NumberSpinner',
            'emssquidcachemem',
            array(
                'description'       => 'Define (in MB) how much memory Squid will consume for cache.',
                'value'             => $memcache,
                'label'             => 'Memory Cache',
                'smallDelta'        => 10,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 10,
                'max'               => 102400,
                'places'            => 0,
                'maxlength'         => 6,
            )
        );
        $form->getElement('emssquidcachemem')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement(
            'NumberSpinner',
            'emssquidcachepool',
            array(
                'description'       => 'Define (in MB) how much disk Squid will consume for cache.',
                'value'             => $diskcache,
                'label'             => 'Disk Cache',
                'smallDelta'        => 10,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 10,
                'max'               => 102400,
                'places'            => 0,
                'maxlength'         => 6,
            )
        );
        $form->getElement('emssquidcachepool')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement(
            'NumberSpinner',
            'emssquidport',
            array(
                'value'             => $port,
                'label'             => 'Port (1-65535)',
                'smallDelta'        => 1,
                'largeDelta'        => 10,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 1,
                'max'               => 65535,
                'places'            => 0,
                'maxlength'         => 5,
            )
        );

        $form->addElement(
            'NumberSpinner',
            'emssquiddebuglevel',
            array(
                'description'       => 'Incrementing the debug level will add verbosity. The recommended debug level is 1.',
                'value'             => $debuglvl,
                'label'             => 'Debug Level',
                'smallDelta'        => 1,
                'largeDelta'        => 1,
                'defaultTimeout'    => 500,
                'timeoutChangeRate' => 100,
                'min'               => 1,
                'max'               => 9,
                'places'            => 0,
                'maxlength'         => 1,
            )
        );
        $form->getElement('emssquiddebuglevel')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        return $form;
    }

    /**
     * Update service entry as per form data received.
     *
     * @return void
     */
    public function setServiceConfigForm($configdata, $dn=null)
    {
        $cfgForm = $this->getServiceConfigForm($dn);
        $this->updateViaForm($cfgForm, $configdata);
    }
    
    /**
     * Get form to add additional trusted networks to the service
     * 
     * @return Zend_Dojo_Form $form
     */
    public function getTrustedNetworksForm()
    {
        $regexLib = $this->_getRegexLibrary();

        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'trustednetworkconfigform',
            'dijitParams' => array(
                'title' => 'Trusted Networks',
            ),
        ));

        $form->addElement('ValidationTextBox', 'emssquidtrustednetworks', array(
            'required'          => true,
            'disabled'          => false,
            'label'             => 'Enter IP/Network: ',
            'regExp'            => $regexLib->exp->ipnetwork,
            'invalidMessage'    => 'Invalid characters in Network field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->ipnetwork.'/')),
                                   ),
            'value'             => '',
        ));

        return $form;
    }
    
    /**
     * Update squid trusted networks.
     * 
     * @return void
     */
    public function setTrustedNetworksForm($tnetdata)
    {

        if (null !== ($trustedNets = $this->getProperty('emssquidtrustednetworks'))) {
            if (!is_array($trustedNets)) {
                $trustedNets = array($trustedNets);
            }
            
            if (!in_array($tnetdata['emssquidtrustednetworks'], $trustedNets)) {
                $callSetProperty = true;
                $trustedNets[] = $tnetdata['emssquidtrustednetworks'];
            } else {
                $ignoreUpdate = true;
            }
        }
        
        if (!isset($ignoreUpdate)) {
            $this->setProperty('emssquidtrustednetworks', $trustedNets);
        }
    }
    
    /**
     * Remove trusted networks from system.
     * 
     */
    public function removeTrustedNetworks($tnetdata)
    {
        if (!is_array($tnetdata) || empty($tnetdata)) {
            throw new Zivios_Exception('tnetdata must be passed as an Array.');
        } else {
            $rmNets = array();
            foreach ($tnetdata as $network => $checked) {
                $rmNets[] = urldecode($network);
            }
        }

        if (null === ($trustedNets = $this->getProperty('emssquidtrustednetworks'))) {
            throw new Zivios_Exception('No trusted networks in system.');
        }

        if (!is_array($trustedNets)) {
            $trustedNets = array($trustedNets);
        }

        $networkDiff = array_diff($trustedNets, $rmNets);
        $trustedNetUpdated = array_values($networkDiff);

        $this->setProperty('emssquidtrustednetworks', $trustedNetUpdated);
    }
}

