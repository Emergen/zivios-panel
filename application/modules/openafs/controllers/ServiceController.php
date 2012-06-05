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
 * @version		$Id: ServiceController.php 919 2008-08-25 11:45:23Z fkhan $
 * @lastchangeddate $LastChangedDate: 2008-08-25 17:45:23 +0600 (Mon, 25 Aug 2008) $
 **/
class Openafs_ServiceController extends Ecl_Controller_Service
{
	protected function _init()
	{
		if (!isset($this->json->operate_dn))
			throw new Ecl_Exception("Invalid called to _init(). Operate DN" .
				" missing");
		$this->_doLdapBind();
		ecl_log::debug('operate dn is: ' . $this->json->operate_dn);
		$obj = new Ecl_LdapObject($this->_dirConnection, $this->json->operate_dn);
		$this->view->obj = $obj->getObject();
		unset($obj);

		/**
		 * Initialize _serviceConfig
		 */
		$this->_initServiceConfig();
	}

	public function addServiceAction()
	{
		echo $this->_serviceConfig->distros->supported;
		return;


		/**
		 * Validate user supplied data.
		 * @todo: remove the below validation method and perform validation locally below.
		 */
		$validate = new $this->_pluginConfig->validate($this->view->plugin, $this->json);

		if ($validate->msg != "") {
			echo Zend_Json_Encoder::encode(array('err' => 1, 'msg' => $validate->msg));
			return;
		}

		$trans = $this->view->obj->addplugin($this->view->plugin);
		$trans->process();
		$msg = "Plugin Initialized Successfully on System. Refreshing Computer Data...";
		echo Zend_Json_Encoder::encode(array('err' => 0,'msg' => $msg));
		$this->_jsCallBack('node_details', array($this->view->obj->getdn()));
		return;
	}

	public function serverManagerAction()
	{
		$this->_init();
		/**
		 * Render the AFS Manager template.
		 */
		$this->render("manager");
	}

	public function resyncVolumeListingAction()
	{
		$this->_init();

		/**
		 * Call volume resync and regenerate listing.
		 */
		$this->view->plugin->resyncVolumeListing();
		$this->view->vols = $this->view->plugin->getVolsOnServer();
		$this->_createPopupReturn(0, "Resync of Volume Data Successful");
		$this->render("listvolumes");
	}

	public function deleteMountPointAction()
	{
		$this->_init();
		$this->view->volEntry = $this->view->plugin->getVolumeDetails($this->json->voldn);

		if ($this->view->plugin->deleteMountPoint($this->view->volEntry,
			$this->json->mp)) {
			$this->_createPopupReturn(0, "Volume mount point removed successfully");
			$this->_jsCallBack('getVolDetails', array('/openafs/server/getvoldetails',
				'pgupdate', $this->view->volEntry->getdn()));
		} else
			$this->_createPopupReturn(1, "Could not remove mount point. Check System Logs");
	}

	public function updateMountPointAction()
	{
		$this->_init();

		$validate = new $this->_pluginConfig->validate($this->view->plugin,
			$this->json);

		if ($validate->msg != "") {
			$this->_createPopupReturn(1, $validate->msg);
			return;
		}

		/**
		 * Update the mountpoint as specified.
		 */
		$this->view->volEntry =
			$this->view->plugin->getVolumeDetails($this->json->voldn);

		isset($this->json->releasebasevol) ? $rb = 1 : $rb = 0;

		if ($this->view->plugin->updateMountPoint($this->view->volEntry,
			$this->json->nmp, $this->json->omp, $this->json->vtype, $rb)) {

			/**
			 * Update mountpoint operation successful.
			 */
			$this->_createPopupReturn(0,"Volume Mount Point Updated Successfully");

			/**
			 * For a cleaner view of the call, please see addMountPointAction()
			 */
			$this->_jsCallBack('getVolDetails', array(
				'/openafs/server/getvoldetails', 'pgupdate',
				$this->view->volEntry->getdn()));
		} else {
			/**
			 * Updating mountpoint operation failed.
			 */
			$this->_createPopupReturn(1, "Error updating Volume Mount Point." .
				" See System Logs");
		}
		return;
	}

	public function addMountPointAction()
	{
		$this->_init();

		$validate = new $this->_pluginConfig->validate($this->view->plugin,
			$this->json);

		if ($validate->msg != "") {
			$this->_createPopupReturn(1, $validate->msg);
			return;
		}

		/**
		 * Call create mountpoint action in plugin
		 */
		$this->view->volEntry =
			$this->view->plugin->getVolumeDetails($this->json->voldn);

		isset($this->json->releasebasevol) ? $rb = 1 : $rb = 0;

		if (!$this->view->plugin->addMountPoint($this->view->volEntry,
			trim($this->json->newmountpoint), $this->json->vtype, $rb)) {
			$this->_createPopupReturn(1, "The mount volume operation failed." .
				 " Please consult the server logs for details");
			return;
		}

		$this->_createPopupReturn(0, "Mount Point Added Successfully");

		/**
		 * I am leaving this fairly clean as an example. The function below
		 * makes a JS call on the client side to refresh all div data.
		 */
		$args = array();
		$args[] = '/openafs/server/getvoldetails';
		$args[] = 'pgupdate';
		$args[] = $this->view->volEntry->getdn();
		$this->_jsCallBack('getVolDetails', $args);
	}

