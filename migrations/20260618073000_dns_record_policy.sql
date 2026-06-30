PRAGMA foreign_keys = ON;

INSERT INTO settings(key, value)
SELECT 'array_dns', '{"unlimited_subdomain_records":"1"}'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key = 'array_dns');

CREATE TABLE records_next (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  subdomain_id INTEGER REFERENCES subdomains(id) ON UPDATE CASCADE ON DELETE SET NULL,
  record_id TEXT NOT NULL,
  name TEXT NOT NULL,
  type TEXT NOT NULL,
  value TEXT NOT NULL,
  line_id TEXT NOT NULL DEFAULT '0',
  line TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  UNIQUE(did, name, type)
);

INSERT INTO records_next(id, uid, did, subdomain_id, record_id, name, type, value, line_id, line, created_at, updated_at)
SELECT id, uid, did, subdomain_id, record_id, name, type, value, line_id, line, created_at, updated_at
FROM records;

DROP TABLE records;
ALTER TABLE records_next RENAME TO records;

CREATE INDEX IF NOT EXISTS idx_records_uid ON records(uid);
CREATE INDEX IF NOT EXISTS idx_records_record_id ON records(record_id);
CREATE INDEX IF NOT EXISTS idx_records_did_type ON records(did, type);
CREATE INDEX IF NOT EXISTS idx_records_subdomain_id ON records(subdomain_id);

-- +kldns Down

DELETE FROM settings WHERE key = 'array_dns';
