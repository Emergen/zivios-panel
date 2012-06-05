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
 * @subpackage  Core
 **/

/**
 * @todo: pretty much a rewrite.
 */

class Zivios_Ldap_Monitor
{
    private $ds;

    public $total_conns;
    public $current_conns;
    public $start_time;
    public $curr_time;
    public $uptime;
    public $data_processed;
    public $total_entries;
    public $read_conns;
    public $write_conns;
    public $total_operations;
    public $total_binds;
    public $total_unbinds;
    public $total_auth_fail;
    public $total_add_ops;
    public $total_delete_ops;
    public $total_modify_ops;
    public $total_search_ops;
    public $total_compare_ops;

    public function __construct(Zivios_Ldap $directory)
    {
        /**
         * Set and populate initial data into
         * defined arrays
         */
        $this->ds = $directory;
        $this->ds->filter = '(objectClass=*)';
        $this->base_dn = 'cn=monitor';
        $this->ds->scope = 'SUB';
    }

    public function refresh_all_data()
    {
        $this->curr_conns = $this->get_connections();
        $this->get_time_stats();
        $this->get_statistics();
        $this->get_waiters();
        $this->get_all_operations();
    }


    protected function get_waiters()
    {
        /**
         * Get information on read and write connections
         */
        $this->ds->return = array('monitorCounter');
        $this->ds->dn = 'cn=Waiters,' . $this->base_dn;
        $tmp_result = $this->ds->search();
        $this->read_conns = $tmp_result[1]['monitorcounter'][0];
        $this->write_conns = $tmp_result[2]['monitorcounter'][0];
    }

    protected function get_connections()
    {
        $this->ds->return = array('monitorCounter');
        $this->ds->dn = 'cn=total,cn=connections,' . $this->base_dn;
        $tmp_result = $this->ds->search();
        $this->total_conns = $tmp_result[0]['monitorcounter'][0];
        $this->ds->dn = 'cn=current,cn=connections,' . $this->base_dn;
        $tmp_result = $this->ds->search();
        $this->current_conns = $tmp_result[0]['monitorcounter'][0];
    }

    protected function get_statistics()
    {
        /**
         * We need:
         *  total num of entries
         *  number of bytes processed
         *  ... add more
         */
        $this->ds->return = array('monitorCounter');
        $this->ds->dn = 'cn=Bytes,cn=Statistics,' . $this->base_dn;
        $tmp_result = $this->ds->search();

        $size = $tmp_result[0]['monitorcounter'][0];

        $i=0;
        $iec = array("B", "Kb", "Mb", "Gb", "Tb");

        while (($size/1024)>1) {
            $size=$size/1024;
            $i++;
        }

        $this->data_processed = round($size,1)." ".$iec[$i];

        $this->ds->dn = 'cn=Entries,cn=Statistics,' . $this->base_dn;
        $tmp_result = $this->ds->search();
        $this->total_entries = $tmp_result[0]['monitorcounter'][0];
    }

    protected function get_all_operations()
    {
        $this->ds->return = array('monitorOpInitiated','monitorOpCompleted');
        $this->ds->dn = 'cn=Operations,' . $this->base_dn;
        $tmp_result = $this->ds->search();
        //echo "<pre>";
        //print_r($tmp_result);
        //echo "</pre>";

        foreach ($tmp_result as $key => $val) {
            /**
             * Ensure we get it the right way rather than the
             * assumed return way
             */
            if (is_array($val)) {
                foreach ($val as $dn_parse) {
                    switch ($dn_parse) {
                        case "cn=Operations,cn=Monitor" :
                        $this->total_operations = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Bind,cn=Operations,cn=Monitor" :
                        $this->total_binds = $val['monitoropinitiated'][0];
                        // the code below is bullshit -- operation is always completed regardless of
                        // auth status. Need to figure out how to get auth failure from cn=monitor
                        $this->total_auth_fail = $val['monitoropinitiated'][0] - $val['monitoropcompleted'][0];
                        break;

                        case "cn=Unbind,cn=Operations,cn=Monitor" :
                        $this->total_unbinds = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Search,cn=Operations,cn=Monitor" :
                        $this->total_search_ops = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Compare,cn=Operations,cn=Monitor" :
                        $this->total_compare_ops = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Add,cn=Operations,cn=Monitor" :
                        $this->total_add_ops = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Delete,cn=Operations,cn=Monitor" :
                        $this->total_delete_ops = $val['monitoropinitiated'][0];
                        break;

                        case "cn=Modify,cn=Operations,cn=Monitor" :
                        $this->total_modify_ops = $val['monitoropinitiated'][0];
                        break;
                    }
                }
            }
        }
    }

