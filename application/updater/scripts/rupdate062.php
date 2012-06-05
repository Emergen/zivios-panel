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

// ppolicy schema.
$ppSchema = array();
$ppSchema['cn'] = 'ppolicy';
$ppSchema['objectClass'] = 'olcSchemaConfig';
$ppSchema['olcAttributeTypes'] = array(
    "{0} ( 1.3.6.1.4.1.42.2.27.8.1.1 NAME 'pwdAttribute' EQUALITY objectIdentifierMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.38 )",
    "{1} ( 1.3.6.1.4.1.42.2.27.8.1.2 NAME 'pwdMinAge' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{2} ( 1.3.6.1.4.1.42.2.27.8.1.3 NAME 'pwdMaxAge' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{3} ( 1.3.6.1.4.1.42.2.27.8.1.4 NAME 'pwdInHistory' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{4} ( 1.3.6.1.4.1.42.2.27.8.1.5 NAME 'pwdCheckQuality' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{5} ( 1.3.6.1.4.1.42.2.27.8.1.6 NAME 'pwdMinLength' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{6} ( 1.3.6.1.4.1.42.2.27.8.1.7 NAME 'pwdExpireWarning' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{7} ( 1.3.6.1.4.1.42.2.27.8.1.8 NAME 'pwdGraceAuthNLimit' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{8} ( 1.3.6.1.4.1.42.2.27.8.1.9 NAME 'pwdLockout' EQUALITY booleanMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 SINGLE-VALUE )",
    "{9} ( 1.3.6.1.4.1.42.2.27.8.1.10 NAME 'pwdLockoutDuration' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{10} ( 1.3.6.1.4.1.42.2.27.8.1.11 NAME 'pwdMaxFailure' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{11} ( 1.3.6.1.4.1.42.2.27.8.1.12 NAME 'pwdFailureCountInterval' EQUALITY integerMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.27 SINGLE-VALUE )",
    "{12} ( 1.3.6.1.4.1.42.2.27.8.1.13 NAME 'pwdMustChange' EQUALITY booleanMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 SINGLE-VALUE )",
    "{13} ( 1.3.6.1.4.1.42.2.27.8.1.14 NAME 'pwdAllowUserChange' EQUALITY booleanMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 SINGLE-VALUE )",
    "{14} ( 1.3.6.1.4.1.42.2.27.8.1.15 NAME 'pwdSafeModify' EQUALITY booleanMatch SYNTAX 1.3.6.1.4.1.1466.115.121.1.7 SINGLE-VALUE )",
    "{15} ( 1.3.6.1.4.1.4754.1.99.1 NAME 'pwdCheckModule' DESC 'Loadable module that instantiates \"check_password() function' EQUALITY caseExactIA5Match SYNTAX 1.3.6.1.4.1.1466.115.121.1.26 SINGLE-VALUE )"
);

$ppSchema['olcObjectClasses'] = array(
    "{0} ( 1.3.6.1.4.1.4754.2.99.1 NAME 'pwdPolicyChecker' SUP top AUXILIARY MAY pwdCheckModule )",
    "{1} ( 1.3.6.1.4.1.42.2.27.8.2.1 NAME 'pwdPolicy' SUP top AUXILIARY MUST pwdAttribute MAY ( ".
        "pwdMinAge $ pwdMaxAge $ pwdInHistory $ pwdCheckQuality $ pwdMinLength $ pwdExpireWarning ".
        "$ pwdGraceAuthNLimit $ pwdLockout $ pwdLockoutDuration $ pwdMaxFailure $ pwdFailureCountInterval ".
        "$ pwdMustChange $ pwdAllowUserChange $ pwdSafeModify ) )"
);

// adding emsaccountlockout to zivios-core
$zcAddAttrToSchema = array();
$zcAddAttrToSchema['olcAttributeTypes'] = "( 1.3.6.1.4.1.31267.1.1.38 NAME 'emsaccountlockout' DESC 'EMS ".
    "Global Account Lockout' SUP name)";

