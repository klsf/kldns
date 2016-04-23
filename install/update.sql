ALTER TABLE `kldns_users`
ADD COLUMN `level`  int(2) NOT NULL DEFAULT 0 AFTER `sid`,
ADD COLUMN `max`  int(2) NOT NULL DEFAULT 0 AFTER `level`;
ALTER TABLE `kldns_domains`
CHANGE COLUMN `allow_uid` `level`  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `domain_id`;
