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
 * @package     ZiviosInstaller
 * @copyright   Copyright (c) 2008 Zivios, LLC. (http://www.zivios.org)
 * @license     http://www.zivios.org/legal/license
 **/

$sqlInstall = array();

$sqlInstall[] = "DROP TABLE IF EXISTS `chan_deb_packages`;";
$sqlInstall[] = "CREATE TABLE `chan_deb_packages` (
  `id` bigint(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `short_desc` varchar(255) NOT NULL,
  `long_desc` text,
  `version` varchar(255) NOT NULL,
  `channel_id` bigint(11) NOT NULL,
  `replaces` text,
  `depends` text,
  `conflicts` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unique_chan` (`name`,`channel_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `channels`;";
$sqlInstall[] = "CREATE TABLE `channels` (
  `id` bigint(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `enabled` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `deb_section` varchar(255) default NULL,
  `baseurl` varchar(255) NOT NULL,
  `rpm_mirrorlist` varchar(255) default NULL,
  `proxy_service_dn` varchar(255) default NULL,
  `class` varchar(255) NOT NULL,
  `deb_dists` varchar(255) default NULL,
  `arch` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `groups`;";
$sqlInstall[] = "CREATE TABLE `groups` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `gid` int(11) unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  `policy` varchar(32) NOT NULL,
  `modified` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `gid` (`gid`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `inst_deb_packages`;";
$sqlInstall[] = "CREATE TABLE `inst_deb_packages` (
  `id` bigint(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `short_desc` varchar(255) NOT NULL,
  `long_desc` text,
  `version` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `emscomputerdn` varchar(255) default NULL,
  `replaces` varchar(255) default NULL,
  `depends` varchar(255) default NULL,
  `conflicts` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unique_packages` (`name`,`emscomputerdn`),
  KEY `name_index` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `inst_rpm_packages`;";
$sqlInstall[] = "CREATE TABLE `inst_rpm_packages` (
   `id` bigint(11) NOT NULL auto_increment,
   `name` varchar(255) NOT NULL,
   `short_desc` varchar(255) NOT NULL,
   `long_desc` text,
   `version` varchar(255) NOT NULL,
   `status` varchar(255) NOT NULL,
   `emscomputerdn` varchar(255) default NULL,
   `replaces` varchar(255) default NULL,
   `depends` varchar(255) default NULL,
   `conflicts` varchar(255) default NULL,
   `release` varchar(255) NOT NULL,
   PRIMARY KEY  (`id`),
   UNIQUE KEY `unique_rpmpackages` (`name`,`emscomputerdn`),
   KEY `name_index` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `menu`;";
$sqlInstall[] = "CREATE TABLE `menu` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `controller` varchar(255) NOT NULL,
  `name` varchar(32) NOT NULL,
  `type` enum('root','branch','leaf') NOT NULL,
  `linktype` enum('redirect','jsinternal','disabled') NOT NULL default 'redirect',
  `parent` varchar(32) NOT NULL,
  `sort` smallint(2) NOT NULL default '0',
  `display` enum('Y','N') NOT NULL default 'N',
  `access` enum('guest','authenticated','user','group','computer','gpo','dns_entry','krb_srv','user_cont','grp_cont','dns_cont','comp_cont','krb_cont','gpo_cont','orgunit') NOT NULL,
  `icon` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `menu_acls`;";
$sqlInstall[] = "CREATE TABLE `menu_acls` (
  `id` int(11) unsigned NOT NULL,
  `gid` int(11) NOT NULL,
  `perms` enum('none','add','edit','delete','all') NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `gid` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";


$sqlInstall[] = "DROP TABLE IF EXISTS `notifications`;";
$sqlInstall[] = "CREATE TABLE `notifications` (
  `id` bigint(11) NOT NULL auto_increment,
  `short_desc` varchar(255) NOT NULL,
  `long_desc` text,
  `type` varchar(15) NOT NULL,
  `refresh_dn` varchar(255) default NULL,
  `click_action` varchar(255) default NULL,
  `click_args_array` longblob,
  `publish_time` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `read_timestamp` timestamp NULL default NULL,
  `userdn` varchar(255) NOT NULL,
  `isread` int(1) NOT NULL default '0',
  `processed` int(1) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `package_report_items`;";
$sqlInstall[] = "CREATE TABLE `package_report_items` (
  `id` bigint(11) NOT NULL auto_increment,
  `package_report_id` bigint(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `current_version` varchar(255) NOT NULL,
  `new_version` varchar(255) NOT NULL,
  `channel_id` bigint(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `package_reports`;";
$sqlInstall[] = "CREATE TABLE `package_reports` (
  `id` bigint(11) NOT NULL auto_increment,
  `emscomputerdn` varchar(255) NOT NULL,
  `rtime` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `trans_groups`;";
$sqlInstall[] = "CREATE TABLE `trans_groups` (
  `id` bigint(20) NOT NULL auto_increment,
  `transaction_id` bigint(20) default NULL,
  `description` varchar(255) default NULL,
  `status` varchar(255) NOT NULL,
  `exec_mode` varchar(255) NOT NULL,
  `stop_ts` timestamp NULL default NULL,
  `start_ts` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `trans_items`;";
$sqlInstall[] = "CREATE TABLE `trans_items` (
  `id` bigint(20) NOT NULL auto_increment,
  `trans_group_id` bigint(20) default NULL,
  `description` varchar(255) default NULL,
  `status` varchar(255) NOT NULL,
  `objarray` longblob,
  `codearray` longblob,
  `stop_ts` timestamp NULL default NULL,
  `rollbackarray` longblob,
  `min` int(11) default NULL,
  `max` int(11) default NULL,
  `progress` int(11) default NULL,
  `log` text,
  `agentretrycount` int(11) NOT NULL default '0',
  `agentlastretryts` timestamp NULL default NULL,
  `created_ts` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `start_ts` timestamp NULL default NULL,
  `exception_index` int(11) default NULL,
  `retry_ts` timestamp NULL default NULL,
  `deferred` int(11) NOT NULL,
  `retry` int(11) default NULL,
  `last_exception` longtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `trans_store`;";
$sqlInstall[] = "CREATE TABLE `trans_store` (
  `trans_group_id` bigint(20) NOT NULL,
  `objstore` longblob,
  `status` int(11) NOT NULL,
  PRIMARY KEY  (`trans_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

$sqlInstall[] = "DROP TABLE IF EXISTS `transactions`;";
$sqlInstall[] = "CREATE TABLE `transactions` (
  `id` bigint(20) NOT NULL auto_increment,
  `status` varchar(255) NOT NULL,
  `user` varchar(255) default NULL,
  `flags` varchar(255) NOT NULL default 'N',
  `description` varchar(255) default NULL,
  `last_exception` longblob,
  `password` varchar(255) default NULL,
  `deferred` smallint(1) NOT NULL,
  `refreshdn` varchar(255) default NULL,
  `defer_index` int(11) default NULL,
  `created_ts` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `start_ts` timestamp NULL default NULL,
  `stop_ts` timestamp NULL default NULL,
  `processcount` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

// Data for menu.
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(1, '/auth/lostpassword', ' Lost Password', 'root', 'redirect', '0', 0, 'Y', 'guest', '/icons/key2.png')";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(2, '/directory/', ' Zivios Directory', 'root', 'redirect', '0', 0, 'Y', 'authenticated', '/icons/directory.png')";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(3, '/directory/browse', 'Browse Directory', 'branch', 'redirect', '2', 0, 'Y', 'authenticated', NULL)";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(4, '/index/logout', ' Logout', 'root', 'redirect', '99', 0, 'Y', 'authenticated', '/icons/logout.png')";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(5, '/directory/reports', 'Directory Reports', 'leaf', 'redirect', '2', 1, 'Y', 'authenticated', NULL)";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(6, '/directory/view', 'View Options', 'branch', 'disabled', '3', 0, 'Y', 'authenticated', NULL)";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(7, '/directory/viewbrowsehistory', 'Browse History', 'leaf', 'jsinternal', '6', 0, 'Y', 'authenticated', NULL)";
$sqlInstall[] = "INSERT INTO `menu` (`id`, `controller`, `name`, `type`, `linktype`, `parent`, `sort`, `display`, `access`, `icon`) VALUES(8, '/directory/viewbrowsehistory', 'Workflow Manager', 'leaf', 'jsinternal', '6', 1, 'Y', 'authenticated', NULL)";

