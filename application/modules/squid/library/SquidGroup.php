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

class SquidGroup extends Zivios_Plugin_Group
{
    protected $_module = 'squid';

    public function __construct()
    {
        parent::__construct();
    }

    public function getAttrs()
    {
        $attrs = parent::getAttrs();
        $attrs[] = 'emssquidactive';
        $attrs[] = 'emssquidblacklist';
        $attrs[] = 'emssquidwhitelist';
        $attrs[] = 'emssquidtimeofday';
        $attrs[] = 'emssquidenablemembers';
        return $attrs;
    }

    /**
     * Subscribe group entry to squid group plugin
     * 
     * @param Zivios_Transaction_Group $tgroup
     * @param string $description
     * @return Zivios_Transaction_Group $tgroup
     */
    public function add(Zivios_Transaction_Group $tgroup, $description=null)
    {
        $this->setProperty('emssquidenablemembers', 'N');
        $this->addObjectClass('emssquidgroup');
        $this->enablePlugin();

        $tgroup = parent::add($tgroup, $description);
        
        return $tgroup;
    }

    /**
     * Remove squid plugin from group.
     *
     * @param Zivios_Transaction_Group $tgroup
     * @param string $description
     * @return Zivios_Transaction_Group $tgroup
     * @return string $description
     */
    public function delete(Zivios_Transaction_Group $tgroup, $decription=null)
    {
        $this->removeProperty('emssquidactive');
        $this->removeProperty('emssquidblacklist');
        $this->removeProperty('emssquidwhitelist');
        $this->removeProperty('emssquidtimeofday');
        $this->removeProperty('emssquidenablemembers');
        $this->removeObjectClass('emssquidgroup');

        return parent::delete($tgroup, $decription);
    }

    /**
     * Enables the squid group plugin
     */
    public function enablePlugin()
    {
        $this->setProperty('emssquidactive','Y');
    }
    
    /**
     * Disables the squid group plugin
     */
    public function disablePlugin()
    {
        $this->setProperty('emssquidactive','N');
    }
    
    /** 
     * Returns all group members who currently subscribe to the squid user plugin
     *
     * @return array $activeUsers
     */
    public function getActiveMembers()
    {
        $users = $this->_groupobj->getAllusers();
        $activeUsers = array();

        if (is_array($users) && !empty($users)) {
            foreach ($users as $user) {
                if ($user->hasPlugin('SquidUser')) {
                    $activeUsers[] = $user;
                }
            }
        }

        return $activeUsers;
    }
    
    /**
     * Enables the squid user plugin for all group members who do not currently subscribe 
     * to the user plugin.
     * 
     * @param Zivios_Transaction_Handler $handler
     * @param string $description
     * @return void
     */
    public function activateGroupMembers(Zivios_Transaction_Handler $handler)
    {
        $users = $this->_groupobj->getAllUsers();
        if (is_array($users) && !empty($users)) {
            foreach ($users as $user) {
                if (!$user->hasPlugin('SquidUser')) {
                    $enabled = true;
                    $squidPlugin = $user->newPlugin('SquidUser');
                    $squidPlugin->setGroup($this->_groupobj);
                    $tgroup = $handler->newGroup('Adding squid plugin for user '.
                        $user->getdn(), Zivios_Transaction_Group::EM_SEQUENTIAL);
                    $user->addPlugin($squidPlugin, $tgroup);
                    $tgroup->commit();
                    unset($tgroup);
                }
            }
                
            // Set emssquidenablemembers attr to Y if any members were subscribed
            // to the plugin.
            if (isset($enabled) && $enabled == true) {
                $this->setProperty('emssquidenablemembers', 'Y');
                $tgroup = $handler->newGroup('Updating Parent Squid Group '.$this->dn,
                    Zivios_Transaction_Group::EM_SEQUENTIAL);
                $tgroup = parent::update($tgroup, $description);
                $tgroup->commit();
            }
        } else {
            Zivios_Log::debug('No users subscribe to group. Squid plugin activation'
                . ' for users ignored.');
        }
    }
    
    /**
     * Disables all group member plugins (user plugins) where active.
     *
     * @param Zivios_Transaction_Group $handler
     * @param string $description
     * @return void
     */
    public function deactivateGroupmembers(Zivios_Transaction_Handler $handler)
    {
        if ($this->getProperty('emssquidenablemembers') == 'Y') {
            $users = $this->_groupobj->getAllUsers();
            if (is_array($users) && !empty($users)) {
                foreach ($users as $user) {
                    if ($user->hasPlugin('SquidUser')) {
                        $disabled = true;
                        $tgroup = $handler->newGroup('Removing squid plugin for user: ' .
                            $user->getdn(), Zivios_Transaction_Group::EM_SEQUENTIAL);
                        $user->removePlugin('SquidUser', $tgroup);
                        $tgroup->commit();
                    }
                    
                    if (isset($disabled) && $disabled == true) {
                        
                        $tgroup = $handler->newGroup('Updating Parent Squid Group '.$this->dn,
                        Zivios_Transaction_Group::EM_SEQUENTIAL);
                        $this->setProperty('emssquidenablemembers', 'N');
                        $tgroup = parent::update($tgroup, $description);
                        $tgroup->commit();
                    }
                }
            } else {
                Zivios_Log::debug('No users subscribe to group. Squid plugin deactivation'
                    . ' for users ignored.');
            }
        } else {
            throw new Zivios_Exception('Squid group members not activated');
        }
    }

