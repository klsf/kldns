package repositories

import (
	"context"
	"database/sql"
	"errors"
	"strings"

	"kldns/models"
)

var ErrInsufficientPoints = errors.New("insufficient points")
var ErrPointOverflow = errors.New("point balance overflow")

// PointsRepository handles point records and balance mutations.
type PointsRepository struct {
	DB *Database
}

func NewPointsRepository(db *Database) *PointsRepository {
	return &PointsRepository{DB: db}
}

type PointRecordAdminFilter struct {
	UID     int64
	AdminID int64
	Action  string
	Change  string
	Keyword string
}

type AdminPointRecordSummary struct {
	ID            int64  `json:"id"`
	UID           int64  `json:"uid"`
	AdminUID      int64  `json:"admin_uid"`
	Username      string `json:"username"`
	AdminUsername string `json:"admin_username"`
	Action        string `json:"action"`
	Points        int64  `json:"points"`
	Rest          int64  `json:"rest"`
	Remark        string `json:"remark"`
	CreatedAt     int64  `json:"created_at"`
}

type PointAdjustment struct {
	UserID  int64
	AdminID int64
	Delta   int64
	Action  string
	Remark  string
	Log     models.OperationLog
}

type PointAdjustmentResult struct {
	UserID   int64  `json:"user_id"`
	Username string `json:"username"`
	Delta    int64  `json:"delta"`
	Balance  int64  `json:"balance"`
	Action   string `json:"action"`
	Remark   string `json:"remark"`
}

func (r *PointsRepository) ListAdminPointRecords(ctx context.Context, filter PointRecordAdminFilter) ([]AdminPointRecordSummary, error) {
	result, err := r.ListAdminPointRecordsPage(ctx, filter, PageQuery{})
	return result.Items, err
}

func (r *PointsRepository) ListAdminPointRecordsPage(ctx context.Context, filter PointRecordAdminFilter, page PageQuery) (PageResult[AdminPointRecordSummary], error) {
	fromWhere := `FROM point_records p
		LEFT JOIN users u ON u.id = p.uid
		LEFT JOIN users au ON au.id = p.admin_uid
		WHERE 1 = 1`
	args := []any{}
	if filter.UID > 0 {
		fromWhere += ` AND p.uid = ?`
		args = append(args, filter.UID)
	}
	if filter.AdminID > 0 {
		fromWhere += ` AND p.admin_uid = ?`
		args = append(args, filter.AdminID)
	}
	if action := strings.TrimSpace(filter.Action); action != "" {
		fromWhere += ` AND p.action = ?`
		args = append(args, action)
	}
	switch strings.ToLower(strings.TrimSpace(filter.Change)) {
	case "increase":
		fromWhere += ` AND p.points > 0`
	case "decrease":
		fromWhere += ` AND p.points < 0`
	}
	if term := likeTerm(filter.Keyword); term != "" {
		fromWhere += ` AND (
			lower(COALESCE(u.username, '')) LIKE ? OR lower(COALESCE(au.username, '')) LIKE ? OR
			lower(p.action) LIKE ? OR lower(COALESCE(p.remark, '')) LIKE ?
		)`
		args = append(args, term, term, term, term)
	}
	total := int64(0)
	if page.Enabled() {
		var err error
		total, err = countRows(ctx, r.DB, fromWhere, args)
		if err != nil {
			return PageResult[AdminPointRecordSummary]{}, err
		}
	}
	query := `SELECT
			p.id, p.uid, COALESCE(p.admin_uid, 0),
			COALESCE(u.username, ''), COALESCE(au.username, ''),
			p.action, p.points, p.rest, COALESCE(p.remark, ''), p.created_at
		` + fromWhere + ` ORDER BY p.id DESC`
	if page.Enabled() {
		page = page.Normalize()
		query, args = applyPage(query, args, page)
	} else {
		query += ` LIMIT 200`
	}
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PageResult[AdminPointRecordSummary]{}, err
	}
	defer rows.Close()
	items := []AdminPointRecordSummary{}
	for rows.Next() {
		var item AdminPointRecordSummary
		if err := rows.Scan(&item.ID, &item.UID, &item.AdminUID, &item.Username, &item.AdminUsername, &item.Action, &item.Points, &item.Rest, &item.Remark, &item.CreatedAt); err != nil {
			return PageResult[AdminPointRecordSummary]{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PageResult[AdminPointRecordSummary]{}, err
	}
	if !page.Enabled() {
		total = int64(len(items))
		page = PageQuery{Page: 1, PageSize: len(items)}
	}
	return PageResult[AdminPointRecordSummary]{Items: items, Total: total, Page: page.Page, PageSize: page.PageSize}, nil
}

func (r *PointsRepository) AdjustUserPoints(ctx context.Context, adjustment PointAdjustment) (PointAdjustmentResult, error) {
	tx, err := r.DB.BeginTx(ctx, nil)
	if err != nil {
		return PointAdjustmentResult{}, err
	}
	var username string
	var current int64
	if err := tx.QueryRowContext(ctx, `SELECT username, points FROM users WHERE id = ? AND id > 0`, adjustment.UserID).Scan(&username, &current); err != nil {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, err
	}
	next := current + adjustment.Delta
	if adjustment.Delta > 0 && next < current {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, ErrPointOverflow
	}
	if next < 0 {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, ErrInsufficientPoints
	}
	res, err := tx.ExecContext(ctx, `UPDATE users SET points = ?, updated_at = strftime('%s','now') WHERE id = ?`, next, adjustment.UserID)
	if err != nil {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, err
	}
	if affected != 1 {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, sql.ErrNoRows
	}
	if _, err := tx.ExecContext(ctx, `INSERT INTO point_records(uid, admin_uid, action, points, rest, remark)
		VALUES (?, NULLIF(?, 0), ?, ?, ?, ?)`, adjustment.UserID, adjustment.AdminID, adjustment.Action, adjustment.Delta, next, adjustment.Remark); err != nil {
		_ = tx.Rollback()
		return PointAdjustmentResult{}, err
	}
	if adjustment.Log.Message != "" {
		if err := insertOperationLog(ctx, tx, adjustment.Log); err != nil {
			_ = tx.Rollback()
			return PointAdjustmentResult{}, err
		}
	}
	if err := tx.Commit(); err != nil {
		return PointAdjustmentResult{}, err
	}
	return PointAdjustmentResult{
		UserID: adjustment.UserID, Username: username, Delta: adjustment.Delta,
		Balance: next, Action: adjustment.Action, Remark: adjustment.Remark,
	}, nil
}
