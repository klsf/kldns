ALTER TABLE `kldns_domains`
ADD COLUMN `review_mode` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `record_types`;

ALTER TABLE `kldns_domains`
ADD COLUMN `beian` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `review_mode`;

CREATE TABLE IF NOT EXISTS `kldns_domain_record_reviews` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `kldns_api_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `kldns_operation_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
