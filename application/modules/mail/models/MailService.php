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
 * @package		mod_mail
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: MailService.php 1027 2008-09-08 10:44:46Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-09-08 15:44:46 +0500 (Mon, 08 Sep 2008) $
 **/
class MailService extends EMSService
{
	private $cyrusconf,$postfixmain,$transportdb;
	private $dirty,$amavisconf,$cyradminconn, $_cyrusConfig;
	protected $_module = 'mail';
    public $mastercomp;

    const TYPE_SINGLE = "single";
    
	public function __construct($dn=null,$attrs=null)
	{

        if ($attrs == null)
            $attrs = array();

		/**
		 * Plugin parameters and their validators are defined here.
		 */

		$attrs[]='emsmailtype';
        $attrs[]='mail';
        $attrs[]='emsmailrbls';
        $attrs[]='emsmailrelayhost';
        $attrs[]='emsmailrelayhostsmx';
        $attrs[]='emsmailtransports';
        $attrs[]='amaviswhitelistsender';
        $attrs[]='amavisblacklistsender';
        $attrs[]='amavisspamkilllevel';
        $attrs[]='amavismessagesizelimit';
        $attrs[]='amavisspamtaglevel';
        $attrs[]='amavisspamtag2level';
        $attrs[]='emsmaildomains';
        $attrs[]='emsmailmessagesizelimit';
        $attrs[]='emspostfixsecurityclass';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
    	parent::init();
        $param = $this->getParameter('emsmaildomains');

		//$param->addValidator(new Zend_Validate_Hostname(Zend_Validate_Hostname::ALLOW_DNS |
			//Zend_Validate_Hostname::ALLOW_IP));

        $mcdn = $this->getProperty('emsmastercomputerdn');
        if ($mcdn != null) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
        }

