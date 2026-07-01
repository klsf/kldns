ALTER TABLE point_records ADD COLUMN admin_uid INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE INDEX idx_point_records_admin_uid ON point_records(admin_uid);
CREATE INDEX idx_point_records_created_at ON point_records(created_at DESC);

-- +kldns Down
DROP INDEX IF EXISTS idx_point_records_created_at;
DROP INDEX IF EXISTS idx_point_records_admin_uid;
