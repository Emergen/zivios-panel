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

class Ca_ComputerController extends Zivios_Controller_Computer
{
    private $caplugin = null;
    
    protected function _init()
    {
    }

    public function dashboardAction()
    {
        $this->_helper->layout->disableLayout(true);
        $this->_helper->viewRenderer->setNoRender();

        echo "<div class='info'> This is the CA Dashboard and would allow for manual configuration and download "
        ." of computer certificates </div>";
        
        
    }

    public function dashviewAction()
    {
        $this->view->dashboardData = $this->caplugin->getDashboardData();
        $this->render();
    }

    public function certManageAction()
    {
        $this->render();
    }
}
