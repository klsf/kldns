ALTER TABLE domains ADD COLUMN provider_config_ciphertext TEXT;

UPDATE domains
SET provider_config_ciphertext = (
  SELECT dns_providers.config_ciphertext
  FROM dns_providers
  WHERE dns_providers.key = domains.provider_key
)
WHERE provider_config_ciphertext IS NULL;

-- +kldns Down
-- SQLite cannot drop a column without rebuilding the table. Keep this migration forward-only.
