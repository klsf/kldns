PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS subdomains (
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
CREATE INDEX IF NOT EXISTS idx_subdomains_uid ON subdomains(uid);
CREATE INDEX IF NOT EXISTS idx_subdomains_did ON subdomains(did);
CREATE INDEX IF NOT EXISTS idx_subdomains_status ON subdomains(status);

ALTER TABLE records ADD COLUMN subdomain_id INTEGER REFERENCES subdomains(id) ON UPDATE CASCADE ON DELETE SET NULL;

WITH RECURSIVE parts(record_id, did, uid, original_name, part, rest, depth, created_at) AS (
  SELECT id, did, uid, lower(name), '', lower(name), 0, created_at
  FROM records
  WHERE trim(name) NOT IN ('', '@')
  UNION ALL
  SELECT record_id, did, uid, original_name,
    CASE WHEN instr(rest, '.') = 0 THEN rest ELSE substr(rest, 1, instr(rest, '.') - 1) END,
    CASE WHEN instr(rest, '.') = 0 THEN '' ELSE substr(rest, instr(rest, '.') + 1) END,
    depth + 1,
    created_at
  FROM parts
  WHERE rest != ''
),
labels AS (
  SELECT record_id, did, uid, original_name, part AS label, created_at
  FROM parts
  WHERE rest = '' AND part != ''
),
owners AS (
  SELECT DISTINCT l.did, l.label,
    COALESCE(
      (
        SELECT root.uid
        FROM labels root
        WHERE root.did = l.did AND root.label = l.label AND root.original_name = l.label
        ORDER BY root.created_at ASC, root.record_id ASC
        LIMIT 1
      ),
      (
        SELECT first.uid
        FROM labels first
        WHERE first.did = l.did AND first.label = l.label
        ORDER BY first.created_at ASC, first.record_id ASC
        LIMIT 1
      )
    ) AS owner_uid
  FROM labels l
)
INSERT OR IGNORE INTO subdomains(uid, did, name, full_domain, status)
SELECT owners.owner_uid, owners.did, owners.label, owners.label || '.' || domains.domain, 1
FROM owners
JOIN domains ON domains.id = owners.did;

WITH RECURSIVE parts(record_id, did, original_name, part, rest) AS (
  SELECT id, did, lower(name), '', lower(name)
  FROM records
  WHERE trim(name) NOT IN ('', '@')
  UNION ALL
  SELECT record_id, did, original_name,
    CASE WHEN instr(rest, '.') = 0 THEN rest ELSE substr(rest, 1, instr(rest, '.') - 1) END,
    CASE WHEN instr(rest, '.') = 0 THEN '' ELSE substr(rest, instr(rest, '.') + 1) END
  FROM parts
  WHERE rest != ''
),
labels AS (
  SELECT record_id, did, part AS label
  FROM parts
  WHERE rest = '' AND part != ''
)
UPDATE records
SET subdomain_id = (
  SELECT subdomains.id
  FROM labels
  JOIN subdomains ON subdomains.did = labels.did AND subdomains.name = labels.label
  WHERE labels.record_id = records.id
)
WHERE id IN (SELECT record_id FROM labels);

UPDATE records
SET uid = (
  SELECT subdomains.uid FROM subdomains WHERE subdomains.id = records.subdomain_id
)
WHERE subdomain_id IS NOT NULL
  AND uid != (SELECT subdomains.uid FROM subdomains WHERE subdomains.id = records.subdomain_id);

CREATE INDEX IF NOT EXISTS idx_records_subdomain_id ON records(subdomain_id);

DROP TABLE IF EXISTS record_reviews;
ALTER TABLE domains DROP COLUMN review_mode;
