DELETE FROM settings WHERE key = 'array_check';
DROP INDEX IF EXISTS idx_records_checked_at;
ALTER TABLE records DROP COLUMN checked_at;

-- +kldns Down
