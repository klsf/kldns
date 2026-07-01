CREATE TABLE subdomains__review_history (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  name TEXT NOT NULL,
  full_domain TEXT NOT NULL,
  status INTEGER NOT NULL DEFAULT 1 CHECK (status IN (0, 1, 2, 3)),
  purpose TEXT NOT NULL DEFAULT '',
  reject_reason TEXT NOT NULL DEFAULT '',
  reviewed_by INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  reviewed_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

INSERT INTO subdomains__review_history(id, uid, did, name, full_domain, status, purpose, created_at, updated_at)
SELECT id, uid, did, name, full_domain, status, COALESCE(purpose, ''), created_at, updated_at FROM subdomains;

DROP TABLE subdomains;

ALTER TABLE subdomains__review_history RENAME TO subdomains;

CREATE UNIQUE INDEX idx_subdomains_live_unique ON subdomains(did, name) WHERE status IN (0, 1, 2);
CREATE INDEX idx_subdomains_uid ON subdomains(uid);
CREATE INDEX idx_subdomains_did ON subdomains(did);
CREATE INDEX idx_subdomains_status ON subdomains(status);
CREATE INDEX idx_subdomains_reviewed_by ON subdomains(reviewed_by);

-- +kldns Down
DROP INDEX IF EXISTS idx_subdomains_reviewed_by;
DROP INDEX IF EXISTS idx_subdomains_status;
DROP INDEX IF EXISTS idx_subdomains_did;
DROP INDEX IF EXISTS idx_subdomains_uid;
DROP INDEX IF EXISTS idx_subdomains_live_unique;

CREATE TABLE subdomains__without_review_history (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  name TEXT NOT NULL,
  full_domain TEXT NOT NULL,
  status INTEGER NOT NULL DEFAULT 1 CHECK (status IN (0, 1, 2)),
  purpose TEXT NOT NULL DEFAULT '',
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  UNIQUE(did, name)
);

INSERT INTO subdomains__without_review_history(id, uid, did, name, full_domain, status, purpose, created_at, updated_at)
SELECT id, uid, did, name, full_domain, status, COALESCE(purpose, ''), created_at, updated_at FROM subdomains WHERE status IN (0, 1, 2);

DROP TABLE subdomains;

ALTER TABLE subdomains__without_review_history RENAME TO subdomains;

CREATE INDEX idx_subdomains_uid ON subdomains(uid);
CREATE INDEX idx_subdomains_did ON subdomains(did);
CREATE INDEX idx_subdomains_status ON subdomains(status);