// replace zivios-core schema
$zcReplaceAttrInSchema = array();
$zcReplaceAttrInSchema['olcObjectClasses'] = array(
    "{0} ( 1.3.6.1.4.1.31267.1.2.1 NAME 'ZvLdapObject' DESC 'EMS Ldap Object' SUP top AUXILIARY MAY (emspermission $ emsdependson ))",
    "{1} ( 1.3.6.1.4.1.31267.1.2.2 NAME 'ZvCacheObject' DESC 'EMS Cacheable Object' SUP ZvLdapObject AUXILIARY )",
    "{2} ( 1.3.6.1.4.1.31267.1.2.3 NAME 'emsSecurityObject' DESC 'EMS Security Object' SUP zvCacheObject AUXILIARY MAY emspermission )",
    "{3} ( 1.3.6.1.4.1.31267.1.2.4 NAME 'emsObject' DESC 'EMS Object' SUP zvCacheObject AUXILIARY MUST ( emsdescription $ cn ) MAY ( emsmodelclass $ emstype $ emsmodulename ) )",
    "{4} ( 1.3.6.1.4.1.31267.1.2.5 NAME 'emsOrganizationalUnit' DESC 'EMS Organizational Unit' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ ou $ cn $ emscontainertype ) )",
    "{5} ( 1.3.6.1.4.1.31267.1.2.6 NAME 'emsGroup' DESC 'EMS Group' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) MAY ( emsplugins $ emsservicemap $ emsmodules ) )",
    "{6} ( 1.3.6.1.4.1.31267.1.2.7 NAME 'emsUser' DESC 'EMS User 'SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn $ emsprimarygroupdn ) MAY ( emsplugins $ emsmodules $ emsaccountlockout ) )",
    "{7} ( 1.3.6.1.4.1.31267.1.2.8 NAME 'emsComputer' DESC 'EMSComputer' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) MAY ( emsmodules $ emsservicemap $ emsplugins $ emscomputervendormodel $ emscomputercpumhz $ emscomputercpucount $ emscomputersystem $ emscomputerram $ emscomputerswap $ emscomputerdistro $ emscomputerdistrorelease $ sn $ uid $ emscomputerarch $ emsdistrodesc $ emsdistrocodename $ emssubchannels) )",
    "{8} ( 1.3.6.1.4.1.31267.1.2.9 NAME 'emsKerberos' DESC 'EMS Kerberos' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{9} ( 1.3.6.1.4.1.31267.1.2.10 NAME 'emsHost' DESC 'EMS DNS Host Entry' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{10} ( 1.3.6.1.4.1.31267.1.2.11 NAME 'emsLocality' DESC 'EMS Locality Entry' SUP emsObject AUXILIARY )",
    "{11} ( 1.3.6.1.4.1.31267.1.2.12 NAME 'emsUserContainer' DESC 'EMS User Container' SUP emsOrganizationalUnit AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{12} ( 1.3.6.1.4.1.31267.1.2.13 NAME 'emsGroupContainer' DESC 'EMS Group' SUP emsOrganizationalUnit AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{13} ( 1.3.6.1.4.1.31267.1.2.14 NAME 'emsComputerContainer' DESC 'EMS Group' SUP emsOrganizationalUnit AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{14} ( 1.3.6.1.4.1.31267.1.2.15 NAME 'emsKrbContainer' DESC 'EMS Group' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{15} ( 1.3.6.1.4.1.31267.1.2.16 NAME 'emsDNSContainer' DESC 'EMS Group' SUP emsObject AUXILIARY MUST ( emsdescription $ emstype $ cn ) )",
    "{16} ( 1.3.6.1.4.1.31267.1.2.17 NAME 'emsIgnore' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{17} ( 1.3.6.1.4.1.31267.1.2.18 NAME 'emsDnsZone' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY MUST (emsdnszonetype) )",
    "{18} ( 1.3.6.1.4.1.31267.1.2.19 NAME 'emsDnsHostName' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{19} ( 1.3.6.1.4.1.31267.1.2.20 NAME 'emsDnsRecord' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{20} ( 1.3.6.1.4.1.31267.1.2.21 NAME 'emsDhcpServer' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{21} ( 1.3.6.1.4.1.31267.1.2.22 NAME 'emsDhcpService' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{22} ( 1.3.6.1.4.1.31267.1.2.23 NAME 'emsDhcpSharedNetwork' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{23} ( 1.3.6.1.4.1.31267.1.2.24 NAME 'emsDhcpSubnet' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{24} ( 1.3.6.1.4.1.31267.1.2.25 NAME 'emsDhcpPool' DESC 'EMS Ignore Object' SUP emsObject AUXILIARY )",
    "{25} ( 1.3.6.1.4.1.31267.1.2.26 NAME 'emsDhcpContainer' DESC 'EMS Dhcp Container' SUP emsOrganizationalUnit AUXILIARY )",
    "{26} ( 1.3.6.1.4.1.31267.1.2.27 NAME 'emsOlcDatabase' DESC 'EMS Exten Entry Object' SUP emsObject AUXILIARY )",
    "{27} ( 1.3.6.1.4.1.31267.1.2.28 NAME 'emsCnConfig' DESC 'EMS Cn Config Object' SUP emsObject AUXILIARY )",
    "{28} ( 1.3.6.1.4.1.31267.1.2.29 NAME 'emsOlcSchema' DESC 'EMS Schema Entry Object' SUP emsObject AUXILIARY )",
    "{29} ( 1.3.6.1.4.1.31267.1.2.30 NAME 'emsOlcSchemaConfig' DESC 'EMS Schema Collection Entry' SUP emsObject AUXILIARY )",
    "{30} ( 1.3.6.1.4.1.31267.1.2.31 NAME 'emsservice' DESC 'EMS Service' SUP emsObject AUXILIARY MUST emsmodulename MAY ( emsmastercomputerdn $ emscomputersdn ) )",
    "{31} ( 1.3.6.1.4.1.31267.1.2.32 NAME 'emsdnsservice' DESC 'EMS DNS Service' SUP emsservice AUXILIARY MUST ( emsdnsmastercomputerdn $ emsdnsreplicationmode $ emsdnsrootzone ) MAY emsdnsslavecomputerdns )",
    "{32} ( 1.3.6.1.4.1.31267.1.2.33 NAME 'emsntpservice' DESC 'EMS NTP Service' SUP emsservice AUXILIARY MUST emsntpserver MAY ( emssubnetbroadcast $ emsstatisticsenable $ emsstatistics ) )",
    "{33} ( 1.3.6.1.4.1.31267.1.2.34 NAME 'emsldapservice' DESC 'EMS LDAP Service' SUP emsservice AUXILIARY MUST emsldapserver )",
    "{34} ( 1.3.6.1.4.1.31267.1.2.35 NAME 'emskerberosservice' DESC 'EMS Kerberos Service' SUP emsservice AUXILIARY MUST emskrbserver )",
    "{35} ( 1.3.6.1.4.1.31267.1.2.36 NAME 'emsplugin' DESC 'EMS Plugin' SUP ZvCacheObject AUXILIARY )",
    "{36} ( 1.3.6.1.4.1.31267.1.2.37 NAME 'emscaservice' DESC 'EMS CA Service' SUP emsservice AUXILIARY MUST emscaserver )",
    "{37} ( 1.3.6.1.4.1.31267.1.2.38 NAME 'emscomputergroup' DESC 'EMS Computer Group' SUP emsobject AUXILIARY MAY (member $ emssubchannels $ emsmodules $ emsplugins ) )"
);

