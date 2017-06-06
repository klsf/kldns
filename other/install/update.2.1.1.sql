ALTER TABLE `kldns_records`
ADD COLUMN `line` TINYINT(5) DEFAULT 0 AFTER `value`,
ADD COLUMN `line_name` VARCHAR(50) NOT NULL AFTER `line`;

