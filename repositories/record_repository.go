package repositories

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"strings"

	"kldns/models"
)

type RecordRepository struct {
	DB *sql.DB
}

func NewRecordRepository(db *sql.DB) *RecordRepository {
	return &RecordRepository{DB: db}
}

func (r *RecordRepository) GetSubdomainForUser(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error) {
	return NewSubdomainsRepository(r.DB).GetSubdomainForUser(ctx, id, uid)
}

func (r *RecordRepository) GetSubdomain(ctx context.Context, id int64) (models.Subdomain, models.Domain, error) {
	return NewSubdomainsRepository(r.DB).GetSubdomain(ctx, id)
}

func (r *RecordRepository) ListSubdomainsForUser(ctx context.Context, uid int64) ([]models.Subdomain, error) {
	return NewSubdomainsRepository(r.DB).ListSubdomainsForUser(ctx, uid)
}

func (r *RecordRepository) ListSubdomainsForDomain(ctx context.Context, did int64) ([]models.Subdomain, error) {
	return NewSubdomainsRepository(r.DB).ListSubdomainsForDomain(ctx, did)
}

func (r *RecordRepository) RegisterSubdomain(ctx context.Context, user models.User, domain models.Domain, name string, log models.OperationLog) (models.Subdomain, error) {
	return NewSubdomainsRepository(r.DB).RegisterSubdomain(ctx, user, domain, name, log)
}

func (r *RecordRepository) DeleteSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error {
	return NewSubdomainsRepository(r.DB).DeleteSubdomain(ctx, subdomain, log)
}

func (r *RecordRepository) DeleteAdminSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error {
	return NewSubdomainsRepository(r.DB).DeleteAdminSubdomain(ctx, subdomain, log)
}

func (r *RecordRepository) GetUser(ctx context.Context, id int64) (models.User, error) {
	var user models.User
	err := r.DB.QueryRowContext(ctx, `SELECT id, group_id, status, username, COALESCE(email, ''), points FROM users WHERE id = ?`, id).
		Scan(&user.ID, &user.GroupID, &user.Status, &user.Username, &user.Email, &user.Points)
	return user, err
}

func (r *RecordRepository) GetDomainForGroup(ctx context.Context, did int64, gid int64) (models.Domain, error) {
	var row struct {
		domain      models.Domain
		recordTypes string
	}
	err := r.DB.QueryRowContext(ctx, `SELECT id, provider_key, COALESCE(provider_config_ciphertext, ''), remote_zone_id, domain, group_policy, record_types, beian, points_cost, COALESCE(description, '')
		FROM domains
		WHERE id = ? AND (group_policy = '0' OR instr(',' || group_policy || ',', ',' || ? || ',') > 0)`, did, gid).
		Scan(&row.domain.ID, &row.domain.ProviderKey, &row.domain.ProviderConfigCiphertext, &row.domain.RemoteZoneID, &row.domain.Domain, &row.domain.GroupPolicy,
			&row.recordTypes, &row.domain.Beian, &row.domain.PointsCost, &row.domain.Description)
	if err != nil {
		return models.Domain{}, err
	}
	for _, typ := range strings.Split(row.recordTypes, ",") {
		typ = strings.ToUpper(strings.TrimSpace(typ))
		if typ != "" {
			row.domain.RecordTypes = append(row.domain.RecordTypes, typ)
		}
	}
	return row.domain, nil
}

func (r *RecordRepository) GetDomain(ctx context.Context, did int64) (models.Domain, error) {
	var row struct {
		domain      models.Domain
		recordTypes string
	}
	err := r.DB.QueryRowContext(ctx, `SELECT id, provider_key, COALESCE(provider_config_ciphertext, ''), remote_zone_id, domain, group_policy, record_types, beian, points_cost, COALESCE(description, '')
		FROM domains WHERE id = ?`, did).
		Scan(&row.domain.ID, &row.domain.ProviderKey, &row.domain.ProviderConfigCiphertext, &row.domain.RemoteZoneID, &row.domain.Domain, &row.domain.GroupPolicy,
			&row.recordTypes, &row.domain.Beian, &row.domain.PointsCost, &row.domain.Description)
	if err != nil {
		return models.Domain{}, err
	}
	for _, typ := range strings.Split(row.recordTypes, ",") {
		typ = strings.ToUpper(strings.TrimSpace(typ))
		if typ != "" {
			row.domain.RecordTypes = append(row.domain.RecordTypes, typ)
		}
	}
	return row.domain, nil
}