        $this->dirty=0;
	}

	/**
	 * Function is called to add the Mail service to a computer.
	 *
	 * @param Zivios_Transaction_Handler $handler
	 * @return object Zivios_Transaction_Handler
	 */
	public function add(EMSObject $parent,Zivios_Transaction_Group $tgroup,$description=null)
	{
        $this->setProperty('mail','@');

        $this->addObjectClass('emsMailService');
        $this->addObjectClass('qmailuser');
        $this->addObjectClass('amavisaccount');
        $this->addObjectClass('namedObject');

        parent::add($parent,$tgroup,$description);
        return $this->updateCfg($tgroup);
	}
    
	public function update(Zivios_Transaction_Group $tgroup,$description=null)
	{
		$tgroup = parent::update($tgroup,$description);
		return $this->updateCfg($tgroup);
	}

	public function updateCfg(Zivios_Transaction_Group $tgroup)
	{

		$titem = $tgroup->newTransactionItem("Updating Mail Configuration and transferring to Host");
		$titem->addObject('mailservice',$this);
		$titem->addCommitLine('$this->mailservice->_updateCfg();');
		$titem->commit();
		return $tgroup;
	}

	public function _updateCfg()
	{
		$appConfig = Zend_Registry::get('appConfig');
		$mastercomputer = $this->getMasterComputer();
		$this->_setMasterComputerConfig();

		/**
		 * Get CA Plugin, we will use this to extract TLS paths later
		 */

		$caplugin = $mastercomputer->getPlugin('CaComputer');
		/**
		 * Initialize module templates
		 */
        $cyrusconftmplfile = $appConfig->modules .'/'. $this->_module . '/config/cyrus.conf.tmpl';
        $imapdconftmplfile = $appConfig->modules .'/'. $this->_module . '/config/imapd.conf.tmpl';
        $maincftmplfile = $appConfig->modules .'/'. $this->_module . '/config/main.cf.tmpl';
        $mastercftmplfile = $appConfig->modules .'/'. $this->_module . '/config/master.cf.tmpl';
        $saslsmtpdtmplfile = $appConfig->modules .'/'. $this->_module . '/config/saslsmtpd.conf.tmpl';

        /**
         * Generate Cyrus.conf and imapd.conf from templates,
         * These are fairly static files. in imapd.conf, we currently set the admin user to 'zadmin'
         * This would be a randomly generated user and password in the future.
         */
		$cyrusconf = Zivios_Util::renderTmplToCfg($cyrusconftmplfile,array());

		$imapdvalarray['cyrus_admin_user'] = 'zadmin';
		$imapdvalarray['tls_key_file'] = $caplugin->getPrvKeyPath();
		$imapdvalarray['tls_cert_file'] = $caplugin->getPubKeyPath();
		$imapdvalarray['tls_ca_file'] = $caplugin->getCaCertPath();
		
		$cyrconfig = $this->getTargetComputerConfig();
		
		$imapdvalarray['cyrusconfigdirectory'] = $cyrconfig->cyrusconfigdirectory;
		$imapdvalarray['cyrusdefaultpartition'] = $cyrconfig->cyrusdefaultpartition;

		$imapdconf = Zivios_Util::renderTmplToCfg($imapdconftmplfile,$imapdvalarray);

		/**
		 * Prepare arrays for main.cf substitution
		 */
		$maincfvalarray = array();
		$maincfvalarray['myhostname'] = $mastercomputer->getProperty('cn');
		$mydestination = "localhost," . $mastercomputer->getProperty('cn');
		foreach ($this->getProperty('emsmaildomains',1) as $maildomain)
			$mydestination .= "," . $maildomain;

		$maincfvalarray['mydestination'] = $mydestination;
		$maincfvalarray['mynetworks'] = "127.0.0.1";

		/**
		 * This is tricky. we will have to think of a smarter way for the local route
		 * parameter if we are to handle clustering in future versions. Currently its set
		 * to the hostname of the master computer, which works for basic setups
		 */

		$maincfvalarray['localroute'] = $mastercomputer->getProperty('cn');
        
        
        if ($this->getProperty('emsmailrelayhostsmx') == 1  && $this->getProperty('emsmailrelayhost') != '') 
            $relayhost = '['.$this->getProperty('emsmailrelayhost').']';
        else
            $relayhost = $this->getProperty('emsmailrelayhost');
    
		$maincfvalarray['relayhost'] = $relayhost;

		/**
		 * Prepare RBL Client List. This is with a trailing comma for ease
		 */

		$rbllist = "";
		foreach ($this->getProperty('emsmailrbls',1) as $rbls) {
			$rbllist .= "reject_rbl_client " . $rbls . ",\n\t";
		}
		$maincfvalarray['reject_rbl_client_list'] = $rbllist;

		$ldapConfig = Zend_Registry::get('ldapConfig');
		$maincfvalarray['ldap_server'] = $ldapConfig->host;
		$maincfvalarray['ldap_base'] = $ldapConfig->basedn;

		/**
		 * Get paths for CA certficates
		 */

		$maincfvalarray['tls_key_file'] = $caplugin->getPrvKeyPath();
		$maincfvalarray['tls_cert_file'] = $caplugin->getPubKeyPath();
		$maincfvalarray['tls_ca_file'] = $caplugin->getCaCertPath();


		/**
		 * get Path for Krb5 keytab to generate sasl/smtpd.conf
		 */

		$krbplugin = $mastercomputer->getPlugin('KerberosComputer');
		$keytabpath = $krbplugin->getKeytabPath();


		/**
		 * All Done! Generate the config now.
		 */
		$postfixmaincffile = Zivios_Util::renderTmplToCfg($maincftmplfile,$maincfvalarray);
		$postfixmastercffile = Zivios_Util::renderTmplToCfg($mastercftmplfile,array());
		$saslsmtpdconffile = Zivios_Util::renderTmplToCfg($saslsmtpdtmplfile,array("keytab_location" => $keytabpath));
		$mastercomputer->putFileFromString($cyrusconf,$this->_compConfig->cyrusconf,0640,'cyrus');
		$mastercomputer->putFileFromString($imapdconf,$this->_compConfig->imapdconf,0640,'cyrus');
		$mastercomputer->putFileFromString($postfixmaincffile,$this->_compConfig->postfixmaincf,0640,'postfix');
		$mastercomputer->putFileFromString($postfixmastercffile,$this->_compConfig->postfixmastercf,0640,'postfix');
		$mastercomputer->putFileFromString($saslsmtpdconffile,$this->_compConfig->postfixsaslsmtpdconf,0640,'postfix');

	}
	public function getDeferredQueue(EMSComputer $computer=null)
    {
        // Currently gets the deferred queue over at a particular computer
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $array = $agent->getdeferredqueue();

        Zivios_Log::debug($array);

        $assoc = array();
        $i=0;
        foreach ($array as $item) {
            $queueobj = new StdClass();
            $queueobj->sender = $item[1];
            $queueobj->time = $item[2];
            $queueobj->recipient = $item[3];
            $queueobj->deferreason = $item[4];
            $size= explode(" ",trim($item[5]));
            $size=$size[0];
            $queueobj->size = $size;
            $assoc[$item[0]] = $queueobj;
        }
        return $assoc;
    }

    public function getActiveQueue(EMSComputer $computer = null)
    {
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $array = $agent->getactivequeue();
        $assoc = array();
        $i=0;
        foreach ($array as $item) {
            $queueobj = new StdClass();
            $queueobj->sender = $item[1];
            $queueobj->time = $item[2];
            $queueobj->recipient = $item[3];
            $size= explode(" ",trim($item[4]));
            $size=$size[0];
            $queueobj->size = $size;
            $assoc[$item[0]] = $queueobj;
        }

        return $assoc;
    }

    public function deleteMessageFromQueue($id,EMSComputer $computer=null)
    {
         // Currently gets the deferred queue over at a particular computer
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $resp = $agent->deletemessage($id);
        Zivios_Log::debug("Attempting to delete message Queue Id $id");
        return $resp;
    }

    public function flushMessageQueue(EMSComputer $computer=null)
    {
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $resp = $agent->flushqueue();
        return $resp;
    }


    public function getCyrusAdminConnection($force=0)
    {
        $mastercomp = $this->getMasterComputer();
        $host = $mastercomp->getProperty('cn');

        /**
         * @todo check by resource, not if it's null
         */
        if ($this->cyradminconn == null || $force) {
        	/**
        	 * Bind to Cyrus as the session user!!
        	 */

            // $this->_iniCyrAdminConfig();

            $sessionuser = Zivios_Ldap_Engine::getUserCreds();
            $admuser = $sessionuser['uid'];
            //$admpass = Zivios_Security::decrypt($this->_cyrusConfig->password);
            $admpass = $sessionuser['password'];
            $this->cyradminconn = imap_open("{".$host.":143/tls/novalidate-cert}", $admuser,$admpass);
        }
        return $this->cyradminconn;
    }

    /**
     * Need to wakeup the Cyrus Connection or calls will fail without warning!
     *
     */
    public function wakeup()
    {
    	parent::wakeup();
    	$this->getCyrusAdminConnection(1);
    }

    public function stopService(EMSComputer $computer=null)
    {
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $resp = $agent->stopservice();
        return $resp;
    }

    public function restartService(EMSComputer $computer=null,Zivios_Transaction_Handler $handler=null)
    {
    	$handler = $this->_stopService($handler,"Stopping Mail Services",$computer);
    	return $this->_startService($handler,"Start Mail Services",$computer);
    }
    public function startService(EMSComputer $computer=null)
    {
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $resp = $agent->startservice();
        return $resp;
    }

    public function getServiceStatus()
    {
        $computer = $this->mastercomp->getIp();
        $agent = new Zivios_Comm_Agent($computer,'mail');
        $resp = $agent->status();
        return $resp;
    }

	public function getPostfixSecurityClasses()
	{
		return $this->getProperty('emspostfixsecurityclass',1);
	}

	public function delete(Zivios_Transaction_Group $tgroup)
	{
        // Mega unsubscribe!
		return parent::delete($tgroup);
	}

    public function _delete()
    {
         $this->stopService();
         return parent::_delete();
    }

	// Quota must be in KB
    public function setMailboxQuota($mailbox,$quota)
    {
        $mbox = $this->getCyrusAdminConnection();
       // Zivios_Log::debug("Setting MAilbox $mailbox quota to $quota");

        return imap_set_quota($mbox, $mailbox,$quota);
    }

    public function listMailBoxes($pattern)
    {
        $host = $this->mastercomp->getProperty('cn');
        $mbox=$this->getCyrusAdminConnection();
        $folders = imap_listmailbox($mbox,"{".$host.":143}", $pattern);
        return $folders;
    }



    public function createMailBox($mailbox)
    {
        $mbox = $this->getCyrusAdminConnection();
        $host = $this->mastercomp->getProperty('cn');
        return imap_createmailbox($mbox,"{".$host.":143}$mailbox");
    }

    public function deleteMailBox($mailbox)
    {
        $mbox = $this->getCyrusAdminConnection();
        $host = $this->mastercomp->getProperty('cn');
        return imap_deletemailbox($mbox,"{".$host.":143}$mailbox");
    }

    public function setAcl($mailbox,$user,$acl)
    {
        $mbox = $this->getCyrusAdminConnection();
        return imap_setacl($mbox,$mailbox,$user,$acl);
    }


    public function getStorageQuota($mailbox)
    {
        $mbox = $this->getCyrusAdminConnection();
        $host = $this->mastercomp->getProperty('cn');
        $retarray = imap_get_quota($mbox, "$mailbox");
        return $retarray["STORAGE"];
    }

    public function getChildMailBoxes($mailbox)
    {
        $mbox = $this->getCyrusAdminConnection();
        $host = $this->mastercomp->getProperty('cn');

        $boxes = imap_getmailboxes($mbox, "{".$host.":143}", "$mailbox*");
        $retarray = array();
        foreach ($boxes as $box) {
            $name = explode('}',$box->name);
            $name = $name[1];
            $retarray[] = $name;
        }
        return $retarray;
    }


    private function _iniCyrAdminConfig()
    {
		if (!$this->_cyrusConfig instanceof Zend_Config_Ini) {
			/**
			 * Get application configuration object
			 */
			if (!isset($this->_module) || $this->_module == '')
				throw new Zivios_Exception("Variable _module *MUST* be set by your calling class.");

			$appConfig = Zend_Registry::get('appConfig');

			/**
			 * Instantiate cfg object for plugin
			 */
			$this->_cyrusConfig = new Zend_Config_Ini($appConfig->modules . '/' .
				$this->_module . '/config/cyradmin.ini', 'credentials');
		}
    }
    
    public function setMainForm($data,$parentobj)
    {
        $form = $this->getMainForm($parentobj);
        
        $this->updateViaForm($form,$data);
    }
    
    public function setRoutingGeneralForm($data)
    {
        $relayhost = $data['emsmailrelayhost'];
        $suppressmx = $data['suppressmx'];
        
        
        $this->setProperty('emsmailrelayhost',$relayhost);
        $this->setProperty('emsmailrelayhostsmx',$suppressmx);
    }
    
    public function getRoutingGeneralForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'routinggeneralsettings',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Routing Settings',
            ),
        ));
        $form->addElement('ValidationTextBox','emsmailrelayhost', 
                          array(
                                'required'      => false,
                                'regExp'        => $regexLib->exp->hostname,
                                'title'         => 'Relay Host',
                                'label'         => 'Relay Host: ',
                                'invalidMessage' => 'Invalid Relay Host Specified',
                                'value'         => $this->getProperty('emsmailrelayhost')
                                ));
        
        
        $form->addElement('CheckBox', 'suppressmx',     
                          array(
                                'label'          => 'Supress MX Lookup',
                                'checkedValue'   => '1',
                                'uncheckedValue' => '0',
                                'value'        => $this->getProperty('emsmailrelayhostsmx')
                                ));
        return $form;
    }


    public function getGeneralSettingsForm()
    {
        $regexLib = $this->_getRegexLibrary();
        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'generalsettings',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'General Settings',
            ),
        ));
        $form->addElement('ValidationTextBox','emsmailmessagesizelimit', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->digits,
                                'title'         => 'Max Message Size',
                                'label'         => 'Max Message Size: ',
                                'invalidMessage' => 'Invalid Size Specified',
                                'value'         => $this->getProperty('emsmailmessagesizelimit')
                                ));
        
        $form->addElement('ValidationTextBox','amavisspamtaglevel', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Spam Header at',
                                'label'         => 'Spam Header at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamtaglevel')
                                ));
        
        $form->addElement('ValidationTextBox','amavisspamtag2level', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Spam Tag at',
                                'label'         => 'Spam Tag at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamtag2level')
                                
                                ));

        $form->addElement('ValidationTextBox','amavisspamkilllevel', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->decimal,
                                'title'         => 'Kill At',
                                'label'         => 'Kill at: ',
                                'invalidMessage' => 'Invalid Spam Score Specified',
                                'value'         => $this->getProperty('amavisspamkilllevel')
                                ));
        
        return $form;
    }
    
    public function setGeneralSettingsForm($data)
    {
        $messagelimit = $data['emsmailmessagesizelimit'];
        $spamtag1level = $data['amavisspamtaglevel'];
        $spamtag2level = $data['amavisspamtag2level'];
        $killlevel = $data['amavisspamkilllevel'];
        
        $this->setProperty('emsmailmessagesizelimit',$messagelimit);
        $this->setProperty('amavisspamtaglevel',$spamtag1level);
        $this->setProperty('amavisspamtag2level',$spamtag2level);
        $this->setProperty('amavisspamkilllevel',$killlevel);
        
    }
    public function getMainForm($parentObject)
    {
        $regexLib = $this->_getRegexLibrary();

        $computers = $this->_getCompatibleComputers($parentObject);
        $compArray = array();;
        foreach ($computers as $computer) {
            $compArray[$computer->getdn()] = $computer->getProperty('cn');
        }
        $compArray = array('-1' => '<Select Server>') + $compArray;

        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'addservice',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Service Add From',
            ),
        ));
        $form->addElement('ValidationTextBox','emsmaildomains', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->hostname,
                                'title'         => 'Mail Domain',
                                'label'         => 'Mail Domain: ',
                                'invalidMessage' => 'Invalid Mail Domain Specified'
                                ));

        $form->addElement('ValidationTextBox','cn', 
                          array(
                                'required'      => true,
                                'regExp'        => $regexLib->exp->alnumwithspaces,
                                'title'         => 'Service Name',
                                'label'         => 'Service Name: ',
                                'invalidMessage' => 'Invalid Service Name Specified'
                                ));
        
        $form->addElement('FilteringSelect', 'emsmailtype', Array(
                'required'      => true,
                'multiOptions'  => array(self::TYPE_SINGLE  => 'Single System'),   
                'regExp'        => $regexLib->exp->alnumwithspaces,
                'title'         => 'Select Type',
                'label'         => 'Select Type',
                'invalidMessage'    => 'Invalid Type',
                'filters'           => array('StringTrim'),
                'validators'        => array(
                                           array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/')),
                                       ),
                'autocomplete'  => false
        ));
        
        $form->addElement('FilteringSelect', 'emsmastercomputerdn', Array(
                'required'      => true,
                'multiOptions'  => $compArray,
                'regExp'        => $regexLib->exp->hostname,
                'title'         => 'Select Server',
                'label'         => 'Select Server',
                'invalidMessage'    => 'Invalid characters in hostname field.',
                'filters'           => array('StringTrim'),
                'autocomplete'  => false
        ));

        return $form;
    }
    
	/*public function createMailBoxes($mbox)
	{

        $mbox = $this->getCyrusAdminConnection();

        // Hardcoded Defaults for now!
        $this->createMailBox("user.$uid");
        $this->createMailBox("user.$uid.Trash");
        $this->createMailBox("user.$uid.Sent");
        $this->createMailBox("user.$uid.Drafts");
        $this->createMailBox("user.$uid.Spam");


        $this->setAcl("user.$uid","eadmin","lrswipcda");
        $this->setAcl("user.$uid.Trash","eadmin","lrswipcda");
        $this->setAcl("user.$uid.Sent","eadmin","lrswipcda");
        $this->setAcl("user.$uid.Spam","eadmin","lrswipcda");
        $this->setAcl("user.$uid.Drafts","eadmin","lrswipcda");


	}*/
}
