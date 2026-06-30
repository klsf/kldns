DROP TABLE IF EXISTS email_verifications;

DELETE FROM settings WHERE key = 'array_mail';

INSERT INTO settings(key, value)
SELECT 'array_turnstile', '{"site_key":"","register_enabled":"0","login_enabled":"0"}'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'array_turnstile');

-- +kldns Down
DELETE FROM settings WHERE key = 'array_turnstile';