// Squid schema updates
$sqReplaceAttrInSchema = array();
$sqReplaceAttrInSchema['olcAttributeTypes'] = array(
    "{0} ( 1.3.6.1.4.1.31267.1.3.1.4.1 NAME 'emssquidblacklist' DESC 'Squid Black List' SUP name)",
    "{1} ( 1.3.6.1.4.1.31267.1.3.1.4.2 NAME 'emssquiddelaypool' DESC 'Squid Delay Pool' SUP emsdescription )",
    "{2} ( 1.3.6.1.4.1.31267.1.3.1.4.3 NAME 'emssquidactive' DESC 'Squid Service Status' SUP emsdescription )",
    "{3} ( 1.3.6.1.4.1.31267.1.3.1.4.4 NAME 'emssquidtimeofday' DESC 'Squid Time Based Control' SUP emsdescription )",
    "{4} ( 1.3.6.1.4.1.31267.1.3.1.4.5 NAME 'emssquidwhitelist' DESC 'Squid White List' SUP name)",
    "{5} ( 1.3.6.1.4.1.31267.1.3.1.4.6 NAME 'emssquidport' DESC 'Squid Port' SUP emsdescription)",
    "{6} ( 1.3.6.1.4.1.31267.1.3.1.4.7 NAME 'emssquidhostname' DESC 'Squid Hostname' SUP emsdescription)",
    "{7} ( 1.3.6.1.4.1.31267.1.3.1.4.8 NAME 'emssquidcachemem' DESC 'Squid Cache Memory' SUP emsdescription)",
    "{8} ( 1.3.6.1.4.1.31267.1.3.1.4.9 NAME 'emssquidvisiblehost' DESC 'Squid Visible Hostname' SUP emsdescription)",
    "{9} ( 1.3.6.1.4.1.31267.1.3.1.4.10 NAME 'emssquiddebuglevel' DESC 'Squid Debug Level' SUP emsdescription)",
    "{10} ( 1.3.6.1.4.1.31267.1.3.1.4.11 NAME 'emssquidcachepool' DESC 'Squid Cache Pool' SUP name)",
    "{11} ( 1.3.6.1.4.1.31267.1.3.1.4.12 NAME 'emssquidaclsactive' DESC 'Squid ACL Status' SUP emsdescription)",
    "{12} ( 1.3.6.1.4.1.31267.1.3.1.4.13 NAME 'emssquidtrustednetworks' DESC 'Squid Trusted Networks' SUP name)",
    "{13} ( 1.3.6.1.4.1.31267.1.3.1.4.14 NAME 'emssquidenablemembers' DESC 'Squid Members Enabled' SUP emsdescription)"
);

