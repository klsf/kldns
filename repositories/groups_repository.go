package repositories

import (
	"context"
	"database/sql"
)

// GroupsRepository handles user group data access.
type GroupsRepository struct {
	DB *sql.DB
}

func NewGroupsRepository(db *sql.DB) *GroupsRepository {
	return &GroupsRepository{DB: db}
}

type GroupSummary struct {
	ID   int64  `json:"id"`
	Name string `json:"name"`
}

type GroupFilter struct {
	Keyword string
}

func (r *GroupsRepository) ListGroups(ctx context.Context, filter GroupFilter) ([]GroupSummary, error) {
	query := `SELECT id, name FROM "groups" WHERE 1 = 1`
	args := []any{}
	if term := likeTerm(filter.Keyword); term != "" {
		query += ` AND (lower(name) LIKE ? OR CAST(id AS TEXT) LIKE ?)`
		args = append(args, term, term)
	}
	query += ` ORDER BY id ASC`
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []GroupSummary{}
	for rows.Next() {
		var item GroupSummary
		if err := rows.Scan(&item.ID, &item.Name); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *GroupsRepository) UpsertGroup(ctx context.Context, id int64, name string) (int64, error) {
	if id > 0 {
		_, err := r.DB.ExecContext(ctx, `UPDATE "groups" SET name = ?, updated_at = strftime('%s','now') WHERE id = ?`, name, id)
		return id, err
	}
	res, err := r.DB.ExecContext(ctx, `INSERT INTO "groups"(name) VALUES (?)`, name)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (r *GroupsRepository) DeleteGroup(ctx context.Context, id int64) (bool, error) {
	tx, err := r.DB.BeginTx(ctx, nil)
	if err != nil {
		return false, err
	}
	if id <= 100 {
		_ = tx.Rollback()
		return false, nil
	}
	if _, err := tx.ExecContext(ctx, `UPDATE users SET group_id = 100, updated_at = strftime('%s','now') WHERE group_id = ?`, id); err != nil {
		_ = tx.Rollback()
		return false, err
	}
	res, err := tx.ExecContext(ctx, `DELETE FROM "groups" WHERE id = ?`, id)
	if err != nil {
		_ = tx.Rollback()
		return false, err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		_ = tx.Rollback()
		return false, err
	}
	if affected != 1 {
		_ = tx.Rollback()
		return false, nil
	}
	if err := tx.Commit(); err != nil {
		return false, err
	}
	return true, nil
}
