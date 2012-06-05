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

class SquidUser extends Zivios_Plugin_User
{
    protected $_module = 'squid';

    public function __construct()
	{
    }

	public function getAttrs()
	{
		$attrs = parent::getAttrs();
		$attrs[] = 'emssquidactive';
		$attrs[] = 'emssquidblacklist';
		$attrs[] = 'emssquidwhitelist';
		$attrs[] = 'emssquidtimeofday';
		return 	 $attrs;
	}

	public function enablePlugin()
	{
		$this->setProperty('emssquidactive','Y');
	}

	public function disablePlugin()
	{
		$this->setProperty('emssquidactive','N');
	}

   public function init(EMSPluginManager $pm)
   {
        parent::init($pm);
   }

    public function add(Zivios_Transaction_Group $tgroup,$description=null)
    {
        $this->addObjectClass('emssquiduser');
        $this->enablePlugin();
        return parent::add($tgroup,$description);
    }

    public function delete(Zivios_Transaction_Group $tgroup,$decription=null)
    {
        //removing squid user plugin attributes
        $this->removeProperty('emssquidactive');
        $this->removeProperty('emssquidblacklist');
        $this->removeProperty('emssquidwhitelist');
        $this->removeProperty('emssquidtimeofday');

        $this->removeObjectClass('emssquiduser');
        return parent::delete($tgroup,$decription);
    }

    public function getUserConfigForm()
    {
        if (null === ($whitelist = $this->getProperty('emssquidwhitelist'))) {
            $whitelist = '';
        } else {
            if (is_array($whitelist)) {
                $whitelist = implode("\n", $whitelist);
            }
        }

        if (null === ($blacklist = $this->getProperty('emssquidblacklist'))) {
            $blacklist = '';
        } else {
            if (is_array($blacklist)) {
                $blacklist = implode("\n", $blacklist);
            }
        }

        if (null === ($timeOfDay = $this->getProperty('emssquidtimeofday'))) {
            $startTime  = 'T08:00:00';
            $endTime    = 'T17:30:00';
            $daysOfWeek = array();
        } else {

            $timeOfDay  = explode("|", $this->getProperty('emssquidtimeofday'));
            $daysOfWeek = chunk_split($timeOfDay[0], 1, '.');
            $daysOfWeek = explode('.', $daysOfWeek);

            $startTime = date('Y-m-d\TH:i', strtotime($timeOfDay[1]));
            $endTime   = date('Y-m-d\TH:i', strtotime($timeOfDay[2]));
        }

        $arrDaysOfWeek = array('1' => 'Monday',
                               '2' => 'Tuesday',
                               '3' => 'Wednesday',
                               '4' => 'Thursday',
                               '5' => 'Friday',
                               '6' => 'Saturday',
                               '7' => 'Sunday');

        $form = new Zend_Dojo_Form_SubForm();
        $form->setAttribs(array(
            'name'   => 'userconfigform',
            'dijitParams' => array(
                'title' => 'Squid User Config Form',
                ''
            ),
        ));

        $form->addElement('SimpleTextarea', 'emssquidwhitelist', array(
            'description'       => 'A white list limits access to only the web'.
                                    ' sites defined. Enter one web address per line.',
            'required'          => true,
            'label'             => 'White List: ',
            'filters'           => array('StringTrim'),
            'invalidMessage'    => 'Invalid characters in whitelist field.',
            'style'             => 'width: 14.5em; height: 5em;',
            'value'             => $whitelist,
        ));
        $form->getElement('emssquidwhitelist')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement('SimpleTextarea', 'emssquidblacklist', array(
            'description'       => 'A black list denies access to only web'.
                                    ' sites defined. Enter one web address per line.',
            'required'          => true,
            'label'             => 'Black List: ',
            'filters'           => array('StringTrim'),
            'invalidMessage'    => 'Invalid characters in whitelist field.',
            'style'             => 'width: 14.5em; height: 5em;',
            'value'             => $blacklist,
        ));
        $form->getElement('emssquidblacklist')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement('multiCheckbox', 'weekdays', array(
            'description'       => 'Limit access to certain days of the week.',
            'filters'           => array('StringTrim', 'StripTags'),
            'required'          => true,
            'label'             => 'Days of week:',
            'MultiOptions'      => $arrDaysOfWeek,
            'value'             => $daysOfWeek
        ));
        $form->getElement('weekdays')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement('TimeTextBox', 'fromtime', array(
            'description'        => 'Limit access by defining start and end times.',
            'label'              => 'Start time: ',
            'required'           => true,
            'visibleRange'       => 'T03:00:00',
            'visibleIncrement'   => 'T00:30:00',
            'clickableIncrement' => 'T00:30:00',
            'value'              => $startTime,
        ));
        $form->getElement('fromtime')->getDecorator('description')->setOptions(
            array(
                'placement' => 'prepend', 
                'class'     => 'form descfrm',
        ));

        $form->addElement('TimeTextBox', 'totime', array(
             'label'              => 'End time: ',
             'required'           => true,
             'visibleRange'       => 'T03:00:00',
             'visibleIncrement'   => 'T00:30:00',
             'clickableIncrement' => 'T00:30:00',
             'value'              => $endTime,
        ));
        
        return $form;
    }
    
    public function setUserConfigForm($data)
    {
        // Validate form data and prepare array for prepAdd call.
        $whitelist = '';
        $blacklist = '';
        if (isset($data['emssquidwhitelist']) && trim($data['emssquidwhitelist']) != '') {
            $whitelist = explode("\n", strip_tags($data['emssquidwhitelist']));
        }

        if (isset($data['emssquidblacklist']) && trim($data['emssquidblacklist']) != '') {
            $blacklist = explode("\n", strip_tags($data['emssquidblacklist']));
        }

        if ($whitelist != '' && $blacklist != '') {
            throw new Zivios_Error('A white list and black list cannot be defined together. Please read'.
                                   ' the relevant documentation before proceeding.');
        }

        if (isset($data['weekdays']) && is_array($data['weekdays']) && !empty($data['weekdays'])) {
            $weekdays = implode('', $data['weekdays']);
        } else {
            $weekdays = '';
        }
        
        $fromTime = trim($data['fromtime']);
        $toTime   = trim($data['totime']);

        if ($fromTime == '' || $toTime == '') {
            throw new Zivios_Error('Start and End time data missing in request.');
        } else {
            $fromTime = date('h:iA', strtotime($fromTime));
            $toTime   = date('h:iA', strtotime($toTime));
        }

        // variable holds Zivios compatible format for 'time of day'
        $timeOfDay = implode('|', array($weekdays, $fromTime, $toTime));
        
        // Initialize plugin
        $this->setProperty('emssquidblacklist', $blacklist);
        $this->setProperty('emssquidwhitelist', $whitelist);
        $this->setProperty('emssquidtimeofday', $timeOfDay);
    }

	public function addedToGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

	public function removedFromGroup(EMSGroup $group,Zivios_Transaction_Group $tgroup)
	{
		return $tgroup;
	}

    public function generateContextMenu()
    {
        return false;
    }
}
