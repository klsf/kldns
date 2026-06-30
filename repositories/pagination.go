package repositories

import (
	"context"
)

const defaultPageSize = 20
const maxPageSize = 100

type PageQuery struct {
	Page     int `json:"page"`
	PageSize int `json:"page_size"`
}

type PageResult[T any] struct {
	Items    []T   `json:"items"`
	Total    int64 `json:"total"`
	Page     int   `json:"page"`
	PageSize int   `json:"page_size"`
}

func (q PageQuery) Enabled() bool {
	return q.Page > 0 || q.PageSize > 0
}

func (q PageQuery) Normalize() PageQuery {
	if q.Page <= 0 {
		q.Page = 1
	}
	if q.PageSize <= 0 {
		q.PageSize = defaultPageSize
	}
	if q.PageSize > maxPageSize {
		q.PageSize = maxPageSize
	}
	return q
}

func (q PageQuery) LimitOffset() (int, int) {
	q = q.Normalize()
	return q.PageSize, (q.Page - 1) * q.PageSize
}

func countRows(ctx context.Context, db *Database, fromWhere string, args []any) (int64, error) {
	var total int64
	err := db.QueryRowContext(ctx, "SELECT COUNT(1) "+fromWhere, args...).Scan(&total)
	return total, err
}

func applyPage(query string, args []any, page PageQuery) (string, []any) {
	limit, offset := page.LimitOffset()
	return query + " LIMIT ? OFFSET ?", append(args, limit, offset)
}
