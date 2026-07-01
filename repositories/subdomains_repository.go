package repositories

import (
	"context"
	"database/sql"
	"fmt"
	"strings"

	"kldns/models"
)

// SubdomainsRepository handles subdomain data access.
type SubdomainsRepository struct {
	DB *Database
}

func NewSubdomainsRepository(db *Database) *SubdomainsRepository {
	return &SubdomainsRepository{DB: db}
}

func (r *SubdomainsRepository) GetSubdomainForUser(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error) {
	return r.getSubdomainForUser(ctx, id, uid, true)
}

func (r *SubdomainsRepository) GetUserSubdomain(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error) {
	return r.getSubdomainForUser(ctx, id, uid, false)
}

func (r *SubdomainsRepository) getSubdomainForUser(ctx context.Context, id int64, uid int64, activeOnly bool) (models.Subdomain, models.Domain, error) {
	var subdomain models.Subdomain
	var row struct {
		domain      models.Domain
		recordTypes string
	}
	query := `SELECT
			s.id, s.uid, s.did, s.name, s.full_domain, s.status, COALESCE(s.purpose, ''), COALESCE(s.reject_reason, ''), COALESCE(s.reviewed_by, 0), COALESCE(s.reviewed_at, 0), s.created_at, s.updated_at,
			d.id, d.provider_key, COALESCE(d.provider_config_ciphertext, ''), d.remote_zone_id, d.domain, d.group_policy, d.record_types, d.beian, d.points_cost, d.require_review, COALESCE(d.description, '')
		FROM subdomains s
		JOIN domains d ON d.id = s.did
		WHERE s.id = ? AND s.uid = ?`
	args := []any{id, uid}
	if activeOnly {
		query += ` AND s.status = ?`
		args = append(args, models.SubdomainStatusActive)
	}
	err := r.DB.QueryRowContext(ctx, query, args...).
		Scan(&subdomain.ID, &subdomain.UID, &subdomain.DID, &subdomain.Name, &subdomain.FullDomain, &subdomain.Status, &subdomain.Purpose, &subdomain.RejectReason, &subdomain.ReviewedBy, &subdomain.ReviewedAt, &subdomain.CreatedAt, &subdomain.UpdatedAt,
			&row.domain.ID, &row.domain.ProviderKey, &row.domain.ProviderConfigCiphertext, &row.domain.RemoteZoneID, &row.domain.Domain, &row.domain.GroupPolicy,
			&row.recordTypes, &row.domain.Beian, &row.domain.PointsCost, &row.domain.RequireReview, &row.domain.Description)
	if err != nil {
		return models.Subdomain{}, models.Domain{}, err
	}
	row.domain.RecordTypes = splitCSVUpper(row.recordTypes)
	return subdomain, row.domain, nil
}

func (r *SubdomainsRepository) GetSubdomain(ctx context.Context, id int64) (models.Subdomain, models.Domain, error) {
	var subdomain models.Subdomain
	var row struct {
		domain      models.Domain
		recordTypes string
	}
	err := r.DB.QueryRowContext(ctx, `SELECT
			s.id, s.uid, s.did, s.name, s.full_domain, s.status, COALESCE(s.purpose, ''), COALESCE(s.reject_reason, ''), COALESCE(s.reviewed_by, 0), COALESCE(s.reviewed_at, 0), s.created_at, s.updated_at,
			d.id, d.provider_key, COALESCE(d.provider_config_ciphertext, ''), d.remote_zone_id, d.domain, d.group_policy, d.record_types, d.beian, d.points_cost, d.require_review, COALESCE(d.description, '')
		FROM subdomains s
		JOIN domains d ON d.id = s.did
		WHERE s.id = ?`, id).
		Scan(&subdomain.ID, &subdomain.UID, &subdomain.DID, &subdomain.Name, &subdomain.FullDomain, &subdomain.Status, &subdomain.Purpose, &subdomain.RejectReason, &subdomain.ReviewedBy, &subdomain.ReviewedAt, &subdomain.CreatedAt, &subdomain.UpdatedAt,
			&row.domain.ID, &row.domain.ProviderKey, &row.domain.ProviderConfigCiphertext, &row.domain.RemoteZoneID, &row.domain.Domain, &row.domain.GroupPolicy,
			&row.recordTypes, &row.domain.Beian, &row.domain.PointsCost, &row.domain.RequireReview, &row.domain.Description)
	if err != nil {
		return models.Subdomain{}, models.Domain{}, err
	}
	row.domain.RecordTypes = splitCSVUpper(row.recordTypes)
	return subdomain, row.domain, nil
}

