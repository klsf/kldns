DROP TABLE IF EXISTS `kldns_configs`;
CREATE TABLE `kldns_configs` (
  `k` varchar(150) NOT NULL,
  `v` text,
  PRIMARY KEY (`k`),
  UNIQUE KEY `k` (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `kldns_configs` VALUES ('array_mail', '{\"host\":\"smtp.qq.com\",\"port\":\"465\",\"encryption\":\"ssl\",\"username\":\"815856515@qq.com\",\"password\":\"jxvizloqrcxkertg\",\"test\":\"123456@qq.com\"}');
INSERT INTO `kldns_configs` VALUES ('array_user', '{\"reg\":\"1\",\"email\":\"1\",\"point\":\"100\"}');
INSERT INTO `kldns_configs` VALUES ('array_web', '{\"name\":\"KLDNS\",\"title\":\"KLDNS - 二级域名分发与解析管理平台\",\"keywords\":\"KLDNS,二级域名分发,DNS解析,域名管理平台\",\"description\":\"KLDNS 用于二级域名分发、DNS 解析管理、用户自助申请与后台统一运维。\"}');
INSERT INTO `kldns_configs` VALUES ('html_header', '<div class=\"alert alert-primary\">\r\n本站提供二级域名分发与解析服务，适用于测试、学习与内部业务接入。请遵守相关法律法规与平台使用规范。\r\n</div>');
INSERT INTO `kldns_configs` VALUES ('html_home', '欢迎使用 KLDNS 用户控制台。添加解析前请确认主机记录、记录类型与记录值填写正确，并遵守平台解析规范。');
INSERT INTO `kldns_configs` VALUES ('index_urls', '源码下载|https://github.com/klsf/kldns');
INSERT INTO `kldns_configs` VALUES ('reserve_domain_name', 'www,w,m,3g,4g,qq');
DROP TABLE IF EXISTS `kldns_dns_configs`;
CREATE TABLE `kldns_dns_configs` (
  `dns` varchar(150) NOT NULL,
  `config` varchar(1024) DEFAULT NULL,
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`dns`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_domain_records`;
CREATE TABLE `kldns_domain_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `did` int(11) unsigned NOT NULL DEFAULT '0',
  `record_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(32) NOT NULL,
  `value` varchar(255) NOT NULL,
  `line_id` varchar(32) NOT NULL DEFAULT '0',
  `line` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  `checked_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `record_id` (`record_id`),
  KEY `did` (`did`),
  KEY `name` (`name`,`type`),
  KEY `checked_at` (`checked_at`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_domain_record_reviews`;
CREATE TABLE `kldns_domain_record_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `did` int(10) unsigned NOT NULL DEFAULT '0',
  `record_local_id` int(10) unsigned NOT NULL DEFAULT '0',
  `action` varchar(20) NOT NULL,
  `payload` text,
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `review_remark` varchar(255) DEFAULT NULL,
  `reviewed_by` int(10) unsigned NOT NULL DEFAULT '0',
  `reviewed_at` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `did` (`did`),
  KEY `record_local_id` (`record_local_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_domains`;
CREATE TABLE `kldns_domains` (
  `did` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dns` varchar(32) NOT NULL,
  `domain_id` varchar(50) NOT NULL,
  `domain` varchar(50) NOT NULL,
  `groups` varchar(1024) NOT NULL DEFAULT '0',
  `record_types` varchar(255) NOT NULL DEFAULT 'A,CNAME',
  `review_mode` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `beian` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `point` int(10) unsigned NOT NULL DEFAULT '0',
  `desc` text,
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`did`),
  KEY `domain` (`domain`),
  KEY `domain_id` (`domain_id`),
  KEY `dns` (`dns`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_user_groups`;
CREATE TABLE `kldns_user_groups` (
  `gid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`gid`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8;
INSERT INTO `kldns_user_groups` VALUES ('99', '管理组', '1555212209', '1555212209');
INSERT INTO `kldns_user_groups` VALUES ('100', '默认组', '1555212209', '1555235659');
DROP TABLE IF EXISTS `kldns_user_point_records`;
CREATE TABLE `kldns_user_point_records` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `point` int(11) NOT NULL,
  `rest` int(11) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `created_at` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_api_tokens`;
CREATE TABLE `kldns_api_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_hint` varchar(64) NOT NULL,
  `last_used_at` int(10) unsigned NOT NULL DEFAULT '0',
  `expires_at` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_operation_logs`;
CREATE TABLE `kldns_operation_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_uid` int(10) unsigned NOT NULL DEFAULT '0',
  `source` varchar(32) NOT NULL DEFAULT 'system',
  `target_type` varchar(64) DEFAULT NULL,
  `target_id` varchar(64) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `message` varchar(255) NOT NULL,
  `extra` text,
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `admin_uid` (`admin_uid`),
  KEY `source` (`source`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
DROP TABLE IF EXISTS `kldns_users`;
CREATE TABLE `kldns_users` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gid` int(10) unsigned NOT NULL DEFAULT '100',
  `status` tinyint(2) unsigned NOT NULL DEFAULT '0' COMMENT '0禁用 1待认证 2已认证',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `sid` varchar(32) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `point` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `gid` (`gid`),
  KEY `email` (`email`),
  KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;
INSERT INTO `kldns_users` VALUES ('99', '99', '1', 'admin', '$2y$10$v9PHTvnccjua/5FlAf/uFOVPprXxdWjoS54YnjmbQGGk8vDtxk9YS', 'tn38nVWJER1r0uj3oa222roN1E0sPYCDIUZIW30Yz6hR4U3DcHZU09l4gMsZ', '21c4bc5c23819b646aff4bb3196d6de5', null, '0', '1555212209', '1555408180');
