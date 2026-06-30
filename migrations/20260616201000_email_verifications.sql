CREATE TABLE email_verifications (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  token_hint TEXT NOT NULL,
  expires_at INTEGER NOT NULL,
  used_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_email_verifications_uid ON email_verifications(uid);
CREATE INDEX idx_email_verifications_expires_at ON email_verifications(expires_at);

-- +kldns Down
DROP TABLE IF EXISTS email_verifications;
