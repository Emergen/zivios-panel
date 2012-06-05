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
 * @package		Zivios
 * @copyright	Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class AsteriskService extends EMSService
{
	protected $_module = 'asterisk';
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
	}

	public function add(Zivios_Ldap_Engine $parent,Zivios_Transaction_Group $tgroup)
	{
        $this->addObjectClass('namedObject');
        $this->addObjectClass('emsastservice');

        return parent::add($parent,$tgroup);
	}

	public function returnDisplayName()
	{
		return "Zivios Asterisk";
	}

	public function returnModuleName()
	{
		return "asterisk";
	}

	public function generateContextMenu()
	{
		return false;
	}

	public function getAgent()
	{
		return $this->_initCommAgent();
	}

	public function getObRoutes()
	{
		return array ("internal","local","nwd","mobile","intl");
		
	}
	
	public function stopService()
	{
		$this->_initCommAgent();
		if (!$this->_commAgent->getstatus()) {
			Zivios_Log::warn("Stop command sent to Asterisk Service -- Service not Running!");
			return false;
		} else {
			if ($this->_commAgent->stopservice()) {
				Zivios_Log::info("Asterisk service halted.");
				return true;
			} else {
				Zivios_Log::error("Could not stop Asterisk Service.");
				return false;
			}
		}

	}

	public function getCdr($perPage, $offSet)
	{

		$cdr = new Cdr();
		$filter = null;
		try {
			$cdrfilter = new Zend_Session_Namespace();
			$filter = $cdrfilter->filter;
		} catch (Exception $e){
			$filter = null;
		}
		$data['results'] = $cdr->fetchAll($filter, null, $perPage, $offSet);
		$data['count'] = count($cdr->fetchAll($filter)->toArray());
		return $data;
	}



	public function channelStatus()
	{
		$commagent = $this->_initCommAgent();
		$chans = $commagent->getchanstatus();
		return $chans;
	}


	public function Hangup($channel)
	{
		$commagent = $this->_initCommAgent();
		$commagent->hangup($channel);
		return 1;
	}

	public function startService()
	{
		$this->_initCommAgent();
		if ($this->_commAgent->getstatus()) {
			Zivios_Log::warn("Trying to Start Asterisk Service... already running.");
			return false;
		} else {
			if ($this->_commAgent->startservice()) {
				Zivios_Log::info("Asterisk service started.");
				return true;
			} else {
				Zivios_Log::error("Could not start Asterisk Service.");
				return false;
			}
		}
	}

	public function loadDashboardData()
	{
		$this->_initCommAgent();

		$dashboardData = array();

		if ($this->_commAgent->getstatus())
			$dashboardData['status'] = 1;
		else
			$dashboardData['status'] = 0;

		return $dashboardData;
	}

    public function getMainForm($compArray)
    {
        $regexLib = $this->_getRegexLibrary();

        $compArray = array('-1' => 'Select Server') + $compArray;

        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'addservice',
            'legend' => 'Text Elements',
            'dijitParams' => array(
                'title' => 'Service Add From',
            ),
        ));

        $form->addElement('FilteringSelect', 'emsmastercomputerdn', Array(
                'required'      => true,
                'multiOptions'  => $compArray,
                'regExp'        => $regexLib->exp->dn,
                'title'         => 'Select Server',
                'label'         => 'Select Server',
                'invalidMessage'    => 'Invalid characters in hostname field.',
                'filters'           => array('StringTrim'),
                'validators'        => array(
                                           array('Regex', true, array('/'.$regexLib->exp->dn.'/')),
                                       ),
                'autocomplete'  => false
        ));

        return $form;
    }

    public function setMainForm($valarray, $compArray)
    {
        $this->setForm($this->getMainForm($compArray),$valarray);
    }

}
