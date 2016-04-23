DROP TABLE IF EXISTS `kldns_configs`;
CREATE TABLE `kldns_configs` (
  `vkey` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` varchar(2048) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`vkey`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `kldns_domains`;
CREATE TABLE `kldns_domains` (
  `domain_id` varchar(255) NOT NULL,
  `level` int(10) unsigned NOT NULL DEFAULT '0',
  `dns` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `remark` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`domain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `kldns_records`;
CREATE TABLE `kldns_records` (
  `record_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL,
  `domain_id` varchar(255) CHARACTER SET utf8 NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `type` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'A',
  `value` varchar(255) CHARACTER SET utf8 NOT NULL,
  `updatetime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
DROP TABLE IF EXISTS `kldns_users`;
CREATE TABLE `kldns_users` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user` varchar(255) CHARACTER SET utf8 NOT NULL,
  `email` varchar(255) NOT NULL,
  `pwd` varchar(255) CHARACTER SET utf8 NOT NULL,
  `sid` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `level` int(2) NOT NULL DEFAULT '0',
  `max` int(2) NOT NULL DEFAULT '0',
  `regtime` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`,`user`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
INSERT INTO `kldns_configs` VALUES ('webName', '快乐域名分发系统V1.3');
INSERT INTO `kldns_configs` VALUES ('webFoot', 'Powered by KuaiLeShiFu !\r\n');
INSERT INTO `kldns_configs` VALUES ('webAdmin', '3642acf47ffa3a9503e792d62f1f0876');
INSERT INTO `kldns_configs` VALUES ('allowNum', '0');
INSERT INTO `kldns_configs` VALUES ('forbidRecord', 'www,@,m,3g,4g');