	public function listVolumesAction()
	{
		$this->_init();
		/**
		 * List all volumes on Server.
		 */
		$this->view->vols = $this->view->plugin->getVolsOnServer();
		$this->render("listvolumes");
	}

	public function getVolDetailsAction()
	{
		$this->_init();

		/**
		 * Get volume details by ID
		 */
		$this->view->volEntry = $this->view->plugin->getVolumeDetails($this->json->voldn);
		$this->render("voldetails");
	}

	public function addVolumeAction()
	{
		$this->_init();

		if (!isset($this->json->process)) {
			$this->render("addvolume");
			return;
		}

		/**
		 * Get validation class
		 */
		$validate = new $this->_pluginConfig->validate($this->view->plugin,
			$this->json);

		if ($validate->msg != "") {
			/**
			 * Create popup return with error message.
			 */
			$this->_createPopupReturn(1, $validate->msg);
			return;
		}

		isset($this->json->releasebasevol) ? $rb = 1 : $rb = 0;

		/**
		 * Create the volume in the AFS system.
		 */
		if ($voldn = $this->view->plugin->addAfsVolume($this->json->volname,
			$this->json->maxquota, $this->json->partition)) {
			$volEntry = $this->view->plugin->getVolumeDetails($voldn);

			if ($this->json->newmountpoint != "") {
				/**
				 * Create EMSAfsVolume object for added volume and
				 * set mountpoint in ldap.
				 */
				if ($this->view->plugin->addMountPoint($volEntry,
					$this->json->newmountpoint, $this->json->vtype, $rb)) {
						$this->_createPopupReturn(0, "Volume and mount point "
						. "added successfully");

				} else {
					$this->_createPopupReturn(0, "Volume added successfully. "
						. "Mount Point Operation Failed.");
				}
			} else {
				$this->_createPopupReturn(0, "Volume added successfully");
			}

			/**
			 * Redirect to volume details page.
			 */
			$this->_jsCallBack('getVolDetails', array(
				'/openafs/server/getvoldetails', 'pgupdate',
				$volEntry->getdn()));

		} else {
			$this->_createPopupReturn(1, "The add volume operation failed" .
				 " Please contact your support team");
		}
	}

	public function deleteVolumeAction()
	{
		$this->_init();

		$volEntry = $this->view->plugin->getVolumeDetails($this->json->voldn);

		if ($this->view->plugin->deleteVolume($volEntry)) {
			$this->_createPopupReturn(0, "Volume deleted successfully");

			$this->_jsCallBack('callAfsAction', array(
				'/openafs/server/listvolumes/', 'pgupdate'));

		} else {
			$this->_createPopupReturn(1, "The delete volume operation failed." .
				 " Please contact your support team");
		}
	}

	public function updateQuotaAction()
	{
		$this->_init();

		$validate = new $this->_pluginConfig->validate($this->view->plugin,
			$this->json);

		if ($validate->msg != "") {
			$this->_createPopupReturn(1, $validate->msg);
			return;
		}

		/**
		 * Call Adjust Quota Action
		 */
		$volEntry = $this->view->plugin->getVolumeDetails($this->json->voldn);

		if ($this->view->plugin->updateQuota($volEntry, $this->json->maxquota)) {
			$this->_createPopupReturn(0, "Quota Adjusted Successfully");
			$this->_jsCallBack('getVolDetails', array(
				'/openafs/server/getvoldetails', 'pgupdate',
				$volEntry->getdn()));
		} else {
			$this->_createPopupReturn(1, "Request Failed. Please contact your "
			. "system administrator.");
		}
	}

	/**
	 * Search for an AFS Volume by volume name (cn) or volume id
	 * in LDAP.
	 *
	 * Note: $this->_init() is called with optional argument of operate_dn
	 * here.
	 */
	public function searchVolumeAction()
	{
		/**
		 * Prepare Search String.
		 */
		$search = trim(strip_tags($_POST['volsearch']));
		$vols = $this->view->plugin->searchForVolume($search);

		if (!empty($vols)) {
			echo "<ul>";
			foreach ($vols as $volume)
				echo "<li id='".$volume->getdn()."'>".$volume->getProperty("cn")."</li>";

			/**
			 * End Output.
			 */
			echo "</ul>";
		}
	}
}

