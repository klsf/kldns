INSERT INTO settings(key, value)
SELECT 'array_mail', '{"enabled":"0","host":"","port":"25","username":"","from":"","from_name":"KLDNS","encryption":"none","verify_base_url":""}'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'array_mail');

-- +kldns Down
DELETE FROM settings WHERE key = 'array_mail';