func (r *SubdomainsRepository) ListSubdomainsForUser(ctx context.Context, uid int64) ([]models.Subdomain, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, uid, did, name, full_domain, status, COALESCE(purpose, ''), COALESCE(reject_reason, ''), COALESCE(reviewed_by, 0), COALESCE(reviewed_at, 0), created_at, updated_at
		FROM subdomains WHERE uid = ? ORDER BY id ASC`, uid)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Subdomain{}
	for rows.Next() {
		var item models.Subdomain
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.Name, &item.FullDomain, &item.Status, &item.Purpose, &item.RejectReason, &item.ReviewedBy, &item.ReviewedAt, &item.CreatedAt, &item.UpdatedAt); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *SubdomainsRepository) ListSubdomainsForDomain(ctx context.Context, did int64) ([]models.Subdomain, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, uid, did, name, full_domain, status, COALESCE(purpose, ''), COALESCE(reject_reason, ''), COALESCE(reviewed_by, 0), COALESCE(reviewed_at, 0), created_at, updated_at
		FROM subdomains WHERE did = ? ORDER BY id ASC`, did)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []models.Subdomain{}
	for rows.Next() {
		var item models.Subdomain
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.Name, &item.FullDomain, &item.Status, &item.Purpose, &item.RejectReason, &item.ReviewedBy, &item.ReviewedAt, &item.CreatedAt, &item.UpdatedAt); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *SubdomainsRepository) RegisterSubdomain(ctx context.Context, user models.User, domain models.Domain, name string, purpose string, requireReview bool, log models.OperationLog) (models.Subdomain, error) {
	status := models.SubdomainStatusActive
	if requireReview {
		status = models.SubdomainStatusPending
	}
	subdomain := models.Subdomain{
		UID: user.ID, DID: domain.ID, Name: name,
		FullDomain: name + "." + domain.Domain,
		Status:     status,
		Purpose:    purpose,
	}
	err := withTx(ctx, r.DB, func(tx *sql.Tx) error {
		var unassignedRecords int
		if err := tx.QueryRowContext(ctx, `SELECT COUNT(1) FROM records
			WHERE did = ? AND COALESCE(subdomain_id, 0) = 0 AND (lower(name) = ? OR lower(name) LIKE ?)`,
			domain.ID, name, "%."+name).Scan(&unassignedRecords); err != nil {
			return err
		}
		if unassignedRecords > 0 {
			return fmt.Errorf("subdomain namespace has unassigned records")
		}
		if domain.PointsCost > 0 {
			res, err := tx.ExecContext(ctx, `UPDATE users SET points = points - ?, updated_at = strftime('%s','now')
				WHERE id = ? AND points >= ?`, domain.PointsCost, user.ID, domain.PointsCost)
			if err != nil {
				return err
			}
			affected, err := res.RowsAffected()
			if err != nil {
				return err
			}
			if affected != 1 {
				return fmt.Errorf("insufficient points")
			}
			var rest int64
			if err := tx.QueryRowContext(ctx, `SELECT points FROM users WHERE id = ?`, user.ID).Scan(&rest); err != nil {
				return err
			}
			if _, err := tx.ExecContext(ctx, `INSERT INTO point_records(uid, action, points, rest, remark)
				VALUES (?, ?, ?, ?, ?)`, user.ID, "消费", -domain.PointsCost, rest, "注册二级域名["+subdomain.FullDomain+"]"); err != nil {
				return err
			}
		}
		res, err := tx.ExecContext(ctx, `INSERT INTO subdomains(uid, did, name, full_domain, status, purpose)
			VALUES (?, ?, ?, ?, ?, ?)`, subdomain.UID, subdomain.DID, subdomain.Name, subdomain.FullDomain, subdomain.Status, subdomain.Purpose)
		if err != nil {
			return err
		}
		subdomain.ID, err = res.LastInsertId()
		if err != nil {
			return err
		}
		return insertOperationLog(ctx, tx, log)
	})
	return subdomain, err
}

