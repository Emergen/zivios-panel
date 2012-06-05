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

class AuthController extends Zend_Controller_Action
{
    protected $_model, $form;
    
    public function preDispatch()
    {}

    public function indexAction()
    {}

    public function dologinAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();
        
        if (!Zivios_Util::isFormPost('login')) {
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_MISSING'));
        }

        $formData = new Zivios_ValidateForm($_POST['login']);

        if ($formData->err !== false) {
            if (null === ($appendData = $formData->errMsg)) {
                $appendData = false;
            }
            
            throw new Zivios_Error(Zivios_Errorlib::errorCode('FORM_DATA_INVALID', $appendData));
        }
        
        $authenticate = $this->getModel();
        $session = Zend_Registry::get('userSession');
        $authfail = false;


        if (!$authenticate->authUser($formData->cleanValues['login'], $formData->cleanValues['pass'])) {
            $authfail = true;
            $engine = $authenticate->engine;
            $callbacks   = array();
            if (isset($engine->ppemsg) && $engine->ppemsg == 'Password expired')
            {
                $info = 'Your Password has <b>EXPIRED</b> and you didnt change it during the grace period. Your account has been locked as per policy. Please contact your system administrator';
            }
            else if (isset($engine->pperror)) {
                $info = 'Account Policy Error: '.$engine->ppemsg.' <br> Error code : '.$engine->ppemsg;
            } 
            
            else {
                throw new Zivios_Error("Authentication Failed");
            }
                   
        } else { 
            $engine = $authenticate->engine;

            // Redirect user to base url.
            $callbacks   = array();
            
            if (isset($engine->pperror) || ($engine->ppexpire > 0) || isset($engine->pwdmustchange)) { 
                if (isset($engine->pperror)) {
                    if ($engine->pperror == 2) {
                        $pwdmustchange = true;
                    }
                    $info = 'Account Policy Error: <b>'.$engine->ppemsg.'</b> <br> Error code : <b>'.$engine->pperror.'</b><br> Please contact your system administrator';
                } else if ($engine->ppexpire > 0) {
                    $exp = $engine->ppexpire / 86400;
                    $units = 'days';
                    if ($exp < 1 ) {
                        $exp = $engine->ppexpire / 3600;
                        $units = 'hours';
                    }
                    
                    if ($exp < 1) {
                        $exp = $engine->ppexpire / 60;
                        $units = 'minutes';
                    }
                    $exp = round($exp);
                    $info = 'Expiration Warning: Your password is set to expire in <b>'.$exp.' '.$units.'</b>. Change it as soon as possible';
                }
                
                if ($engine->ppgrace > 0)
                    $pwdmustchange = true;
                
                if ($engine->pwdmustchange) {
                    $pwdmustchange = true; 
                }
            }
        }
            
            
            
            
            if ($engine->pwexpired || $engine->ppgrace > 0){
                $session->pwexpired = 1;
                
            } 
            
            if (!$authfail) {
                $callbacks[] = array('func' => 'zivios.redirectUrl', 'args' => array(
                    'https://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].'/'.WEB_ROOT
                    )
                );
            }
        
            
        if (isset($info) && $info != '') {
            $callbacks[] = array('func' => 'zivios.createInfoDialog','args' => array (
                $info,'Important Account Information'));
        }
           

        $content = Zend_Json::encode(array('callbacks' => $callbacks));
        $this->_response->appendBody($content);
    }

    public function getModel()
    {
        if (null === $this->_model) {
            $this->_model = new Authentication();
        }

        return $this->_model;
    }

    public function getForm($form)
    {
        return $this->getModel()->getForm($form);
    }

    public function redirecttologinAction()
    {
        $this->_helper->layout->disableLayout(true);
    }
}