func (r *RecordRepository) GetRecordForUser(ctx context.Context, id int64, uid int64) (models.Record, error) {
	var record models.Record
	err := r.DB.QueryRowContext(ctx, `SELECT id, uid, did, COALESCE(subdomain_id, 0), record_id, name, type, value, line_id, COALESCE(line, '') FROM records WHERE id = ? AND uid = ?`, id, uid).
		Scan(&record.ID, &record.UID, &record.DID, &record.SubdomainID, &record.RecordID, &record.Name, &record.Type, &record.Value, &record.LineID, &record.Line)
	return record, err
}

func (r *RecordRepository) GetRecord(ctx context.Context, id int64) (models.Record, error) {
	var record models.Record
	err := r.DB.QueryRowContext(ctx, `SELECT id, uid, did, COALESCE(subdomain_id, 0), record_id, name, type, value, line_id, COALESCE(line, '') FROM records WHERE id = ?`, id).
		Scan(&record.ID, &record.UID, &record.DID, &record.SubdomainID, &record.RecordID, &record.Name, &record.Type, &record.Value, &record.LineID, &record.Line)
	return record, err
}

func (r *RecordRepository) ListRecordsForSubdomain(ctx context.Context, subdomainID int64) ([]models.Record, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, uid, did, COALESCE(subdomain_id, 0), record_id, name, type, value, line_id, COALESCE(line, '')
		FROM records WHERE subdomain_id = ? ORDER BY id ASC`, subdomainID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Record{}
	for rows.Next() {
		var item models.Record
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.SubdomainID, &item.RecordID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *RecordRepository) ListRecordsForUser(ctx context.Context, uid int64) ([]models.Record, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, uid, did, COALESCE(subdomain_id, 0), record_id, name, type, value, line_id, COALESCE(line, '')
		FROM records WHERE uid = ? ORDER BY id ASC`, uid)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Record{}
	for rows.Next() {
		var item models.Record
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.SubdomainID, &item.RecordID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *RecordRepository) ListRecordsForUserCascade(ctx context.Context, uid int64) ([]models.Record, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT DISTINCT r.id, r.uid, r.did, COALESCE(r.subdomain_id, 0), r.record_id, r.name, r.type, r.value, r.line_id, COALESCE(r.line, '')
		FROM records r
		LEFT JOIN subdomains s ON s.id = r.subdomain_id
		WHERE r.uid = ? OR s.uid = ?
		ORDER BY r.id ASC`, uid, uid)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Record{}
	for rows.Next() {
		var item models.Record
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.SubdomainID, &item.RecordID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *RecordRepository) ListRecordsForDomain(ctx context.Context, did int64) ([]models.Record, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, uid, did, COALESCE(subdomain_id, 0), record_id, name, type, value, line_id, COALESCE(line, '')
		FROM records WHERE did = ? ORDER BY id ASC`, did)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Record{}
	for rows.Next() {
		var item models.Record
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.SubdomainID, &item.RecordID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *RecordRepository) RecordNameExists(ctx context.Context, did int64, name string, recordType string, ignoreID int64) (bool, error) {
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
	query := `SELECT COUNT(1) FROM records WHERE did = ? AND name = ? AND (type = ? OR type = 'CNAME' OR ? = 'CNAME')`
	args := []any{did, name, recordType, recordType}
	if ignoreID > 0 {
		query += ` AND id != ?`
		args = append(args, ignoreID)
	}
	var count int
	if err := r.DB.QueryRowContext(ctx, query, args...).Scan(&count); err != nil {
		return false, err
	}
	return count > 0, nil
}

func (r *RecordRepository) AllowUnlimitedSubdomainRecords(ctx context.Context) (bool, error) {
	return NewSettingsRepository(r.DB).AllowUnlimitedSubdomainRecords(ctx)
}

