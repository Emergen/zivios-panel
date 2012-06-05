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
 * @package		Zivios
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class CaService extends EMSService
{
    protected $_module = 'ca';

    public  $cabase, $cacertsdir, $pubcerts, $prvcerts, $usercerts,
    		$capubkey, $certpubkey;

    private $caprvkey, $certprvkey;

	public function __construct($dn=null,$attrs=null,$acl=null)
	{
        if ($attrs == null) {
            $attrs = array();
        }

		parent::__construct($dn,$attrs,$acl);
	}

	public function init()
	{
		parent::init();

        $this->_setMasterComputerConfig();
		$this->cabase     = $this->_compConfig->cabase;
		$this->cacertsdir = $this->_compConfig->cacertsdir;
		$this->pubcerts   = $this->_compConfig->pubcerts;
		$this->prvkeys    = $this->_compConfig->prvkeys;
		$this->usercerts  = $this->_serviceCfgGeneral->usercerts;
		$this->capubkey   = $this->_compConfig->capubkey;
		$this->caprvkey   = $this->_compConfig->caprvkey;
	}

	/**
	 * Get all public certificates that the system has issued
	 * and send back relevant details to caller.
	 */
	public function getPubCerts()
	{
		if (!$dir = dir($this->pubcerts))
			throw new Zivios_Exception("Could not initialize public certs directory".
				" System configuration incorrect.");

		/**
		 * Initialize the array we'll be returning
		 */
		$certList = array();

		/**
		 * Look for .pem, crt and cert files and load them.
		 */
		while (false !== ($entry = $dir->read())) {
			if ($entry != '.' && $entry != '..') {
				$extension = substr($entry, strrpos($entry, "."));

				switch ($extension) {
					case ".pem":
					case ".crt":
					case ".cert":
					/**
					 * Read the certificate.
					 */
					if ($this->readCertificate($this->pubcerts . '/'.$entry)) {
						/**
						 * Pack relevant details in return array
						 */
						$sections = array('subject','validFrom', 'validTo', 'purposes');
						$certList[$this->pubcerts . '/'. $entry] = $this->getCertDetails($sections);
					}

					break;
				}
			}
		}

		return $certList;
	}

	/**
	 * Read a certificate
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function readCertificate($path)
	{
		Zivios_Log::debug("Reading cert : " . $path);
		if (trim(strip_tags($path)) == '') {
			Zivios_Log::Debug("Null path given to readCertificate() call.");
			return false;
		}

		$path = trim(strip_tags($path));

		/**
		 * Validate the path and ensure the certificate
		 * can be read by the system.
		 */
		if (!file_exists($path) && !is_readable($path)) {
			Zivios_Log::Debug("Could not find certificate, or certificate could " .
				"not be read. Path: " . $path);
			return false;
		}

		/**
		 * Read certificate data.
		 */
		if ($cp = fopen($path, "r")) {
			$cert = fread($cp, 8192);
			fclose($cp);
		} else {
			Zivios_Log::Debug("Could not read certificate from file");
			return false;
		}

		/**
		 * Ensure certificate data can be read before we parse it.
		 */
		if (!$cert = openssl_x509_read($cert)) {
			Zivios_Log::Debug("Could not load Certificate Data. openssl_x509_read failed.");
			return false;
		}

		if (!$this->certpubkey = openssl_x509_parse($cert)) {
			Zivios_log::Debug("Could not parse public key. openssl_x509_parse failed.");
			return;
		}

		Zivios_Log::Debug("Certificate (".$cert.") read successfully.");
		return 1;
	}

	/**
	 * Reads certificate details from a file and returns requested
	 * information packed in an array. Function returns false if
	 * the certificate is not found, or, if all specified sections
	 * are abset from certificate data.
	 *
	 * @param string $filename
	 * @return array OR boolean $certdetails || false
	 */
	public function loadCertFromFile($filename, $sections=array())
	{
		if (empty($sections)) {
			$sections = array('name','subject','validFrom', 'validTo', 'purposes',
				'serialNumber','hash');
		}

		if ($this->readCertificate($filename)) {
			$certDetails = $this->getCertDetails($sections);
			if (!empty($certDetails)) {
				Zivios_Log::Debug("Returning certificate details");
				return $certDetails;
			}
		}
		return false;
	}

	/**
	 * Specify a section for a certificate to get all
	 * relevant cert details
	 *
	 * @param array $sections
	 * @return array $certDetails
	 */
	public function getCertDetails($sections)
	{
		$certDetails = array();

		if (!is_array($sections)) {
			throw new Zivios_Exception("getCertDetails requires a passed " .
				"array of section names to retrieve.");
		}

		foreach ($sections as $section) {

			if (isset($this->certpubkey[$section])) {
				/**
				 * Handle decoding of certain attributes.
				 */
				switch ($section) {
					case "validFrom" :
					$certDetails[$section] = gmdate('r', $this->cSslTimestamp($this->certpubkey[$section]));
					break;

					case "validTo" :
					$certDetails[$section] = gmdate('r', $this->cSslTimestamp($this->certpubkey[$section]));
					break;

					case "extensions" :
					$certDetails[$section] = $this->decodex509Extensions();
					break;

					case "purposes" :
					if (!empty($this->certpubkey[$section])) {
						$certDetails[$section] = '';
						foreach ($this->certpubkey[$section] as $purpose) {
							$certDetails[$section] .= $purpose[2] . ", ";
						}
						$certDetails[$section] = rtrim($certDetails[$section], ', ');
					} else
						$certDetails[$section] = 'None found.';
					break;

					default:
					Zivios_Log::Debug("Section is: " . $section);
					$certDetails[$section] = $this->certpubkey[$section];
				}
			}
		}

		/**
		 * Return requested details as an array.
		 */
		return $certDetails;
	}

	public function loadDashboardData()
	{
		$this->readCertificate($this->capubkey);

		/**
		 * Specify sections to retrieve for dashboard
		 */
		$sections = array('name','subject','validFrom', 'validTo', 'purposes',
			'serialNumber', 'hash');

		/**
		 * Retrieve information by section.
		 */
		return $this->getCertDetails($sections);
	}

	/**
	 * Generate a service certificate
	 *
	 * @param array $capabilities
	 */
	public function genCert($capabilities, $props, $copyToHost=false)
	{
		Zivios_Log::DEBUG("**** Executing genCert() ****");
		if (!is_array($capabilities) || !is_array($props)) {
			/**
			 * Additional checks need to be placed here. ex: required keys
			 * (for props) are:
			 *
			 *   $..['pubfilename']
			 *   $..['prvfilename']
			 *   $..['subject']
			 * 	 $..['certtype']
			 */
			throw new Zivios_Exception('Capabilities and Props must be sent as an array');
		}

		/**
		 * Generate commands & options
		 */
		$cmdTypes = array();
		$opts = array();

	    foreach ($capabilities as $capability) {
	    	switch ($capability) {
	    		case "https-server" :
	    			$cmdTypes[] = '--type=https-server';

	    			/**
	    			 * Multiple hostnames are permitted in cert SAN. Support
	    			 * needs to be introduced in our system.
	    			 */
	    			if (!isset($props['hostname']))
	    				throw new Zivios_Exception("Missing hostname for certificate");

	    			$opts[] = '--hostname=' . $props['hostname'];
	    		break;

	    		case "pkinit-client":
					if (!isset($props["pkinit-san-id"]))
						throw new Zivios_Exception("PKINIT-SAN-ID Missing.");

					/**
				 	 * Generate command line options.
				 	 */
					$cmdTypes[] = '--type=pkinit-client';
					$opts[] = '--pk-init-principal="' . $props["pkinit-san-id"].'"';
				break;

				case "https-client" :
					$cmdTypes[] = '--type=https-client';
				break;

				case "email" :
					if (!is_array($props["emailaddrs"]) || empty($props["emailaddrs"]))
						throw new Zivios_Exception("Email protection data missing.");

					$cmdTypes[] = '--type=email';
					foreach ($props["emailaddrs"] as $email) {
						$opts[] = '--email="'.$email.'"';
					}
				break;
			}
		}

		$cmd = $this->_serviceCfgGeneral->hxtool . " issue-certificate ";

		/**
		 * Generate command with accompanying (relevant) options.
		 */
		foreach ($cmdTypes as $type) {
			$cmd .= " " . $type;
		}

		foreach ($opts as $options) {
			$cmd .= " " . $options;
		}

		$pubcert = $props['pubfilename'];
		$prvcert = $props['prvfilename'];

		$cmd .= ' --certificate="FILE:'.$this->_serviceCfgGeneral->tmpcertstore.'/'.$pubcert.'"';
		$cmd .= $this->getHxtoolOpts($props['subject']);

		/**
		 * Execute the command
		 */
		Zivios_Log::debug("hxtool::Generating Certificate");
		Zivios_Log::debug("command is: {$cmd}");
		`$cmd`;

		/**
		 * Check created file, break into .pem & .key and move to correct
		 * location.
		 */
		if (file_exists($this->_serviceCfgGeneral->tmpcertstore.'/'.$pubcert) &&
			is_writable($this->_serviceCfgGeneral->tmpcertstore.'/'.$pubcert)) {
			/**
			 * Split the public & private key.
			 */
			if ($this->splitCert($this->_serviceCfgGeneral->tmpcertstore.'/'.$pubcert,
				$pubcert, $prvcert, $props['certtype'])) {
				/**
				 * Certificate data has been written to file -- return certData to frontend.
				 */
				if ($copyToHost == true) {
					/**
					 * Propagate the CA pubkey as well as the host certificates to the
					 * client system and ensure the certificate validates properly.
					 */
					Zivios_Log::debug('copying certificate to host registered');


				}
				return 1;
			}

		} else {
			throw new Zivios_Exception("Certificate creation failed.");
        }
	}

	private function splitCert($tmpcert, $pubfilename, $prvfilename, $type)
	{
		if (!$fp = fopen($tmpcert, "r"))
			throw new Zivios_Exception("Could not open certificate file for reading.");

		$pubkeydata = '';
		$prvkeydata = '';

		Zivios_Log::Debug("Setting pointer to \$pubkeydata");
		$store = &$pubkeydata;

		while (!feof($fp)) {
			$store .= fgets($fp, 4096);

			if (substr($store, -26) == "-----END CERTIFICATE-----\n") {
				Zivios_Log::Debug("Redirecting Pointer to: \$prvkeydata");
				$store = &$prvkeydata;
			}
		}

		/**
		 * Close file
		 */
		fclose($fp);

		switch ($type) {
			case "user" :
				$pubkeypath = $this->_serviceCfgGeneral->usercerts . '/' . $pubfilename;
				$prvkeypath = $this->_serviceCfgGeneral->userprvcerts . '/' . $prvfilename;
			break;

			case "service" :
				$pubkeypath = $this->_serviceCfgGeneral->pubcerts . '/' . $pubfilename;
				$prvkeypath = $this->_serviceCfgGeneral->prvkeys . '/' . $prvfilename;
			break;
		}

		/**
		 * Reopen file and overwrite content as required.
		 */
		if (!$fp = fopen($pubkeypath, "w"))
			throw new Zivios_Exception("Could not open certificate file for writing:" . $pubkeypath);

		if (fwrite($fp, $pubkeydata) === FALSE)
			throw new Zivios_Exception("Could not write public key data to file");

		fclose($fp);

		/**
		 * Write private key to file.
		 */
		if (!$fp = fopen($prvkeypath, "w"))
			throw new Zivios_Exception("Could not open private key for writing:" . $prvkeypath);

		if (fwrite($fp, $prvkeydata) === FALSE)
			throw new Zivios_Exception("Could not write private key to file");

		fclose($fp);

		/**
		 * Unlink the temp cert.
		 */
		unlink($tmpcert);
		return 1;
	}

	private function getHxtoolOpts($cn)
	{
		return
			' --ca-certificate="FILE:'.$this->capubkey.','.$this->caprvkey.'"' .
			' --generate-key=rsa' .
			' --subject="'.$cn.'"';
	}

	private function decodex509Extensions()
	{
		if (version_compare(PHP_VERSION, '5.2.4') !== 1) {
    		Zivios_Log::Debug("x509 certificate decoding not possible in current PHP version.");
    		Zivios_Log::Debug("Calling asn1derIa5string() to decode x509 extensions.");
    		//return $this->asn1derIa5string($this->certpubkey['extensions']['1.3.6.1.4.1.7782.3.3']);
    		return '';
		}
		return $this->certpubkey['extensions'];
	}

	/**
	 * Functions below were taken (and modified) from the php website
	 */
	private function asn1derIa5string($str)
	{
	    $len = strlen($str) - 2;
	    $pos = 0;

	    if ($len < 0 && $len > 127)
	        return false;

		if (22 != (ord($str[$pos++]) & 0x1f) && ord($str[$pos++]) != $len) {
			Zivios_Log::Debug('Invalid DER encoding of IA5STRING');
	    	return false;
		}

	    return substr($str, 2,  $len);
	}

	/**
	 * Converts SSL timestamp.
	 *
	 * @param string $timestamp
	 * @return string
	 */
	private function cSslTimestamp ($timestamp)
	{
        $year  = substr($timestamp, 0, 4);
        $month = substr($timestamp, 4, 2);
        $day   = substr($timestamp, 6, 2);
        $hour  = substr($timestamp, 8, 2);
        $min   = substr($timestamp, 10, 2);
        $sec   = substr($timestamp, 12, 2);
        return gmmktime($hour, $min, $sec, $month, $day, $year);
	}
}
