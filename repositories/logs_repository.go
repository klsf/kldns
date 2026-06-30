package repositories

import (
	"context"
	"database/sql"
)

// LogsRepository handles operation log data access.
type LogsRepository struct {
	DB *sql.DB
}

func NewLogsRepository(db *sql.DB) *LogsRepository {
	return &LogsRepository{DB: db}
}

type LogSummary struct {
	ID            int64  `json:"id"`
	UID           int64  `json:"uid"`
	AdminUID      int64  `json:"admin_uid"`
	Username      string `json:"username"`
	AdminUsername string `json:"admin_username"`
	Source        string `json:"source"`
	TargetType    string `json:"target_type"`
	TargetID      string `json:"target_id"`
	Action        string `json:"action"`
	Message       string `json:"message"`
	CreatedAt     int64  `json:"created_at"`
}

type LogFilter struct {
	Source  string
	Action  string
	Keyword string
}

func (r *LogsRepository) ListLogs(ctx context.Context, filter LogFilter) ([]LogSummary, error) {
	result, err := r.ListLogsPage(ctx, filter, PageQuery{})
	return result.Items, err
}

func (r *LogsRepository) ListLogsPage(ctx context.Context, filter LogFilter, page PageQuery) (PageResult[LogSummary], error) {
	fromWhere := `FROM operation_logs l
		LEFT JOIN users u ON u.id = l.uid
		LEFT JOIN users au ON au.id = l.admin_uid
		WHERE 1 = 1`
	args := []any{}
	if filter.Source != "" {
		fromWhere += ` AND l.source = ?`
		args = append(args, filter.Source)
	}
	if filter.Action != "" {
		fromWhere += ` AND l.action = ?`
		args = append(args, filter.Action)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		fromWhere += ` AND (
			lower(COALESCE(l.target_type, '')) LIKE ? OR lower(COALESCE(l.target_id, '')) LIKE ? OR
			lower(l.message) LIKE ? OR lower(l.action) LIKE ? OR lower(l.source) LIKE ? OR
			lower(COALESCE(u.username, '')) LIKE ? OR lower(COALESCE(au.username, '')) LIKE ?
		)`
		args = append(args, term, term, term, term, term, term, term)
	}
	total := int64(0)
	if page.Enabled() {
		var err error
		total, err = countRows(ctx, r.DB, fromWhere, args)
		if err != nil {
			return PageResult[LogSummary]{}, err
		}
	}
	query := `SELECT
			l.id, COALESCE(l.uid, 0), COALESCE(l.admin_uid, 0),
			COALESCE(u.username, ''), COALESCE(au.username, ''),
			l.source, COALESCE(l.target_type, ''), COALESCE(l.target_id, ''), l.action, l.message, l.created_at
		` + fromWhere
	query += ` ORDER BY l.id DESC`
	if page.Enabled() {
		page = page.Normalize()
		query, args = applyPage(query, args, page)
	} else {
		query += ` LIMIT 200`
	}
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PageResult[LogSummary]{}, err
	}
	defer rows.Close()
	items := []LogSummary{}
	for rows.Next() {
		var item LogSummary
		if err := rows.Scan(&item.ID, &item.UID, &item.AdminUID, &item.Username, &item.AdminUsername, &item.Source, &item.TargetType, &item.TargetID, &item.Action, &item.Message, &item.CreatedAt); err != nil {
			return PageResult[LogSummary]{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PageResult[LogSummary]{}, err
	}
	if !page.Enabled() {
		total = int64(len(items))
		page = PageQuery{Page: 1, PageSize: len(items)}
	}
	return PageResult[LogSummary]{Items: items, Total: total, Page: page.Page, PageSize: page.PageSize}, nil
}