func (r *RecordRepository) CountRecordsForSubdomain(ctx context.Context, subdomainID int64, uid int64) (int64, error) {
	var count int64
	err := r.DB.QueryRowContext(ctx, `SELECT COUNT(1) FROM records WHERE subdomain_id = ? AND uid = ?`, subdomainID, uid).Scan(&count)
	return count, err
}

func (r *RecordRepository) ApplyCreatedRecord(ctx context.Context, user models.User, domain models.Domain, record models.Record, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if _, err := tx.ExecContext(ctx, `INSERT INTO records(uid, did, subdomain_id, record_id, name, type, value, line_id, line)
			VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)`, user.ID, domain.ID, record.SubdomainID, record.RecordID, record.Name, record.Type, record.Value, record.LineID, record.Line); err != nil {
			return err
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *RecordRepository) ApplyUpdatedRecord(ctx context.Context, recordID int64, record models.Record, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `UPDATE records
			SET subdomain_id = NULLIF(?, 0), record_id = ?, name = ?, type = ?, value = ?, line_id = ?, line = ?, updated_at = strftime('%s','now')
			WHERE id = ? AND uid = ?`,
			record.SubdomainID, record.RecordID, record.Name, record.Type, record.Value, record.LineID, record.Line, recordID, record.UID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return sql.ErrNoRows
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *RecordRepository) ApplyDeletedRecord(ctx context.Context, recordID int64, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `DELETE FROM records WHERE id = ?`, recordID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return sql.ErrNoRows
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *RecordRepository) ApplyAdminCreatedRecord(ctx context.Context, record models.Record, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if _, err := tx.ExecContext(ctx, `INSERT INTO records(uid, did, subdomain_id, record_id, name, type, value, line_id, line)
			VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)`, record.UID, record.DID, record.SubdomainID, record.RecordID, record.Name, record.Type, record.Value, record.LineID, record.Line); err != nil {
			return err
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *RecordRepository) ApplyAdminUpdatedRecord(ctx context.Context, recordID int64, record models.Record, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `UPDATE records
			SET subdomain_id = NULLIF(?, 0), record_id = ?, name = ?, type = ?, value = ?, line_id = ?, line = ?, updated_at = strftime('%s','now')
			WHERE id = ?`,
			record.SubdomainID, record.RecordID, record.Name, record.Type, record.Value, record.LineID, record.Line, recordID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return sql.ErrNoRows
		}
		return insertOperationLog(ctx, tx, log)
	})
}

type SyncedRecordInput struct {
	RecordID string
	Name     string
	Type     string
	Value    string
	LineID   string
	Line     string
}

type SyncRecordsResult struct {
	Total   int `json:"total"`
	Created int `json:"created"`
	Skipped int `json:"skipped"`
}

func (r *RecordRepository) SyncDomainRecords(ctx context.Context, domain models.Domain, records []SyncedRecordInput, log models.OperationLog) (SyncRecordsResult, error) {
	result := SyncRecordsResult{Total: len(records)}
	err := withTx(ctx, r.DB, func(tx *sql.Tx) error {
		for _, record := range records {
			exists, err := syncedRecordExists(ctx, tx, domain.ID, record.RecordID, record.Name, record.Type)
			if err != nil {
				return err
			}
			if exists {
				continue
			}
			subdomainID, err := ensureSystemSubdomain(ctx, tx, domain, record.Name)
			if err != nil {
				return err
			}
			if _, err := tx.ExecContext(ctx, `INSERT INTO records(uid, did, subdomain_id, record_id, name, type, value, line_id, line)
				VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)`,
				0, domain.ID, subdomainID, record.RecordID, record.Name, record.Type, record.Value, record.LineID, record.Line); err != nil {
				return err
			}
			result.Created++
		}
		if log.Message != "" {
			return insertOperationLog(ctx, tx, log)
		}
		return nil
	})
	if err != nil {
		return SyncRecordsResult{}, err
	}
	result.Skipped = result.Total - result.Created
	return result, nil
}

