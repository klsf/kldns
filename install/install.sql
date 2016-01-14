
DROP TABLE IF EXISTS `kldns_configs`;
CREATE TABLE `kldns_configs` (
  `vkey` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` varchar(2048) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`vkey`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `kldns_configs` VALUES ('webName', '快乐域名分发系统v1.0');
INSERT INTO `kldns_configs` VALUES ('webFoot', 'PGNlbnRlcj5Qb3dlcmVkIGJ5IEt1YWlMZVNoaUZ1ICE8L2NlbnRlcj4=');
INSERT INTO `kldns_configs` VALUES ('webAdmin', 'd02af22e9967d381245676709ee08002');
INSERT INTO `kldns_configs` VALUES ('allowNum', '0');

DROP TABLE IF EXISTS `kldns_domains`;
CREATE TABLE `kldns_domains` (
  `domain_id` varchar(255) NOT NULL,
  `allow_uid` int(10) unsigned NOT NULL DEFAULT  '0',
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
  `regtime` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `lasttime` datetime DEFAULT NULL,
  `lastip` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `logintime` datetime DEFAULT NULL,
  `loginip` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  PRIMARY KEY (`uid`,`user`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
