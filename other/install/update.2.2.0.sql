ALTER TABLE `kldns_users`
ADD COLUMN `status`  tinyint(2) NOT NULL DEFAULT 1 AFTER `sid`;
ALTER TABLE `kldns_users`
ADD COLUMN `email`  varchar(255) NULL AFTER `sid`;