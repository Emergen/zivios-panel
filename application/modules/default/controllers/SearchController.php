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
 * @package     Zivios
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class SearchController extends Zivios_Controller
{
    protected function _init() {}

    public function preDispatch()
    {
        parent::preDispatch();
    }

    public function indexAction()
    {
        if (null === ($dn = $this->_request->getParam('dn'))) {
            throw new Zivios_Error('Specified entry not found in system.');
        } else {
            $dn = strip_tags(urldecode($dn));
        }

        $this->_helper->layout->disableLayout(true);
        $regexLib = Zivios_Regex::loadLibrary();

        // a small & simple search form.
        $sform = new Zend_Dojo_Form();

        $sform->setName('searchform')
                     ->setElementsBelongTo('search-form')
                     ->setMethod('post')
                     ->setAction('#');

        $sform->addElement('ValidationTextBox', 'searchfield', array(
            'required'          => true,
            'label'             => 'Search: ',
            'maxlength'         => 24,
            'regExp'            => $regexLib->exp->alnumwithspaces,
            'invalidMessage'    => 'Invalid characters in first name field.',
            'filters'           => array('StringTrim'),
            'validators'        => array(
                                       array('Regex', true, array('/'.$regexLib->exp->alnumwithspaces.'/i')),
                                   ),
        ));

         $sform->addElement(
            'FilteringSelect',
            'filter',
            array(
                'label'        => 'Filter',
                'value'        => 'all',
                'autocomplete' => false,
                'multiOptions' => array(
                    'all'            => 'None',
                    'userentry'      => 'System Users',
                    'groupentry'     => 'System Groups',
                    'serverentry'    => 'Servers',
                    'desktopentry'   => 'Desktops',
                    'serviceentry'   => 'Services',
                ),
            )
        );

        $onClick = "zivios.cpaneRefresh('searchrset', 'default/search/runsearch/dn/" . urlencode($dn) . 
            "/s/' + encodeURI(dijit.byId('searchform-searchfield').attr('value')) + '/f/' + " . 
            "encodeURI(dijit.byId('searchform-filter'))); return false;";
        
        $sform->addElement('submitButton', 'submit', array(
            'required'    => false,
            'ignore'      => true,
            'label'       => "Search",
            'onclick'     => $onClick,
        ));

        $this->view->sform = $sform;
    }

    public function runsearchAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null !== ($dn = $this->_request->getParam('dn'))) {
            $dn     = strip_tags(trim(urldecode(($dn))));
            $filter = strip_tags(trim(urldecode($this->_request->getParam('f'))));
            $search = strip_tags(trim(urldecode($this->_request->getParam('s'))));


            if ($search == '') {
                return '';
            }
            
            // Perform search.
            switch ($filter) {
                case "serverentry" :
                    $filter = '(&(objectclass=EMSComputer)(cn=('.$search.'*)'.
                    '(emstype=ServerEntry)(!(objectclass=emsignore)))';
                    break;

                case "desktopentry" :
                    $filter = '(&(objectclass=EMSComputer)(|(cn=('.$search.'*)(uid=('.$search.'*))'.
                    '(emstype=DesktopEntry)(!(objectclass=emsignore)))';
                    break;

                case "groupentry" :
                    $filter = '(&(objectclass=EMSGroup)(cn='.$search.'*)'.
                    '(emstype=GroupEntry)(!(objectclass=emsignore)))';
                    break;

                case "userentry" :
                    $filter = '(&(objectclass=EMSUser)(|(cn=('.$search.'*)(uid=('.$search.'*))'.
                    '(emstype=UserEntry)(!(objectclass=emsignore)))';
                    break;

                case "serviceentry" :
                    $filter = '(&(objectclass=EMSService)(cn=('.$search.'*)'.
                    '(emstype=ServiceEntry)(!(objectclass=emsignore)))';
                   break;
                   
               case "all" :
                    $filter = '(&(objectclass=EMSObject)(|(cn=*'.$search.'*)(uid=*'.$search.'*))'.
                    '(!(objectclass=emsignore)))';
                   break;

                default:
                    // this is problematic currently, hence disabled.
                    //$filter = '(&(objectclass=*)(|(cn='.$search.'*)(uid='.$search.
                    //'*))(!(objectclass=emsignore)))';
                    Zivios_Log::debug('invalid call received by search controller.');
                    return false;
            }

            $searchBase                = Zivios_Ldap_Cache::loadDn($dn);
            $this->view->returnResults = $searchBase->getAllChildren($filter, 'SUB',false, null,'NOMODEL');
            $this->render('resultset');
            return;
        } else {
            return;
        }
    }

    public function forwardtoresultAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        if (null !== ($dn = $this->_request->getParam('dn'))) {
            $dn = strip_tags(trim(urldecode(($dn))));
        } else {
            throw new Zivios_Error('Invalid call to forward action in search controller.');
        }

        $entry = Zivios_Ldap_Cache::loadDn($dn);
        $viewDirector =  Service_Ldap::getViewDirector($entry);

        $uri = $viewDirector['module'] . '/' . $viewDirector['controller'] . '/' .
               $viewDirector['action'] . '/dn/' . urlencode($dn);
        
        $this->_redirect($uri);

    }
}
