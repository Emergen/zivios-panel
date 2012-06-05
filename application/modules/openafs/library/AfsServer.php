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
 * @package		mod_openafs
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 * @version		$Id: AfsServer.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/


class AfsServer extends Afs
{
	const RDN = 'o=openafs,';

	private $_cfg = 0, $_mountPointMap = array(), $_dirConnection, $_ldapConfig;
	protected $clientSystem = 0;
	public $log, $appConfig;

	/**
	 * Instantiate the contructor with a computer object. The plugin internally
	 * sets and handles computer attribute updates as required. The computer
	 * object details are updated using only the transaction handler.
	 *
	 * @param EMSComputer $computerobj
	 */
	public function __construct(EMSComputer $computerobj)
	{
		parent::__construct($computerobj);

		/**
		 * Plugin parameters and their validators are defined here.
		 */
		$param = $this->addParameter('emsafscell','AFS Cell Name', 1);
		$param->addValidator(new Zend_Validate_Hostname(),
			Ecl_Validate::errorCode2Array('hostname',$param->dispname));

		/**
		 * Params which do not need validation or handle validation via
		 * their private validation class calls.
		 */
		$param = $this->addParameter('emsafspartitions', 'AFS Partitions', 1);
		$param = $this->addParameter('emsafsrole', 'AFS System Role', 1);
	}

	/**
	 * Function is called to add the AFS service to a computer.
	 *
	 * @param Ecl_Transaction_Handler $handler
	 * @return object Ecl_Transaction_Handler
	 */
	public function add(Ecl_Transaction_Handler $handler=null)
	{
		if ($handler == null)
			$handler = $this->_computerobj->getTransaction();

		$this->_computerobj->addObjectClass('emsAfsService');

		/**
		 * Add system partitions to computer transaction update handler.
		 */
		$partitions = $this->_getPartitions();

		if (!empty($partitions)) {
			$param = $this->_computerobj->getParameter("emsafspartitions");
			$c=0;
			foreach ($partitions as $partition) {
				if ($c == 0)
					$param->setValue($partition);
				else
					$param->addValue($partition);
				$c++;
			}
		}

		$handler = $this->_computerobj->update($handler);

		Ecl_Log::Debug("Computer CN: " . $this->_computerobj->getProperty('cn'));
		/**
		 * Add OpenAFS plugin DN to transaction handler.
		 */
		$pluginDN = new EMSSecurityObject($this->_computerobj->newLdapObject(null));
		$pluginDN->setProperty('cn','openafs');
		$pluginDN->setProperty('emspermission','default');
		$pluginDN->setProperty('emsdescription','EMS OpenAFS Plugin');
		$pluginDN->setProperty('emstype',EMSObject::TYPE_SERVICEPLUGIN);
		$pluginDN->addObjectClass('emsIgnore');


		/**
		 * Pass add operation to trans handler
		 */
		$handler = $pluginDN->add($this->_computerobj, $handler);
		$handler = $this->_addVolsToTrans($pluginDN, $handler);

		/**
		 * The call below needs to be encoded in the transaction object.
		 */
		//$this->resyncMountPointMap();

		/**
		 * Return handler
		 */
		return $handler;
	}

	public function update(Ecl_Transaction_Handler $handler=null)
	{}

	public function delete(Ecl_Transaction_Handler $handler=null)
	{}

	public function deleteMountPoint(EMSAfsVolume $volEntry, $mountPoint)
	{
		Ecl_Log::DEBUG("OpenAFS Server calling deleteMountPoint");
		Ecl_Log::DEBUG("mountpoint to remove: " . $mountPoint);
		$extMountPoints = $this->getVolMountPoints($volEntry);
		/**
		 * Format mountpoints to not include mount point type.
		 */
		foreach ($extMountPoints as $mp) {
			$vm = explode(":", $mp);
			if ($vm[0] == $mountPoint)
				break;
		}

		/**
		 * Ensure mountpoint exists in LDAP returned result set.
		 */
		if (!in_array($mountPoint, $vm))
			return false;

		/**
		 * Remove the mountpoint
		 */
		if (!$this->_deleteMountPoint($vm[0]))
			return false;

		/**
		 * Release base volume.
		 */
		$this->_releaseBaseVol($mountPoint);

		/**
		 * Mountpoint removal successful. Remove from LDAP.
		 */
		$lentry = $vm[0] . ':' . strtolower($vm[1]);
		$param = $volEntry->getParameter("emsafsvolmountpoint");
		$param->removeValue($mp);
		$trans = $volEntry->update();
		$trans->process();

		return true;
	}

	public function deleteVolume(EMSAfsVolume $volEntry)
	{
		$volMountPoints = $this->getVolMountPoints($volEntry);

		if (!empty($volMountPoints)) {
			foreach ($volMountPoints as $mountpoint) {
				Ecl_Log::debug("Removing mountpoint: " . $mountpoint);
				$mp = explode(":", $mountpoint);
				$this->deleteMountPoint($volEntry, $mp[0]);
			}
		} else {
			Ecl_Log::debug("Mountpoint return empty");
		}

		/**
		 * Remove the volume itself.
		 */
		if ($this->_deleteVolume($volEntry)) {
			/**
			 * Remove entry from LDAP.
			 */
			$trans = $volEntry->delete(null);
			$trans->process();
			return true;
		} else
			return false;
	}

	/**
	 * Get Volume Mountpoints as they exist in LDAP. If no mountpoints are
	 * found, the function returns an empty array.
	 *
	 * @param EMSAfsVolume $volEntry
	 * @return array
	 */
	public function getVolMountPoints(EMSAfsVolume $volEntry)
	{
		if (!$mp = $volEntry->getProperty("emsafsvolmountpoint"))
			return array();
		else {
			if (!is_array($mp))
				return array($mp);
			else
				return $mp;
		}
	}