    protected function get_connection_details()
    {
        /**
         * Get stats on a particular connection
         */
    }

    protected function get_time_stats()
    {
        /**
         * Query and set time params
         */
        $this->ds->return = array('monitorTimestamp');
        $this->ds->dn = 'cn=time,cn=monitor';
        $this->result = $this->ds->search();

        $tmp['stimestamp'] = $this->result[1]['monitortimestamp'][0];
        $tmp['ctimestamp'] = $this->result[2]['monitortimestamp'][0];
        $this->start_time =  $this->format_timestamp($tmp['stimestamp']);
        $this->curr_time = $this->format_timestamp($tmp['ctimestamp']);

        /**
         * get unix timestamps
         */
        $tmp['st_unix'] = $this->format_timestamp_unixtime($this->result[1]['monitortimestamp'][0]);
        $tmp['cr_unix'] = $this->format_timestamp_unixtime($this->result[2]['monitortimestamp'][0]);

        /**
         * Calculate Uptime
         */
        $this->uptime = $this->calculate_uptime($tmp['st_unix'],$tmp['cr_unix']);
    }

    function calculate_uptime($beg, $end)
    {
        /**
         * days are currently displayed without consideration of "weeks"
         * the display, though calculated correctly, misrepresents the intent.
         */

        $seconds = $end - $beg;
        $tmp = $seconds / 86400;

        if(ereg('\.', $tmp)) {
            $tmp = split('\.', $tmp);
            $uptime['days'] = $tmp[0];
        } else
            $uptime['days'] = $tmp;

        $uptime['weeks'] = floor($uptime['days'] / 7);
        $uptime['years'] = floor($uptime['weeks'] / 52);

        $tmp = $seconds / 3600;

        if(ereg('\.', $tmp)) {
            $tmp = split('\.', $tmp);
            $uptime['hour'] = $tmp[0];
        } else
            $uptime['hour'] = $tmp;

        $uptime['hour'] = $uptime['hour'] - ($uptime['days'] * 24);

        $uptime['min'] = round( ($seconds - (($uptime['days'] * 86400) + ($uptime['hour'] * 3600))) / 60);

        if($uptime['min'] < 0) {
            $uptime['min']--;
            $uptime['min'] = 60 + $uptime['min'];
        }

        $uptime['sec'] = $seconds - (($uptime['days'] * 86400) + ($uptime['hour'] * 3600) + ($uptime['min'] * 60));

        if($uptime['sec'] < 0) {
            $uptime['min']--;
            $uptime['sec']--;
            $uptime['sec'] = 60 + $uptime['sec'];
        }

        $uptime_string['years'] = ($uptime['years']) ? $uptime['years']." years, " : "";
        $uptime_string['weeks'] = ($uptime['weeks']) ? $uptime['weeks']." weeks, " : "";
        $uptime_string['days'] = ($uptime['days'])  ? $uptime['days']." days, "   : "";
        $uptime_string['hours'] = ($uptime['hour'])  ? $uptime['hour']." hours, " : "";
        $uptime_string['minutes'] = ($uptime['min'])   ? $uptime['min']." minutes, " : "";
        $uptime_string['seconds'] = ($uptime['sec'])   ? $uptime['sec']." seconds." : "";
        $string  = $uptime_string['years'].$uptime_string['weeks'].$uptime_string['days'];
        $string .= $uptime_string['hours'].$uptime_string['minutes'].$uptime_string['seconds']."\n";

        return($string);
    }

    private function format_timestamp_unixtime($date) {
        $date = preg_replace('/Z$/', '', $date);

        $year   = $date[0].$date[1].$date[2].$date[3];
        $month  = $date[4].$date[5];
        $day    = $date[6].$date[7];

        if(isset($date[8]) and isset($date[9]))
            $hour   = $date[8].$date[9];
        else
            $hour   = "00";

        if (isset($date[10]) and isset($date[11]))
            $minute = $date[10].$date[11];
        else
            $minute = "00";

        if(isset($date[12]) and isset($date[13]))
            $second = $date[12].$date[13];
        else
            $second = "00";

        return(gmmktime($hour, $minute, $second, $month, $day, $year));
}

    private function format_timestamp($timestamp) {
        $date   = date("F j, Y, g:i a", $this->format_timestamp_unixtime($timestamp));
        return($date);
    }

    protected function get_loaded_modules()
    {
    }

    protected function get_loaded_overlays()
    {
    }


}
