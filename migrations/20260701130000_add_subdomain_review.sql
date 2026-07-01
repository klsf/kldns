ALTER TABLE domains ADD COLUMN require_review INTEGER NOT NULL DEFAULT 0 CHECK (require_review IN (0, 1));

CREATE TABLE subdomains__new (
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

INSERT INTO subdomains__new(id, uid, did, name, full_domain, status, purpose, created_at, updated_at)
SELECT id, uid, did, name, full_domain, status, '', created_at, updated_at FROM subdomains;

DROP TABLE subdomains;

ALTER TABLE subdomains__new RENAME TO subdomains;

CREATE INDEX idx_subdomains_uid ON subdomains(uid);
CREATE INDEX idx_subdomains_did ON subdomains(did);
CREATE INDEX idx_subdomains_status ON subdomains(status);

-- +kldns Down
DROP INDEX IF EXISTS idx_subdomains_status;
DROP INDEX IF EXISTS idx_subdomains_did;
DROP INDEX IF EXISTS idx_subdomains_uid;

CREATE TABLE subdomains__old (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  name TEXT NOT NULL,
  full_domain TEXT NOT NULL,
  status INTEGER NOT NULL DEFAULT 1 CHECK (status IN (0, 1)),
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  UNIQUE(did, name)
);

INSERT INTO subdomains__old(id, uid, did, name, full_domain, status, created_at, updated_at)
SELECT id, uid, did, name, full_domain, status, created_at, updated_at FROM subdomains;

DROP TABLE subdomains;

ALTER TABLE subdomains__old RENAME TO subdomains;

CREATE INDEX idx_subdomains_uid ON subdomains(uid);
CREATE INDEX idx_subdomains_did ON subdomains(did);
CREATE INDEX idx_subdomains_status ON subdomains(status);
