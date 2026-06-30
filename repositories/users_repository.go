package repositories

import (
	"context"
	"database/sql"
	"strings"
)

// UsersRepository handles user admin data access.
type UsersRepository struct {
	DB *sql.DB
}

func NewUsersRepository(db *sql.DB) *UsersRepository {
	return &UsersRepository{DB: db}
}

type UserAdminSummary struct {
	ID       int64  `json:"id"`
	GroupID  int64  `json:"group_id"`
	Status   int    `json:"status"`
	Username string `json:"username"`
	Email    string `json:"email"`
	Points   int64  `json:"points"`
}

type UserAdminFilter struct {
	Keyword string
	Status  *int
	GroupID int64
}

type UserWrite struct {
	ID           int64  `json:"id"`
	GroupID      int64  `json:"group_id"`
	Status       int    `json:"status"`
	Username     string `json:"username"`
	Email        string `json:"email"`
	Points       int64  `json:"points"`
	PasswordHash string `json:"-"`
	SID          string `json:"-"`
}

func (r *UsersRepository) ListUsers(ctx context.Context, filter UserAdminFilter) ([]UserAdminSummary, error) {
	result, err := r.ListUsersPage(ctx, filter, PageQuery{})
	return result.Items, err
}

func (r *UsersRepository) ListUsersPage(ctx context.Context, filter UserAdminFilter, page PageQuery) (PageResult[UserAdminSummary], error) {
	fromWhere := `FROM users WHERE id > 0`
	args := []any{}
	if filter.Status != nil {
		fromWhere += ` AND status = ?`
		args = append(args, *filter.Status)
	}
	if filter.GroupID > 0 {
		fromWhere += ` AND group_id = ?`
		args = append(args, filter.GroupID)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		fromWhere += ` AND (lower(username) LIKE ? OR lower(COALESCE(email, '')) LIKE ?)`
		args = append(args, term, term)
	}
	total := int64(0)
	if page.Enabled() {
		var err error
		total, err = countRows(ctx, r.DB, fromWhere, args)
		if err != nil {
			return PageResult[UserAdminSummary]{}, err
		}
	}
	query := `SELECT id, group_id, status, username, COALESCE(email, ''), points ` + fromWhere
	query += ` ORDER BY id DESC`
	if page.Enabled() {
		page = page.Normalize()
		query, args = applyPage(query, args, page)
	}
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PageResult[UserAdminSummary]{}, err
	}
	defer rows.Close()
	items := []UserAdminSummary{}
	for rows.Next() {
		var item UserAdminSummary
		if err := rows.Scan(&item.ID, &item.GroupID, &item.Status, &item.Username, &item.Email, &item.Points); err != nil {
			return PageResult[UserAdminSummary]{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PageResult[UserAdminSummary]{}, err
	}
	if !page.Enabled() {
		total = int64(len(items))
		page = PageQuery{Page: 1, PageSize: len(items)}
	}
	return PageResult[UserAdminSummary]{Items: items, Total: total, Page: page.Page, PageSize: page.PageSize}, nil
}

func (r *UsersRepository) UpdateUser(ctx context.Context, input UserWrite) (bool, error) {
	query := `UPDATE users
		SET group_id = ?, status = ?, username = ?, email = NULLIF(?, ''), points = ?, updated_at = strftime('%s','now')`
	args := []any{input.GroupID, boolStatus(input.Status), input.Username, input.Email, maxInt64(input.Points, 0)}
	if strings.TrimSpace(input.PasswordHash) != "" {
		query += `, password_hash = ?, sid = ?`
		args = append(args, input.PasswordHash, input.SID)
	}
	query += ` WHERE id = ?`
	args = append(args, input.ID)
	res, err := r.DB.ExecContext(ctx, query, args...)
	if err != nil {
		return false, err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}
	return affected == 1, nil
}