    /**
     * Generate squid "add plugin" form for group entries.
     *
     * @param string $srvdn (service dn)
     * @param string $dn (group dn)
     * @return Zend_Dojo_Form $form
     */
    public function getAddPluginForm($srvdn, $dn)
    {
        $form = new Zend_Dojo_Form();
        $form->setName('addsquidgroupform')
             ->setElementsBelongTo('addsquidgroupform')
             ->setMethod('post')
             ->setAction('#');
        
        $configForm = $this->getGroupConfigForm($dn, true);

        $form->addSubForm($configForm, 'groupconfigform');
        
        // Add group dn
        $hfdn = new Zend_Form_Element_Hidden('dn');
        $hfdn->setValue(urlencode($dn))
             ->removeDecorator('label')
             ->removeDecorator('HtmlTag');
        $form->addElement($hfdn);

        // Add service dn
        $hfsrvdn = new Zend_Form_Element_Hidden('srvdn');
        $hfsrvdn->setValue(urlencode($srvdn))
                ->removeDecorator('label')
                ->removeDecorator('HtmlTag');
        $form->addElement($hfsrvdn);

        $form->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'        => 'Activate Squid Plugin',
            'onclick'     => "zivios.formXhrPost('addsquidgroupform','squid/group/dolinktoservice'); return false;",
        ));

        // Add required hidden fields and submit button.
        return $form;
    }

    /**
     * Prepare the group object for the squid plugin.
     *
     * @params array $data
     * @return void
     */
    public function setAddPluginForm($data)
    {
        if (!is_array($data) || empty($data)) {
            Zivios_Log::debug('Empty or non-array passed to prepAdd call.');
            return;
        }
        
        if (!isset($data['groupconfigform'])) {
            Zivios_Log::error('Missing form: "groupconfigform" in data array.');
            throw new Zivios_Error('Required data missing from request. Please check system logs.');
        }
        
        // Set config data.
        $this->setGroupConfigForm($data['groupconfigform']);
    }

    public function getGroupConfigForm($dn=null, $isNew=false)
    {
        if ($isNew == true) {

            if ($dn == null) {
                throw new Zivios_Exception('getGroupConfigForm for new entries requires '.
                    ' the group DN to be passed');
            }

            $whitelist = '';
            $blacklist = '';
            $startTime = 'T08:00:00';
            $endTime   = 'T17:30:00';
            $daysOfWeek = array();

        } else {
            // Get required properties and format for configuration form.
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
            'name'   => 'groupconfigform',
            'dijitParams' => array(
                'title' => 'Squid Group Config Form',
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
        
        // Give the option of enabling existing group members with the squid plugin.
        // This option is only available when the plugin is being activated.

        /*
        if ($isNew == true) {
            $form->addElement('Checkbox', 'actmem', array(
                'description'       => 'Option enables Squid User plugin for all group members.',
                'required'          => false,
                'label'             => 'Activate Members: ',
                'checkedValue'      => '1',
                'uncheckedValue'    => '0',
                'checked'           => false,
            ));
            $form->getElement('actmem')->getDecorator('description')->setOptions(
                array(
                    'placement' => 'prepend', 
                    'class'     => 'form descfrm',
            ));
        }
        */

        return $form;
    }    

    public function setGroupConfigForm($data)
    {
        // Validate form data and prepare array for prepAdd call.
        $whitelist = '';
        $blacklist = '';
        if (isset($data['emssquidwhitelist']) && trim($data['emssquidwhitelist']) != '') {
            // We perform no validation of this data -- we simply clean it.
            $whitelist = explode("\n", strip_tags($data['emssquidwhitelist']));
        }

        if (isset($data['emssquidblacklist']) && trim($data['emssquidblacklist']) != '') {
            $blacklist = explode("\n", strip_tags($data['emssquidblacklist']));
        }

        if ($whitelist != '' && $blacklist != '') {
            throw new Zivios_Error('A white list and black list cannot be defined together. Please read'.
                                   ' the relevant documentation before proceeding.');
        }

        if (!isset($data['weekdays']) || !is_array($data['weekdays']) || empty($data['weekdays'])) {
            throw new Zivios_Error('No week days selected.');
        } else {
            $weekdays = implode('',$data['weekdays']);
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
       
        // Check if group member plugin for squid should be activated.
        /*
        if (isset($data['actmem']) && $data['actmem'] == 1) {
            $this->setProperty('emssquidenablemembers', 'Y');
        } else {
            Zivios_Log::debug('Activation of members not requested.');
        }
        */
    }

    public function generateContextMenu()
    {
        return false;
    }
}