	/**
	 * Resynchronize volume listing between LDAP and the OpenAFS VLDB.
	 *
	 * @return void
	 */
	public function resyncVolumeListing()
	{
		Ecl_Log::DEBUG("calling resyncVolumeListing");

		/**
		 * Create the mountpoint map
		 */
		$this->createMountPointMap();

		/**
		 * @todo FK recommends we run an update here rather than a delete / add
		 */
		$vols = $this->getVolsOnServer();
		$trans = null;
		if (!empty($vols)) {
			foreach ($vols as $volume)
				$trans = $volume->delete($trans);

			/**
		 	 * Process delete transaction
		 	 */
			$trans->process();
		}

		/**
		 * Get AFS DN relevant to computer object
		 */
		$dn = self::RDN . $this->_computerobj->getdn();
		$pluginDN = $this->_computerobj->_lobj->respawn($dn)->getObject();

		/**
		 * Get transaction handler
		 */
		$handler = $this->_computerobj->getTransaction();
		$handler = $this->_addVolsToTrans($pluginDN, $handler);

		/**
		 * Process the transaction
		 */
		$handler->process();
	}

	public function getVolsOnServer()
	{
		/**
		 * Volume Listing returned here are LDAP objects on a per volume
		 * basis rather than a direct SSH query. The option to resync
		 * can be given on the volume display template.
		 */
		Ecl_Log::debug("Calling getVolumeListing");
		$dn = self::RDN . $this->_computerobj->getdn();
		$afs = $this->_computerobj->newLdapObject($dn)->getObject();
		return $afs->getImmediateChildren(0);
	}

	public function getVolumeDetails($voldn)
	{
		Ecl_Log::debug("Getting volume details for: " . $voldn);
		return $this->_computerobj->newLdapObject($voldn)->getObject();
	}

	public function getPartitions($resync = false)
	{
		/**
		 * Unless resync is requested, we simply return the partition listing
		 * from the computer object.
		 */
		return $this->_computerobj->getProperty("emsafspartitions");
	}

	public function addAfsVolume($name, $quota, $partition)
	{
		/**
		 * First, create the volume on the AFS server:: _createVolume()
		 * Get volume details from server:: _getVolDetails()
		 * Create LDAP object with volume details
		 * Run the transaction.
		 *
		 * @return boolean
		 */
		$this->_iniConfig();
		$vol = $this->_createVolume($name, $quota, $partition);

		/**
		 * Push volume entry in LDAP
		 */
		$dn = self::RDN . $this->_computerobj->getdn();
		$pluginDN = $this->_computerobj->_lobj->respawn($dn)->getObject();

		/**
		 * Get transaction handler
		 */
		$handler = $this->_computerobj->getTransaction();
		$handler = $this->_addVolsToTrans($pluginDN, $handler, array($vol));

		/**
		 * Process the transaction
		 */
		$handler->process();

		/**
		 * Return the DN of the freshly created Volume.
		 */
		return 'o=' . $name . ',' . $pluginDN->getdn();
	}

	public function getValidationClass()
	{
		$this->_iniConfig();
		return $this->_cfg->validate;
	}

	public function setClientServer()
	{
		if (!$this->clientSystem instanceof AFSClient)
			$this->_setClientServer();
	}