func (r *SubdomainsRepository) DeleteSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `DELETE FROM subdomains
			WHERE id = ? AND uid = ? AND NOT EXISTS (
				SELECT 1 FROM records WHERE subdomain_id = ?
			)`, subdomain.ID, subdomain.UID, subdomain.ID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return fmt.Errorf("subdomain has records or does not exist")
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *SubdomainsRepository) DeleteAdminSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `DELETE FROM subdomains
			WHERE id = ? AND NOT EXISTS (
				SELECT 1 FROM records WHERE subdomain_id = ?
			)`, subdomain.ID, subdomain.ID)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return fmt.Errorf("subdomain has records or does not exist")
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *SubdomainsRepository) ApproveSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		res, err := tx.ExecContext(ctx, `UPDATE subdomains
			SET status = ?, reject_reason = '', reviewed_by = NULLIF(?, 0), reviewed_at = strftime('%s','now'), updated_at = strftime('%s','now')
			WHERE id = ? AND status = ?`, models.SubdomainStatusActive, log.AdminUID, subdomain.ID, models.SubdomainStatusPending)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return fmt.Errorf("subdomain is not pending or does not exist")
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *SubdomainsRepository) RejectPendingSubdomain(ctx context.Context, subdomain models.Subdomain, domain models.Domain, reason string, pointRemark string, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if domain.PointsCost > 0 {
			if _, err := tx.ExecContext(ctx, `UPDATE users SET points = points + ?, updated_at = strftime('%s','now') WHERE id = ?`, domain.PointsCost, subdomain.UID); err != nil {
				return err
			}
			var rest int64
			if err := tx.QueryRowContext(ctx, `SELECT points FROM users WHERE id = ?`, subdomain.UID).Scan(&rest); err != nil {
				return err
			}
			if strings.TrimSpace(pointRemark) == "" {
				pointRemark = "域名申请审核未通过，退回注册积分"
			}
			if _, err := tx.ExecContext(ctx, `INSERT INTO point_records(uid, action, points, rest, remark)
				VALUES (?, ?, ?, ?, ?)`, subdomain.UID, "退回", domain.PointsCost, rest, pointRemark); err != nil {
				return err
			}
		}
		res, err := tx.ExecContext(ctx, `UPDATE subdomains
			SET status = ?, reject_reason = ?, reviewed_by = NULLIF(?, 0), reviewed_at = strftime('%s','now'), updated_at = strftime('%s','now')
			WHERE id = ? AND status = ?`, models.SubdomainStatusRejected, reason, log.AdminUID, subdomain.ID, models.SubdomainStatusPending)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return fmt.Errorf("subdomain is not pending or does not exist")
		}
		return insertOperationLog(ctx, tx, log)
	})
}

func (r *SubdomainsRepository) CancelPendingSubdomain(ctx context.Context, subdomain models.Subdomain, domain models.Domain, pointRemark string, log models.OperationLog) error {
	return withTx(ctx, r.DB, func(tx *sql.Tx) error {
		if domain.PointsCost > 0 {
			if _, err := tx.ExecContext(ctx, `UPDATE users SET points = points + ?, updated_at = strftime('%s','now') WHERE id = ?`, domain.PointsCost, subdomain.UID); err != nil {
				return err
			}
			var rest int64
			if err := tx.QueryRowContext(ctx, `SELECT points FROM users WHERE id = ?`, subdomain.UID).Scan(&rest); err != nil {
				return err
			}
			if strings.TrimSpace(pointRemark) == "" {
				pointRemark = "待审核域名取消，退回注册积分"
			}
			if _, err := tx.ExecContext(ctx, `INSERT INTO point_records(uid, action, points, rest, remark)
				VALUES (?, ?, ?, ?, ?)`, subdomain.UID, "退回", domain.PointsCost, rest, pointRemark); err != nil {
				return err
			}
		}
		res, err := tx.ExecContext(ctx, `DELETE FROM subdomains WHERE id = ? AND status = ?`, subdomain.ID, models.SubdomainStatusPending)
		if err != nil {
			return err
		}
		affected, err := res.RowsAffected()
		if err != nil {
			return err
		}
		if affected != 1 {
			return fmt.Errorf("subdomain is not pending or does not exist")
		}
		return insertOperationLog(ctx, tx, log)
	})
}
