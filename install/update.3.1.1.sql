ALTER TABLE `kldns_domains`
ADD COLUMN `record_types` varchar(255) NOT NULL DEFAULT 'A,CNAME' AFTER `groups`;