	/**
	 * Update an AFS volume's quota.
	 *
	 * @param EMSAfsVolume $volEntry
	 * @param string $maxquota
	 * @return boolean
	 */
	public function updateQuota(EMSAfsVolume $volEntry, $maxquota)
	{
		Ecl_Log::DEBUG("calling AFSPlugin updateQuota");

		/**
		 * First and foremost, we ensure that a mountpoint entry exists
		 * in ldap for this volume. Updating the quota or settings ACLs
		 * requires a mountpoint.
		 */
		if ($volEntry->getproperty("emsafsvolmountpoint") == "") {
			Ecl_Log::debug("No volume mount point");
			return false;
		}

		/**
		 * Ensure the passed volume ID exists and the Quota specified is
		 * possible on the server partition in question.
		 */
		$vol = $this->_getVolumeData($volEntry->getProperty("cn"));

		if (empty($vol))
			throw new Ecl_Exception("Volume Not Found on AFS System");

		/**
		 * Get (current) partition usage.
		 */
		$partitionUsage = $this->_getPartitionUsage();

		/**
		 * Check volume quota with specified
		 */
		if (!array_key_exists($vol['partition'], $partitionUsage))
			throw new Ecl_Exception("Cannot locate Partition");

		/**
		 * Check quota against available space.
		 */
		//echo "Space available: " . $partitionUsage[$vol['partition']]['free'] . "<br />";
		//echo "Total available: " . $partitionUsage[$vol['partition']]['available'] . "<br />";

		if ($maxquota > $partitionUsage[$vol['partition']]['free']) {
			Ecl_Log::ERROR("Quota Update failed for volume " . $volEntry->getdn() .
				": Insufficient space on partition");
			return false;
		}

		/**
		 * Call update quota action.
		 */
		if ($this->_updateQuota($volEntry, $maxquota)) {
			/**
			 * Update LDAP's volume entry for max quota.
			 */
			$param = $volEntry->getParameter("emsafsvolmaxquota");
			$param->setValue($maxquota);
			$trans = $volEntry->update();
			$trans->process();

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Creates a mountpoint map for AFS
	 */
	public function createMountPointMap()
	{
		if (empty($this->_mountPointMap))
			$this->_createMountpointMap();
	}

	/**
	 * Resync the mountpoint map and update volume details accordingly.
	 *
	 */
	public function resyncMountPointMap()
	{
		return false;
	}

	public function addMountPoint(EMSAfsVolume $volEntry, $mountpoint,
	$type="rw", $releasebase=0)
	{
		/**
		 * Set the volume entry to the mountpoint specified.
		 */
		Ecl_Log::DEBUG("calling AFSPlugin addMountPoint");

		if ($type != "ro" && $type != "rw")
			throw new Ecl_Exception("Unknown Volume Mount Type Specified");

		$pattern = '/^(?:[a-z0-9_-]|\.(?!\.))+$/iD';
		$mountPoints = explode('/', trim($mountpoint));

		$c = 0;
		$dirAppendToBase = '';
		foreach ($mountPoints as $dirEntry) {
        	if (!preg_match($pattern, $dirEntry))
        		throw new Ecl_Exception("Invalid Mountpoint Given.");

        	if ($c < count($mountPoints)-1)
        		$dirAppendToBase .= '/' . $dirEntry;

        	$c++;
		}

       	/**
       	 * Set client system and see if the base entry to the volume
       	 * exists. We will also search for volume mountpoints leading up
       	 * to the primary directory and run a release operation on the
       	 * first volume mountpoint relative to the new mountpoint.
       	 */
		$mpBaseCellName = '.'.$this->_computerobj->getProperty("emsafscell");
		$dirBasePath = '/afs/' . $mpBaseCellName . $dirAppendToBase;
		$volMountPoint = $mountPoints[count($mountPoints)-1];

		/**
		 * The full mount point path entry as created in LDAP
		 */
		$fullMountPath = $dirBasePath . '/'. $volMountPoint;
		$fullMountPathLdap = $fullMountPath . ':' . strtolower($type);

		if (!$this->_addMountPoint($dirBasePath, $volMountPoint,
			$volEntry->getProperty("cn"), $type)) {
			/**
			 * Volume mount failed.
			 */
			return false;
		}

		/**
		 * Release the base volume (if requested)
		 */
		if ($releasebase)
			$this->_releaseBaseVol($fullMountPath);

		/**
		 * Add mountpoint entry in LDAP.
		 */
		$param = $volEntry->getParameter('emsafsvolmountpoint');
		$param->addValue($fullMountPathLdap);
		$trans = $volEntry->update();
		$trans->process();
		return true;
	}

	/**
	 * Updates a mountpoint for a given volume entry. The function below
	 * makes use of createmountpoint and deletemountpoint, while calling
	 * "copyacl" in the middle to ensure the volume mount maintains the
	 * existing access control list
	 *
	 * @param EMSAfsVolume $volEntry
	 * @param string $newmp (New Mount Point)
	 * @param string $oldmp (Old Mount Point)
	 * @param string $voltype (Volume Type: RW or RO)
	 * @param integer $releasebase
	 * @return boolean
	 */
	public function updateMountPoint(EMSAfsVolume $volEntry, $newmp, $oldmp,
	$voltype, $releasebase)
	{
		Ecl_Log::DEBUG("Calling AfsServer updateMountPoint");
		Ecl_Log::DEBUG("New Mount Point is: " . $newmp);
		Ecl_Log::DEBUG("Old Mount Point is: " . $oldmp);
		Ecl_Log::DEBUG("Mount type is: " . $voltype);
		Ecl_Log::DEBUG("Release Base is: " . $releasebase);

		$nmp = '/afs/.' . $this->_computerobj->getProperty('emsafscell')
			. '/' . $newmp;

		if ($nmp == $oldmp) {
			/**
			 * Volume type has changed. We unmount and then remount.
			 * Copying Acls is not required.
			 */
			Ecl_Log::debug("Remount operation requested.");
			if ($this->deleteMountPoint($volEntry, $oldmp))
				if ($this->addMountPoint($volEntry, $newmp, $voltype)) {
					if ($releasebase)
						$this->_releaseBaseVol($nmp);
					return true;
				}

		} else {
			Ecl_Log::debug("Add new mountpoint first.");
			Ecl_Log::debug("New Mountpoint is: " . $nmp);
			Ecl_Log::debug("Old Mountpoint is: " . $oldmp);
			if ($this->addMountPoint($volEntry, $newmp, $voltype))
				if ($voltype != "ro")
					$this->_copyAcl($oldmp, $nmp);

			if ($this->deleteMountPoint($volEntry, $oldmp)) {
				if ($releasebase) {
					$this->_releaseBaseVol($nmp);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Search for a volume by volume name (cn in ldap) or by volume ID.
	 *
	 * @param string $volname
	 * @param string $volid
	 * @return array
	 */
	public function searchForVolume($vol)
	{
		if (trim($vol) == "")
			return array();

		$dn = self::RDN . $this->_computerobj->getdn();
		$pluginDN = $this->_computerobj->_lobj->respawn($dn)->getObject();

		$filter = '(&(objectclass=EMSAfsVolume)(|(cn=*'.$vol.'*)'
			. '(emsafsvolid='.$vol.'*)))';
		Ecl_Log::Debug("Filter is: " . $filter);
		return $pluginDN->getAllChildren($filter);
	}

	/**
	 * Provisions for generating a context menu for the plugin. If no context
	 * menu is desired, the plugin should simply return false.
	 *
	 * @return object $menuCfg || false
	 */
	public function generateContextMenu()
	{
		/**
		 * Load menu configuration file for AFS and simply return.
		 *
		 * @todo: take care of possible failures here -- however, if an
		 * exception is thrown, it's because the developer is miconfiguring
		 * his/her plugin.
		 */
		return false;
		$this->_iniConfig();

		return new Zend_Config_Ini($this->appConfig->bootstrap->modules .
			self::PLUGIN . '/config/menu.ini', 'ServerEntry');
	}

	/**
	 *****
	 * Private functions defined below. These interact directly with the AFS
	 * DB, File and Client Services.
	 *****
	 */
	private function _deleteVolume(EMSAfsVolume $volEntry)
	{
		$this->_iniConfig();
		$cmd = $this->_cfg->vos . " remove -id " .
			$volEntry->getProperty("cn") . " -localauth";

		$this->_shellCmd($cmd, true);
		$volData = $this->_getVolumeData($volEntry->getProperty("cn"));

		if (empty($vol_details))
			return true;
		else
			return false;
	}

	private function _releaseBaseVol($mountpoint)
	{
		if ($releaseVol = $this->_getBaseVol($mountpoint)) {
			if (!$releaseVol instanceof EMSAfsVolume)
				return false;
			else {
				/**
				 * Release this volume.
				 */
				$this->_iniConfig();

				$cmd = $this->_cfg->vos . " release " .
					$releaseVol->getProperty("cn") . " -localauth";

				$cout = $this->_shellCmd($cmd, true);
				$expout = 'Released volume ' . $releaseVol->getProperty("cn") .
					' successfully';
				if ($cout == $expout)
					return true;
				else
					return false;
			}
		}
		return false;
	}

	private function _getBaseVol($mountpoint)
	{
		$extMp = explode("/", $mountpoint);

		if ($extMp[0] == "") {
			array_shift($extMp);
			if (empty($extMp))
				false;
		}

		array_pop($extMp);
		if (empty($extMp))
			return false;

		$path = '';
		foreach ($extMp as $mp) {
			$path .= '/' . $mp;
		}

		/**
		 * Search LDAP for volume paths to get volume ID -- the base volume
		 * path may be mounted rw or ro -- we search for both.
		 */
		$fp_a = $path . ':ro';
		$fp_b = $path . ':rw';

		$filter  = '(&(objectclass=EMSAfsVolume)(|(emsafsvolmountpoint='.$fp_a.')'
			. '(emsafsvolmountpoint='.$fp_b.')))';

		/**
		 * Get/Set LDAP Connection.
		 */
		$this->_connLdap();
		$lobj = new Ecl_LdapObject($this->_dirConnection, $this->_ldapConfig->basedn);
		$lobj = $lobj->getObject();


		$rst = $lobj->getAllChildren($filter);

		if (!empty($rst)) {
			return $rst[0];
		} else
			return $this->_getBaseVol($path);
	}

	private function _releaseVol(EMSAfsVolume $volEntry)
	{

	}

	private function _copyAcl($frommp, $tomp)
	{
		$this->_iniConfig();
		$this->_setClientServer();

		$cmd = $this->_cfg->fs . " copyacl " . $frommp . " " . $tomp;
		$cout = $this->clientSystem->shellCmd($cmd, true);

		if ($cout != "")
			return false;

		return true;
	}

	private function _addMountPoint($volPath, $volMount, $volName, $type)
	{
		$this->_iniConfig();
		$this->_setClientServer();

		$cmd = 'cd ' . $volPath;
		if ($this->clientSystem->_shellCmd($cmd, true) != "") {
			Ecl_Log::debug("Failed to CD to volume base path");
			return false;
		}

		Ecl_Log::debug("CD to Volume Base path Successful");

		/**
		 * Command to mount the operation as required.
		 */
		$cmd = $this->_cfg->fs . " mkm -dir ./" . $volMount . " -vol " . $volName;

		if ($type == "rw")
			$cmd .= ' -rw';

		Ecl_Log::debug("command is: " . $cmd);

		/**
		 * The mount operation is harmless -- we can simply check to see if any
		 * output is returned from the (silent if successful) mount command to
		 * continue.
		 */
		$cout = $this->clientSystem->_shellCmd($cmd, true);

		if ($cout != "") {
			return false;
		}

		Ecl_Log::debug("Command output is: " . $cout);
		return true;
	}

	private function _deleteMountPoint($mp)
	{
		/**
		 * Steps required
		 *   Get Client System
		 *   Remove mountpoint
		 */
		$this->_iniConfig();

		/**
		 * Get AFS Client
		 */
		$this->_setClientServer();

		/**
		 * If the mountpoint path is very long, the command output
		 * will wrap and end up as output read. Another means of
		 * checking the mountpoint should be implemented here.
		 *
		 * @todo: We should chdir to the folder housing the mountpoint
		 * base and run rmm on the relative path. Folders starting with "--"
		 * will hence be honored.
		 */
		$cmd = $this->_cfg->fs . " rmm " . $mp;
		$cout = $this->clientSystem->_shellCmd($cmd, true);

		if ($cout != "")
			return false;

		return true;
	}

	private function _getPartitionUsage()
	{
		$this->_iniConfig();

		$cmd = $this->_cfg->vos . ' partinfo -server ' .
			$this->_computerobj->getProperty('cn') . ' -localauth';

		$cout = $this->_shellCmd($cmd,true);
		$partinfo = explode("<br />", nl2br(trim($cout)));
		$inf = array();
		foreach ($partinfo as $info) {
			$info = ereg_replace("[ \t]+", " ", $info);
			$info = explode (" ", $info);
			$partition = rtrim($info[4], ':');
			$inf[$partition]['free'] = $info[5];
			$inf[$partition]['available'] = $info[11];
		}
		return $inf;
	}

	private function _updateQuota(EMSAfsVolume $vol, $quota)
	{
		$this->_iniConfig();
		Ecl_Log::debug("Volume Entry: " . $vol->getproperty("emsafsvolid"));
		Ecl_Log::debug("Set Max Quota to: " . $quota);

		$mountPoints = $this->getVolMountPoints($vol);

		foreach ($mountPoints as $mountPoint) {
			$mparr = explode("/", $mountPoint);
			$c = 0;
			$cdTo = '';

			if ($mparr[0] == "")
				array_shift($mparr);

			foreach ($mparr as $mpsegment) {
				if ($c == count($mparr) -1) {
					$clsegment = explode(":", $mpsegment);
					$dirEntry = $clsegment[0];
				} else {
					$cdTo .= '/' . $mpsegment;
				}
				$c++;
			}

			/**
			 * Simply break out after getting a single mount point
			 * entry.
			 */
			break;
		}

		Ecl_Log::DEBUG("Update Quota requires CHDIR to: " . $cdTo);
		Ecl_Log::DEBUG("Update Quota for Mountpoint: " . $dirEntry);

		$this->_setClientServer();

		$cmd = 'cd ' . $cdTo;
		if ($this->clientSystem->shellCmd($cmd, true) != "") {
			Ecl_Log::debug("Failed to CD to volume base path");
			return false;
		}

		$cmd = $this->_cfg->fs . " sq " . $dirEntry . " " . $quota;
		Ecl_Log::DEBUG("Set Quota command is: " . $cmd);
		$cout = $this->clientSystem->shellCmd($cmd, true);

		if ($cout != "")
			return false;

		return true;
	}

	private function _setClientServer()
	{
		/**
		 * Get existing server role
		 */
		$this->_iniConfig();

		$role = $this->_computerobj->getProperty('emsafsrole');
		if (!in_array("CL", $role)) {
			/**
			 * Current server is not a client system. Seek Client
			 * Server in given cell. System must be integrated with
			 * the EMS to create and pass a valid EMSComputer object
			 * to the AFSClient class.
			 */
			$this->_seekClient();

		} else {
			/**
			 * Instantiate the SSH Handler
			 */
			$this->_getSshHandler();

			/**
			 * Instantiate Client System
			 */
			$this->clientSystem = new $this->_cfg->client($this->_computerobj,
				$this->_computerobj->_sshHandler);
		}
	}

	private function _seekClient()
	{
		/**
		 * @desc Function tries to search the EMS
		 * LDAP tree for a client system for the cell in
		 * question. The client system found
		 */
	}

	private function _createVolume($volname, $volquota, $partition)
	{
		$this->_iniConfig();

		/**
		 * Create Volume Command
		 */
		$cmd = $this->_cfg->vos . ' create -server ' .
			$this->_computerobj->getProperty('cn') . ' -partition ' . $partition
			. ' -name ' . $volname . ' -maxquota ' . $volquota . ' -localauth';

		$vol_details = $this->_getVolumeData($volname);

		if (!empty($vol_details))
			throw new Ecl_Exception("Volume already exists. Resync Listing.");

		/**
		 * Ensure partition specified exists.
		 */
		if (!in_array($partition, $this->_getPartitions()))
			throw new Ecl_Exception("Unknown Partition specified");

		/**
		 * Create the volume.
		 */
		Ecl_Log::debug("Executing Add Volume Command: " . $cmd);
		$cout = $this->_shellCmd($cmd,true);

		/**
		 * Command output is logged automatically. We'll try and get volume
		 * details again -- if successful, we return them.
		 */
		$vol_details = $this->_getVolumeData($volname);

		if (empty($vol_details))
			throw new Ecl_Exception("Volume creation failed. Check Log.");
		else
			return $vol_details;
	}

	private function _getPartitions()
	{
		$this->_iniConfig();

		$cmd = $this->_cfg->vos . ' listpart -server ' .
			$this->_computerobj->getProperty('cn') . ' -localauth';


		$cout = $this->_shellCmd($cmd,true);
		$cout = ereg_replace("[ \t]+", " ", trim($cout));
		$cout = explode (" ", $cout);

		$partitions = array();
		foreach ($cout as $aval) {
			$pos = strpos(trim($aval), '/');
			if ($pos === false)
				continue;
			else
				$partitions[] = trim($aval);

		}
		return $partitions;
	}

	/**
	 * Creates a complete mountpoint map and returns it as a multi-dimensional
	 * array to the caller.
	 *
	 * @todo The function below is very expensive and a caching system is
	 * required
	 */
	private function _createMountpointMap()
	{
		$this->_iniConfig();
		$ldapConfig = Zend_Registry::get('ldapConfig');

		/**
		 * Figure out which system has the 'root.cell' volume. The servers
		 * may be distributed geographically so we search from base tree
		 * dn for cell and volume information in order.
		 */
		$cell = $this->_computerobj->getproperty("emsafscell");

		$ds = Ecl_Ldap_Util::doUserBind();
		$lobj = new Ecl_LdapObject($ds, $ldapConfig->basedn);
		$lobj = $lobj->getObject();

		$filter = '(&(objectclass=EMSComputer)(emsafscell='.$cell.'))';
		$rst = $lobj->getAllChildren($filter);

		/**
		 * Set server housing the root.cell Entry.
		 */
		foreach ($rst as $entry) {
			/**
			 * Search for root.cell
			 */
			Ecl_Log::debug("Searching for root.cell in " . $entry->getdn());
			$filter = '(cn=root.cell)';
			$children = $entry->getAllChildren($filter);

			if (!empty($children)) {
				$rcFound = 1;
				$rootCellServer = $entry;
				break;
			} else
				Ecl_Log::debug("root.cell not found on " . $entry->getdn());
		}

		if (!isset($rcFound)) {
			/**
			 * Root cell was not found -- function returns false here.
			 */
			return false;
		}

		if ($rootCellServer->getdn() != $this->_computerobj->getdn()) {
			Ecl_Log::debug("Starting a new plugin Instance");
			$rootCellPlugin = new $this->_cfg->server($entry);
		} else {
			Ecl_Log::debug("Creating reference between computers");
			$rootCellPlugin = &$this;
		}

		/**
		 * Create raw mountpoint map
		 */
		$cmd = $this->_cfg->salvager . ' -showmounts -showlog';
		$cmd .= ' | awk \'{print $6,$9,$NF}\' | sed \'s/[()]//g\'';

		$cout = $rootCellPlugin->_shellCmd($cmd, true, 30);
		$cout = str_replace(array("\r\n", "\r", "\n"), "<br />", $cout);
		$cout = ereg_replace("[ \t]+", " ", $cout);
		$mpm = explode('<br />', $cout);

		for ($c=4; $c < count($mpm) - 3; $c++) {
			$volmap = explode(" ", $mpm[$c]);
			if ($volmap[0] == 'root.afs') {
				$str = '\'%' . $this->_computerobj->getProperty("emsafscell")
					. ':root.cell.\'';

				/**
				 * We're assuming that multiple mount points for root.cell
				 * and root.afs do not exist.
				 */
				if ($volmap[2] == $str) {
					$this->_mountPointMap['root.cell']['mountpoint'] = '/afs/.' .
						$this->_computerobj->getProperty("emsafscell");

					$this->_mountPointMap['root.afs']['mountpoint'] = '/afs/' .
						$this->_computerobj->getProperty("emsafscell");

					$this->_mountPointMap['root.afs']['lentry'] = '/afs/' .
						$this->_computerobj->getProperty("emsafscell") . ':RO';

					$this->_mountPointMap['root.cell']['lentry'] = '/afs/.' .
						$this->_computerobj->getProperty("emsafscell") . ':RW';
				}
			}

			/**
			 * Next we check for all volumes mounted in root.cell
			 */
			if ($volmap[0] == "root.cell") {

				/**
				 * Mount point types are either RW or RO
				 */
				$type = substr($volmap[2], 1, 1);

				if ($type == '%')
					$st = 'rw';
				else
					$st = 'ro';

				$this->_mountPointMap[substr($volmap[2], 2, -2)]['mountpoint'][] =
					$this->_mountPointMap['root.cell']['mountpoint'] . '/' .
					substr($volmap[1], 2);
				$this->_mountPointMap[substr($volmap[2], 2, -2)]['type'][] = $st;

				/**
				 * And an array created for LDAP entries.
				 */
				$this->_mountPointMap[substr($volmap[2], 2, -2)]['lentry'][] =
					$this->_mountPointMap['root.cell']['mountpoint'] . '/' .
					substr($volmap[1], 2) . ':' . strtolower($st);
			}

			/**
			 * At this point, we must cross reference mountpoints and generate
			 * the mpm.
			 */
			if ($volmap[0] != 'root.cell' && $volmap[0] != 'root.afs') {
				if (array_key_exists($volmap[0], $this->_mountPointMap)) {
					foreach ($this->_mountPointMap[$volmap[0]]['mountpoint'] as $mp) {
						$type = substr($volmap[2], 1, 1);
						if ($type == '%')
							$st = 'rw';
						else
							$st = 'ro';

						$this->_mountPointMap[substr($volmap[2], 2, -2)]['mountpoint'][]
							= $mp . '/' . substr($volmap[1], 2);
						$this->_mountPointMap[substr($volmap[2], 2, -2)]['type'][]
							= $st;
						$this->_mountPointMap[substr($volmap[2], 2, -2)]['lentry'][] =
							$mp . '/' . substr($volmap[1], 2) . ':' . strtolower($st);
					}
				}
			}
		}
	}

	private function _getVolumeData($volume_id)
	{
		/**
		 * Return all data pertaining to an existing volume.
		 * @return array
		 */
		$this->_iniConfig();
		$cmd = $this->_cfg->vos . ' examine ' . $volume_id . ' -format -localauth';
		$cout = $this->_shellCmd($cmd, true);

		if (trim($cout) == "VLDB: no such entry")
			return array();

		/**
		 * Otherwise we format the data and return it in an array.
		 */
		$cout = nl2br($cout);
		$vol_info = explode("<br />", $cout);
		$vol = array();

		/**
		 * @todo: before this loop, pop the last 11 elements of the array off
		 */

		foreach ($vol_info as $attribute) {
			if (strpos($attribute, 'name') !== FALSE) {
				$vol['name'] = trim(substr($attribute, strrpos($attribute,"name") + 4));
			}
			if (strpos($attribute, 'serv') !== FALSE) {
				if (!isset($vol['server'])) {
					$vol['server'] = trim(substr($attribute, strrpos($attribute,"server") + 6));
					$vol['server'] = ereg_replace("[ \t]+", " ", $vol['server']);
				}
			}
			if (strpos($attribute, 'id') !== FALSE) {
				$vol['id'] = trim(substr($attribute, strrpos($attribute,"id") + 2));
			}
			if (strpos($attribute, 'parentID') !== FALSE) {
				$vol['parentid'] = trim(substr($attribute, strrpos($attribute,"parentID") + 8));
			}
			if (strpos($attribute, 'status') !== FALSE) {
				$vol['status'] = trim(substr($attribute, strrpos($attribute,"status") + 6));
			}
			if (strpos($attribute, 'backupID') !== FALSE) {
				$vol['backupid'] = trim(substr($attribute, strrpos($attribute,"backupID") + 8));
			}
			if (strpos($attribute, 'cloneID') !== FALSE) {
				$vol['cloneid'] = trim(substr($attribute, strrpos($attribute,"cloneID") + 7));
			}
			if (strpos($attribute, 'destroyMe') !== FALSE) {
				$vol['destroyme'] = trim(substr($attribute, strrpos($attribute,"destroyMe") + 9));
			}
			if (strpos($attribute, 'creationDate') !== FALSE) {
				$vol['creationdate'] = trim(substr($attribute, strrpos($attribute,"creationDate") + 12));
				$vol['creationdate'] = ereg_replace("[ \t]+", " ", $vol['creationdate']);
			}
			if (strpos($attribute, 'accessDate') !== FALSE) {
				$vol['accessdate'] = trim(substr($attribute, strrpos($attribute,"accessDate") + 10));
				$vol['accessdate'] = ereg_replace("[ \t]+", " ", $vol['accessdate']);
			}
			if (strpos($attribute, 'updateDate') !== FALSE) {
				$vol['updatedate'] = trim(substr($attribute, strrpos($attribute,"updateDate") + 10));
				$vol['updatedate'] = ereg_replace("[ \t]+", " ", $vol['updatedate']);
			}
			if (strpos($attribute, 'backupDate') !== FALSE) {
				$vol['backupdate'] = trim(substr($attribute, strrpos($attribute,"backupDate") + 10));
				$vol['backupdate'] = ereg_replace("[ \t]+", " ", $vol['backupdate']);
			}
			if (strpos($attribute, 'copyDate') !== FALSE) {
				$vol['copydate'] = trim(substr($attribute, strrpos($attribute,"copyDate") + 8));
				$vol['copydate'] = ereg_replace("[ \t]+", " ", $vol['copydate']);
			}
			if (strpos($attribute, 'part') !== FALSE) {
				if (!isset($vol['partition'])) {
					$vol['partition'] = trim(substr($attribute, strrpos($attribute,"part") + 4));
				}
			}
			if (strpos($attribute, 'type') !== FALSE) {
				$vol['type'] = trim(substr($attribute, strrpos($attribute,"type") + 4));
			}
			if (strpos($attribute, 'inUse') !== FALSE) {
				$vol['inuse'] = trim(substr($attribute, strrpos($attribute,"inUse") + 5));
			}
			if (strpos($attribute, 'needsSalvaged') !== FALSE) {
				$vol['needssalvage'] = trim(substr($attribute, strrpos($attribute,"needsSalvaged") + 13));
			}
			if (strpos($attribute, 'maxquota') !== FALSE) {
				$vol['maxquota'] = trim(substr($attribute, strrpos($attribute,"maxquota") + 8));
			}
			if (strpos($attribute, 'diskused') !== FALSE) {
				$vol['diskused'] = trim(substr($attribute, strrpos($attribute,"diskused") + 8));
			}
			if (strpos($attribute, 'flags') !== FALSE) {
				$vol['flags'] = trim(substr($attribute, strrpos($attribute,"flags") + 5));
				$vol['flags'] = ereg_replace("[ \t]+", " ", $vol['flags']);
			}
			if (strpos($attribute, 'filecount') !== FALSE) {
				$vol['filecount'] = trim(substr($attribute, strrpos($attribute,"filecount") + 9));
			}
			if (strpos($attribute, 'dayUse') !== FALSE) {
				$vol['dayuse'] = trim(substr($attribute, strrpos($attribute,"dayUse") + 6));
			}
			if (strpos($attribute, 'weekUse') !== FALSE) {
				$vol['weekuse'] = trim(substr($attribute, strrpos($attribute,"weekUse") + 7));
				$vol['weekuse'] = ereg_replace("[ \t]+", " ", $vol['weekuse']);
			}
		}
		return $vol;
	}

	private function _getVols($bypartition = false, $partition = '')
	{
		$this->_iniConfig();
		/**
		 * @todo: add option of getting volume by specific partition.
		 */
		if ($bypartition == true && $partition != '') {
			if (!in_array($partition, $this->_getPartitions())) {
				throw new Ecl_Exception('Invalid partition specified in
					getVols requests.');
			}
			$cmd = $this->_cfg->vos . ' listvol -server '.
				$this->_computerobj->getProperty('cn') . ' -partition ' .
				$partition . ' -format -localauth';
		} else {
			$cmd = $this->_cfg->vos . ' listvol -server ' .
				$this->_computerobj->getProperty('cn') . ' -format -localauth';
		}

		$cout = $this->_shellCmd($cmd, true);
		$cout = nl2br($cout);
		$cout = explode ('BEGIN_OF_ENTRY', $cout);
		$vols = array();
		$c = 0;
		foreach ($cout as $vol_data) {
			$vol_info = explode("<br />", $vol_data);
			foreach ($vol_info as $vol_details) {
				$attrs = explode ("<br />", $vol_details);
				foreach ($attrs as $attribute) {
					if (trim($attribute) != 'END_OF_ENTRY') {
						if (strpos($attribute, 'name') !== FALSE) {
							$vols[$c]['name'] = trim(substr($attribute,
									strrpos($attribute,"name") + 4));
						}
						if (strpos($attribute, 'serv') !== FALSE) {
							$vols[$c]['server'] = trim(substr($attribute,
									strrpos($attribute,"server") + 6));
							$vols[$c]['server'] = ereg_replace("[ \t]+", " ",
								$vols[$c]['server']);
						}
						if (strpos($attribute, 'id') !== FALSE) {
							$vols[$c]['id'] = trim(substr($attribute,
									strrpos($attribute,"id") + 2));
						}
						if (strpos($attribute, 'parentID') !== FALSE) {
							$vols[$c]['parentid'] = trim(substr($attribute,
									strrpos($attribute,"parentID") + 8));
						}
						if (strpos($attribute, 'status') !== FALSE) {
							$vols[$c]['status'] = trim(substr($attribute,
									strrpos($attribute,"status") + 6));
						}
						if (strpos($attribute, 'backupID') !== FALSE) {
							$vols[$c]['backupid'] = trim(substr($attribute,
									strrpos($attribute,"backupID") + 8));
						}
						if (strpos($attribute, 'cloneID') !== FALSE) {
							$vols[$c]['cloneid'] = trim(substr($attribute,
									strrpos($attribute,"cloneID") + 7));
						}
						if (strpos($attribute, 'destroyMe') !== FALSE) {
							$vols[$c]['destroyme'] = trim(substr($attribute,
									strrpos($attribute,"destroyMe") + 9));
						}
						if (strpos($attribute, 'creationDate') !== FALSE) {
							$vols[$c]['creationdate'] = trim(substr($attribute,
									strrpos($attribute,"creationDate") + 12));
							$vols[$c]['creationdate'] = ereg_replace("[ \t]+",
								" ", $vols[$c]['creationdate']);
						}
						if (strpos($attribute, 'accessDate') !== FALSE) {
							$vols[$c]['accessdate'] = trim(substr($attribute,
									strrpos($attribute,"accessDate") + 10));
							$vols[$c]['accessdate'] = ereg_replace("[ \t]+",
								" ", $vols[$c]['accessdate']);
						}
						if (strpos($attribute, 'updateDate') !== FALSE) {
							$vols[$c]['updatedate'] = trim(substr($attribute,
									strrpos($attribute,"updateDate") + 10));
							$vols[$c]['updatedate'] = ereg_replace("[ \t]+",
								" ", $vols[$c]['updatedate']);
						}
						if (strpos($attribute, 'backupDate') !== FALSE) {
							$vols[$c]['backupdate'] = trim(substr($attribute,
									strrpos($attribute,"backupDate") + 10));
							$vols[$c]['backupdate'] = ereg_replace("[ \t]+",
								" ", $vols[$c]['backupdate']);
						}
						if (strpos($attribute, 'copyDate') !== FALSE) {
							$vols[$c]['copydate'] = trim(substr($attribute,
									strrpos($attribute,"copyDate") + 8));
							$vols[$c]['copydate'] = ereg_replace("[ \t]+",
								" ", $vols[$c]['copydate']);
						}
						if (strpos($attribute, 'part') !== FALSE) {
							$vols[$c]['partition'] = trim(substr($attribute,
									strrpos($attribute,"part") + 4));
						}
						if (strpos($attribute, 'type') !== FALSE) {
							$vols[$c]['type'] = trim(substr($attribute,
									strrpos($attribute,"type") + 4));
						}
						if (strpos($attribute, 'inUse') !== FALSE) {
							$vols[$c]['inuse'] = trim(substr($attribute,
									strrpos($attribute,"inUse") + 5));
						}
						if (strpos($attribute, 'needsSalvaged') !== FALSE) {
							$vols[$c]['needssalvage'] = trim(substr($attribute,
									strrpos($attribute,"needsSalvaged") + 13));
						}
						if (strpos($attribute, 'maxquota') !== FALSE) {
							$vols[$c]['maxquota'] = trim(substr($attribute,
									strrpos($attribute,"maxquota") + 8));
						}
						if (strpos($attribute, 'diskused') !== FALSE) {
							$vols[$c]['diskused'] = trim(substr($attribute,
									strrpos($attribute,"diskused") + 8));
						}
						if (strpos($attribute, 'flags') !== FALSE) {
							$vols[$c]['flags'] = trim(substr($attribute,
									strrpos($attribute,"flags") + 5));
							$vols[$c]['flags'] = ereg_replace("[ \t]+", " ",
								$vols[$c]['flags']);
						}
						if (strpos($attribute, 'filecount') !== FALSE) {
							$vols[$c]['filecount'] = trim(substr($attribute,
									strrpos($attribute,"filecount") + 9));
						}
						if (strpos($attribute, 'dayUse') !== FALSE) {
							$vols[$c]['dayuse'] = trim(substr($attribute,
									strrpos($attribute,"dayUse") + 6));
						}
						if (strpos($attribute, 'weekUse') !== FALSE) {
							$vols[$c]['weekuse'] = trim(substr($attribute,
									strrpos($attribute,"weekUse") + 7));
							$vols[$c]['weekuse'] = ereg_replace("[ \t]+", " ",
								$vols[$c]['weekuse']);
						}
					} else
						$c++;
				}
			}
		}
		return $vols;
	}

	private function _addVolsToTrans(EMSSecurityObject $pluginDN,
		Ecl_Transaction_Handler $handler, $vol=array())
	{
		/**
		 * Get vols on server and add them to transaction (unless an array
		 * of volume entries has been passed.
		 */
		if (!empty($vol))
			$vols = $vol;
		else
			$vols = $this->_getVols();

		foreach ($vols as $volume_data) {
			/**
			 * Create LDAP entries for volumes in transaction handler.
			 */

			$afsvol = new EMSAfsVolume($pluginDN->newLdapObject(null));
			$param = $afsvol->getParameter('emsafsvolmountpoint');

			if (array_key_exists($volume_data['name'], $this->_mountPointMap)) {
				$param->addValue($this->_mountPointMap[$volume_data['name']]['lentry']);
			}

			$afsvol->setProperty('cn',$volume_data['name']);
			$afsvol->setProperty('emsafsvolname',$volume_data['name']);
			$afsvol->setProperty('emsafsvolid',$volume_data['id']);
			$afsvol->setProperty('emsafsvollocation',$volume_data['server']);
			$afsvol->setProperty('emsafsvolpartition',$volume_data['partition']);
			$afsvol->setProperty('emsafsvolstatus',$volume_data['status']);
			$afsvol->setProperty('emsafsvolbackupid',$volume_data['backupid']);
			$afsvol->setProperty('emsafsvolparentid',$volume_data['parentid']);
			$afsvol->setProperty('emsafsvolcloneid',$volume_data['cloneid']);
			$afsvol->setProperty('emsafsvolinuse',$volume_data['inuse']);
			$afsvol->setProperty('emsafsvolneedssalvage', $volume_data['needssalvage']);
			$afsvol->setProperty('emsafsvoldestroyme', $volume_data['destroyme']);
			$afsvol->setProperty('emsafsvoltype',$volume_data['type']);
			$afsvol->setProperty('emsafsvolcreationdate', $volume_data['creationdate']);
			$afsvol->setProperty('emsafsvolaccessdate', $volume_data['accessdate']);
			$afsvol->setProperty('emsafsvolcopydate',$volume_data['copydate']);
			$afsvol->setProperty('emsafsvolbackupdate', $volume_data['backupdate']);
			$afsvol->setProperty('emsafsvolupdatedate', $volume_data['updatedate']);
			$afsvol->setProperty('emsafsvoldiskused', $volume_data['diskused']);
			$afsvol->setProperty('emsafsvolmaxquota', $volume_data['maxquota']);
			$afsvol->setProperty('emsafsvolfilecount', $volume_data['filecount']);
			$afsvol->setProperty('emsafsvoldayuse', $volume_data['dayuse']);
			$afsvol->setProperty('emsafsvolweekuse', $volume_data['weekuse']);
			$afsvol->setProperty('emstype', 'AfsVolumeEntry');
			$afsvol->setProperty('emsdescription', 'EMS OpenAFS Volume Entry');
			$handler = $afsvol->add($pluginDN, $handler);
		}

		return $handler;
	}


	private function _connLdap()
	{
		if (!$this->_dirConnection instanceof Ecl_Ldap)
			$this->_dirConnection = Ecl_Ldap_Util::doUserBind();

		/**
		 * Ensure LdapConfig is available as well
		 */
		if (!$this->_ldapConfig instanceof Zend_Config_Ini)
			$this->_ldapConfig = Zend_Registry::get('ldapConfig');
	}

	/**
	 * Initialize plugin configuration as Zend_Ini object
	 *
	 * @param none
	 * @return void
	 */
	private function _iniConfig()
	{
		if (!$this->_cfg instanceof Zend_Config_Ini) {
			$cfg_section = $this->_computerobj->getproperty("emscomputerdistro")
				. '_' . $this->_computerobj->getproperty("emscomputerdistrorelease");
			/**
			 * Get application configuration object
			 */
			$this->appConfig = Zend_Registry::get('appConfig');

			/**
			 * Instantiate cfg object for plugin
			 */
			$this->_cfg = new Zend_Config_Ini($this->appConfig->bootstrap->modules .
				self::PLUGIN . '/config/plugin.ini', $cfg_section);
		}
	}
}
