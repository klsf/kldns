PRAGMA foreign_keys = ON;

-- 支撑记录冲突检测、DNS 同步去重和管理端列表筛选。
CREATE INDEX IF NOT EXISTS idx_records_did_name_type ON records(did, name, type);
CREATE INDEX IF NOT EXISTS idx_records_did_record_id ON records(did, record_id);

-- 支撑后台操作日志按时间倒序分页。
CREATE INDEX IF NOT EXISTS idx_operation_logs_created_at ON operation_logs(created_at DESC);

-- +kldns Down
DROP INDEX IF EXISTS idx_operation_logs_created_at;
DROP INDEX IF EXISTS idx_records_did_record_id;
DROP INDEX IF EXISTS idx_records_did_name_type;
