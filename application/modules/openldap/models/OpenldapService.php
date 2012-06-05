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
 * @package     mod_openldap
 * @copyright   Copyright (c) 2008-2010 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

class OpenldapService extends EMSService
{
    public    $mastercomp;
    protected $_module = 'openldap', $_regexLib, $_targetClusterConfig, $_replicaServer, $_replicaServerData = array(),
              $_ldapConfig, $_replicaList;
    private   $_tmpReplicaFolder = null;

    public function __construct($dn=null,$attrs=null)
    {
        if ($attrs == null) {
            $attrs = array();
        }

        $attrs[] = 'emsldapserver';
        $attrs[] = 'emsmastercomputerdn';
        $attrs[] = 'emsldapserverreplicas';
        $attrs[] = 'emsdependson';

        parent::__construct($dn,$attrs);
    }

    public function init()
    {
        parent::init();

        if (null !== ($mcdn = $this->getProperty('emsmastercomputerdn'))) {
            $this->mastercomp = Zivios_Ldap_Cache::loadDn($mcdn);
        }
    }

    /**
     * Get a listing of replicas with the array index (sorted) by rid. Values are returned
     * as a multi-dimensional array. 
     *
     * If no replicas are found, an empty array is returned.
     *
     * @return array $replicas
     */
    public function getReplicas()
    {
        $replicaListing = array();
        if (null !== ($replicas = $this->getProperty('emsldapserverreplicas'))) {
            if (!is_array($replicas)) {
                $replicas = array($replicas);
            }
            
            foreach ($replicas as $replica) {
                $replicaDetails = explode('_', $replica);
                $replicaListing[$replicaDetails[0]] = array($replicaDetails[1], $replicaDetails[2]);
            }

            ksort($replicaListing);
        }

        return $replicaListing;
    }

    /**
     * Get a DN listing of possible replica candidate computers
     *
     * @return array $compatSystems
     */
    public function getReplicaCandidates($parentDn)
    {
        $replicas = $this->getReplicas();
        $parent   = Zivios_Ldap_Cache::loadDn($parentDn);
        $comps    = $this->getCompatibleComputers($parent);

        $compatSystems = array();

        // get replicaDns in a separate array
        $replicaDns = array();
        if (!empty($replicas)) {
            foreach ($replicas as $replicaData) {
                $service = Zivios_Ldap_Cache::loadDn($replicaData[1]);
                $replicaDns[] = $service->getProperty('emsmastercomputerdn');
                unset($service);
            }
        }

        foreach ($comps as $computer) {
            if ($computer->getProperty('cn') != $this->getProperty('emsldapserver')) {
                if (!empty($replicaDns)) {
                    foreach ($replicas as $riddata) {
                        if (!in_array($computer->getdn(), $replicaDns)) {
                            $compatSystems[$computer->getdn()] = $computer->getProperty('cn');
                        }
                    }
                } else {
                    $compatSystems[$computer->getdn()] = $computer->getProperty('cn');
                }
            }
        }
        
        return $compatSystems;
    }

    public function getReplicaCompatibleForDisplay()
    {
        $this->_iniClusterConfig();
        $dispCompatible = explode(',', $this->_clusterCfg->supported);
        
        $compatComps = array();
        foreach ($dispCompatible as $compatible) {
            $distDetails = explode ('-', $compatible);
            $compatComps[] = ucfirst(strtolower($distDetails[0])) . ' ' . ucfirst(strtolower($distDetails[1]));
        }

        return $compatComps;
    }

    public function updateLogLevel($data, $tgroup)
    {
        if (empty($data) || isset($data['off'])) {
            // empty dataset or all debugging off request.
            Zivios_Log::info('Turning off OpenLDAP debug log.');
            $logLevel = array('0');
        } else {
            Zivios_Log::info('Request to adjust OpenLDAP log level.');
            $logLevel = array();
            foreach ($data as $loglevel => $setting) {
                $logLevel[] = $loglevel;
            }
        }

        $cnConfig = new cnConfig();
        $cnConfig->init();

        $cnConfig->setProperty('olcloglevel', $logLevel);
        $cnConfig->update($tgroup);
    }

    public function getLogLevel()
    {
        $cnConfig = new cnConfig();
        $cnConfig->init();
        $loglevel =  $cnConfig->getProperty('olcloglevel');
        
        if (!is_array($loglevel)) {
            $loglevel = array($loglevel);
        }
        
        if (in_array('0', $loglevel)) {
            $loglevel = array('off');
        }

        return $loglevel;
    }

    public function getPrimaryDbIndexes()
    {
        $dn = 'olcDatabase={1}hdb,cn=config';
        $cnConfig = new cnConfig($dn);
        $cnConfig->init();

        $indexes = $cnConfig->getProperty('olcdbindex');
        
        if (!is_array($indexes)) {
            $indexes = array($indexes);
        }

        return $indexes;
    }

    public function refreshPrimaryDbIndexes($data, $tgroup)
    {
        $dn = 'olcDatabase={1}hdb,cn=config';
        $cnConfig = new cnConfig($dn);
        $cnConfig->init();

        $indexes = array();
        
        if (!empty($data)) {
            $c = 0;
            foreach ($data as $index => $values) {
                $indexes[$c] = $index . ' ';

                foreach ($values as $key => $value) {
                    $indexes[$c] .= $key . ',';
                }

                $indexes[$c] = rtrim($indexes[$c], ',');
                $c++;
            }
        }

        $cnConfig->setProperty('olcdbindex', $indexes);
        $cnConfig->update($tgroup);
    }

    public function addPrimaryDbIndex($data, $tgroup)
    {
        $dn = 'olcDatabase={1}hdb,cn=config';
        $cnConfig = new cnConfig($dn);
        $cnConfig->init();

        $index = '';
        $c = 0;
        foreach ($data as $key => $value) {

            if ($c == 0) {
                $index = $value . ' ';
            } else {
                $index .= $key . ',';
            }
            $c++;
        }

        $index = rtrim($index, ',');
        $param = $cnConfig->getParameter('olcdbindex');
        $param->addValue($index);
        $cnConfig->update($tgroup);
    }

    public function registerReplica($data, Zivios_Transaction_Group $tgroup, $description=null)
    {
        if ($description == null) {
            $description = 'Registering replica with Zivios Directory service';
        }

        $replicaEntry = $data['rid'] . '_' . $data['cn'] . '_' . $data['dn'];
        $param = $this->getParameter('emsldapserverreplicas');
        $param->addValue($replicaEntry);
        
        $this->update($tgroup, $description);
    }
    
    /**
     * Copy over schema files and initialize the _tmpReplicaFolder variable
     *
     * @return string
     */
    public function initializeSchemaPack($folderName)
    {
        Zivios_Log::debug('Initializing schema copy for replica host.');
        $this->_initCommAgent();

        return $this->_commAgent->copySchemaToTmp($folderName);
    }
}