func syncedRecordExists(ctx context.Context, tx *sql.Tx, did int64, recordID string, name string, recordType string) (bool, error) {
	var count int
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
	err := tx.QueryRowContext(ctx, `SELECT COUNT(1) FROM records
		WHERE did = ? AND (record_id = ? OR (name = ? AND (type = ? OR type = 'CNAME' OR ? = 'CNAME')))`,
		did, recordID, name, recordType, recordType).Scan(&count)
	if err != nil {
		return false, err
	}
	return count > 0, nil
}

func ensureSystemSubdomain(ctx context.Context, tx *sql.Tx, domain models.Domain, name string) (int64, error) {
	label := rightmostRecordLabel(name)
	if label == "" {
		return 0, nil
	}
	var id int64
	err := tx.QueryRowContext(ctx, `SELECT id FROM subdomains WHERE did = ? AND name = ?`, domain.ID, label).Scan(&id)
	if err == nil {
		return id, nil
	}
	if err != sql.ErrNoRows {
		return 0, err
	}
	res, err := tx.ExecContext(ctx, `INSERT INTO subdomains(uid, did, name, full_domain, status)
		VALUES (?, ?, ?, ?, ?)`, 0, domain.ID, label, label+"."+domain.Domain, models.SubdomainStatusActive)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func rightmostRecordLabel(name string) string {
	name = strings.ToLower(strings.TrimSpace(name))
	if name == "" || name == "@" {
		return ""
	}
	parts := strings.Split(name, ".")
	return strings.TrimSpace(parts[len(parts)-1])
}

func (r *RecordRepository) EnqueueDNSWriteJob(ctx context.Context, job models.DNSWriteJob) error {
	if job.ValueDigest == "" {
		sum := sha256.Sum256([]byte(job.Payload))
		job.ValueDigest = hex.EncodeToString(sum[:])
	}
	if job.Status == "" {
		job.Status = "pending"
	}
	_, err := r.DB.ExecContext(ctx, `INSERT INTO dns_write_jobs(uid, source, provider_key, domain, record_name, record_type, value_digest, remote_record_id, operation, status, last_error, payload)
		VALUES (NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		job.UID, job.Source, job.ProviderKey, job.Domain, job.RecordName, job.RecordType, job.ValueDigest, job.RemoteRecordID, job.Operation, job.Status, job.LastError, job.Payload)
	return err
}

func (r *RecordRepository) DeleteAdminUser(ctx context.Context, user models.User, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if err := insertOperationLog(ctx, tx, log); err != nil {
			return err
		}
		res, err := tx.ExecContext(ctx, `DELETE FROM users WHERE id = ? AND id != 1`, user.ID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return sql.ErrNoRows
		}
		return nil
	})
}

func (r *RecordRepository) DeleteAdminDomain(ctx context.Context, domain models.Domain, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if err := insertOperationLog(ctx, tx, log); err != nil {
			return err
		}
		res, err := tx.ExecContext(ctx, `DELETE FROM domains WHERE id = ?`, domain.ID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return sql.ErrNoRows
		}
		return nil
	})
}

func withTx(ctx context.Context, db *sql.DB, fn func(*sql.Tx) error) error {
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	if err := fn(tx); err != nil {
		_ = tx.Rollback()
		return err
	}
	return tx.Commit()
}

func insertOperationLog(ctx context.Context, tx *sql.Tx, log models.OperationLog) error {
	if log.Source == "" {
		log.Source = "system"
	}
	_, err := tx.ExecContext(ctx, `INSERT INTO operation_logs(uid, admin_uid, source, target_type, target_id, ip, action, message, extra)
		VALUES (NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?)`,
		log.UID, log.AdminUID, log.Source, log.TargetType, log.TargetID, log.IP, log.Action, log.Message, log.Extra)
	return err
}

func splitCSVUpper(value string) []string {
	parts := strings.Split(value, ",")
	out := make([]string, 0, len(parts))
	for _, part := range parts {
		part = strings.ToUpper(strings.TrimSpace(part))
		if part != "" {
			out = append(out, part)
		}
	}
	return out
}
