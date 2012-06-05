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
 * @package		ZiviosInstaller
 * @copyright	Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license		http://www.zivios.org/legal/license
 **/

class Zend_View_Helper_AboutZivios extends Zend_View_Helper_Abstract
{
    public function AboutZivios()
    {
        $model = new Installer();
        $info  = $model->getZiviosInfo();

        $iniDisplay  = '<div class="heading">' . $info['appname'] . '</div>';
        $iniDisplay .= '<div class="content">Version: ' . $info['version'] . '<br />';
        $iniDisplay .= 'Supported Linux Systems: ';
        $iniDisplay .= '<ul>';

        foreach ($info['supportedOsTags'] as $osSupport) {
            $iniDisplay .= '<li/>'.$osSupport;
        }

        $iniDisplay .= '</ul>';
        $iniDisplay .= '</div>';
        
        return $iniDisplay;

    }
}
