PRAGMA foreign_keys = ON;

CREATE TABLE settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

CREATE TABLE "groups" (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  group_id INTEGER NOT NULL DEFAULT 100 REFERENCES "groups"(id) ON UPDATE CASCADE,
  status INTEGER NOT NULL DEFAULT 0 CHECK (status IN (0, 1, 2)),
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  remember_token_hash TEXT,
  sid TEXT NOT NULL,
  email TEXT UNIQUE,
  points INTEGER NOT NULL DEFAULT 0 CHECK (points >= 0),
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_users_group_id ON users(group_id);
CREATE INDEX idx_users_status ON users(status);

CREATE TABLE dns_providers (
  key TEXT PRIMARY KEY,
  config_ciphertext TEXT NOT NULL,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);

CREATE TABLE domains (
  id INTEGER PRIMARY KEY,
  provider_key TEXT NOT NULL REFERENCES dns_providers(key) ON UPDATE CASCADE ON DELETE RESTRICT,
  remote_zone_id TEXT NOT NULL,
  domain TEXT NOT NULL,
  group_policy TEXT NOT NULL DEFAULT '0',
  record_types TEXT NOT NULL DEFAULT 'A,CNAME',
  review_mode INTEGER NOT NULL DEFAULT 0 CHECK (review_mode IN (0, 1)),
  beian INTEGER NOT NULL DEFAULT 0 CHECK (beian IN (0, 1)),
  points_cost INTEGER NOT NULL DEFAULT 0 CHECK (points_cost >= 0),
  description TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  UNIQUE(provider_key, remote_zone_id),
  UNIQUE(domain)
);
CREATE INDEX idx_domains_provider_key ON domains(provider_key);

CREATE TABLE records (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  record_id TEXT NOT NULL,
  name TEXT NOT NULL,
  type TEXT NOT NULL,
  value TEXT NOT NULL,
  line_id TEXT NOT NULL DEFAULT '0',
  line TEXT,
  checked_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  UNIQUE(did, name)
);
CREATE INDEX idx_records_uid ON records(uid);
CREATE INDEX idx_records_record_id ON records(record_id);
CREATE INDEX idx_records_did_type ON records(did, type);
CREATE INDEX idx_records_checked_at ON records(checked_at);

CREATE TABLE record_reviews (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  did INTEGER NOT NULL REFERENCES domains(id) ON UPDATE CASCADE ON DELETE CASCADE,
  record_local_id INTEGER REFERENCES records(id) ON UPDATE CASCADE ON DELETE SET NULL,
  action TEXT NOT NULL CHECK (action IN ('create', 'update', 'delete')),
  payload TEXT NOT NULL,
  status INTEGER NOT NULL DEFAULT 0 CHECK (status IN (0, 1, 2)),
  review_remark TEXT,
  reviewed_by INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  reviewed_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_record_reviews_uid ON record_reviews(uid);
CREATE INDEX idx_record_reviews_did ON record_reviews(did);
CREATE INDEX idx_record_reviews_record_local_id ON record_reviews(record_local_id);
CREATE INDEX idx_record_reviews_status ON record_reviews(status);

CREATE TABLE api_tokens (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  name TEXT NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  token_hint TEXT NOT NULL,
  last_used_at INTEGER NOT NULL DEFAULT 0,
  expires_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_api_tokens_uid ON api_tokens(uid);

CREATE TABLE sessions (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  token_hint TEXT NOT NULL,
  last_used_at INTEGER NOT NULL DEFAULT 0,
  expires_at INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_sessions_uid ON sessions(uid);

CREATE TABLE point_records (
  id INTEGER PRIMARY KEY,
  uid INTEGER NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  action TEXT NOT NULL,
  points INTEGER NOT NULL,
  rest INTEGER NOT NULL,
  remark TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_point_records_uid ON point_records(uid);
CREATE INDEX idx_point_records_action ON point_records(action);

CREATE TABLE operation_logs (
  id INTEGER PRIMARY KEY,
  uid INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  admin_uid INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  source TEXT NOT NULL DEFAULT 'system',
  target_type TEXT,
  target_id TEXT,
  ip TEXT,
  action TEXT NOT NULL,
  message TEXT NOT NULL,
  extra TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_operation_logs_uid ON operation_logs(uid);
CREATE INDEX idx_operation_logs_admin_uid ON operation_logs(admin_uid);
CREATE INDEX idx_operation_logs_source ON operation_logs(source);
CREATE INDEX idx_operation_logs_action ON operation_logs(action);

CREATE TABLE dns_write_jobs (
  id INTEGER PRIMARY KEY,
  uid INTEGER REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  source TEXT NOT NULL,
  provider_key TEXT NOT NULL,
  domain TEXT NOT NULL,
  record_name TEXT NOT NULL,
  record_type TEXT NOT NULL,
  value_digest TEXT NOT NULL,
  remote_record_id TEXT,
  operation TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  payload TEXT,
  created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
  updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
CREATE INDEX idx_dns_write_jobs_status ON dns_write_jobs(status);
CREATE INDEX idx_dns_write_jobs_provider ON dns_write_jobs(provider_key);

INSERT INTO "groups"(id, name) VALUES (99, '管理组'), (100, '默认组');
INSERT INTO settings(key, value) VALUES
  ('array_user', '{"reg":"1","email":"1","point":"100"}'),
  ('array_web', '{"name":"KLDNS","title":"KLDNS - 二级域名分发与解析管理平台","keywords":"KLDNS,二级域名分发,DNS解析,域名管理平台","description":"KLDNS 用于二级域名分发、DNS 解析管理、用户自助申请与后台统一运维。"}'),
  ('html_header', '<div class="alert alert-primary">本站提供二级域名分发与解析服务，请遵守相关法律法规与平台使用规范。</div>'),
  ('html_home', '欢迎使用 KLDNS 用户控制台。添加解析前请确认主机记录、记录类型与记录值填写正确，并遵守平台解析规范。'),
  ('index_urls', '源码下载|https://github.com/klsf/kldns'),
  ('reserve_domain_name', 'www,w,m,3g,4g,qq');

-- +kldns Down
DROP TABLE IF EXISTS dns_write_jobs;
DROP TABLE IF EXISTS operation_logs;
DROP TABLE IF EXISTS point_records;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS record_reviews;
DROP TABLE IF EXISTS records;
DROP TABLE IF EXISTS domains;
DROP TABLE IF EXISTS dns_providers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS "groups";
DROP TABLE IF EXISTS settings;