$sqReplaceAttrInSchema['olcObjectClasses'] = array(
    "{0} ( 1.3.6.1.4.1.31267.1.3.2.4.1 NAME 'emssquidservice' DESC 'Squid Service Object' SUP emsservice AUXILIARY MAY (emssquidport $ emssquidhostname $ emssquidvisiblehost $ emssquidcachemem $ emssquiddebuglevel $ emssquidcachepool $ emssquidaclsactive $ emssquidtrustednetworks ))",
    "{1} ( 1.3.6.1.4.1.31267.1.3.2.4.2 NAME 'emssquiduser' DESC 'Squid User Object' SUP emsSecurityObject AUXILIARY MUST emssquidactive MAY (emssquidblacklist $ emssquidwhitelist $ emssquidtimeofday ))",
    "{2} ( 1.3.6.1.4.1.31267.1.3.2.4.3 NAME 'emssquidgroup' DESC 'Squid Group Object' SUP emsSecurityObject AUXILIARY MUST emssquidactive MAY (emssquidblacklist $ emssquidwhitelist $ emssquidtimeofday $ emssquidenablemembers ))"
);

// Load ppolicy
$moduleAdd = array();
$moduleAdd['olcModuleLoad'] = "ppolicy";

// Add overlay
$overlayAdd = array();
$overlayAdd['olcOverLay']  = "ppolicy";
$overlayAdd['objectClass'] = array("olcOverlayConfig", "olcPPolicyConfig");

// SQL alterations required.
$sql[] = "TRUNCATE TABLE `transactions`";
$sql[] = "TRUNCATE TABLE `trans_items`";
$sql[] = "TRUNCATE TABLE `trans_groups`";
$sql[] = "TRUNCATE TABLE `trans_items`";
$sql[] = "DROP TABLE IF EXISTS `trans_store`";
$sql[] = "CREATE TABLE `trans_store` (
  `trans_group_id` bigint(20) NOT NULL,
  `objstore` longblob,
  `status` int(11) NOT NULL,
  PRIMARY KEY  (`trans_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

// Execute updates.
addToConfig($ppSchema, 'cn=ppolicy', 'cn=schema,cn=config');
updateSchema('cn=module{0},cn=config', $moduleAdd, "add");
addtoConfig($overlayAdd,'olcOverlay=ppolicy','olcDatabase={2}hdb,cn=config');
updateSchema('cn={11}zivios-core,cn=schema,cn=config', $zcAddAttrToSchema, "add");
updateSchema('cn={11}zivios-core,cn=schema,cn=config', $zcReplaceAttrInSchema, "replace");
updateSchema('cn={13}zivios-squid,cn=schema,cn=config', $sqReplaceAttrInSchema, "replace");
runSql($sql);
