package repositories

import (
	"context"
	"database/sql"
)

type InstallRepository struct {
	DB *sql.DB
}

func NewInstallRepository(db *sql.DB) *InstallRepository {
	return &InstallRepository{DB: db}
}

func (r *InstallRepository) UserCount(ctx context.Context) (int64, error) {
	var count int64
	err := r.DB.QueryRowContext(ctx, `SELECT COUNT(1) FROM users WHERE id > 0`).Scan(&count)
	return count, err
}

func (r *InstallRepository) CreateAdmin(ctx context.Context, username string, passwordHash string, email string, sid string) (int64, error) {
	res, err := r.DB.ExecContext(ctx, `INSERT INTO users(group_id, status, username, password_hash, sid, email, points)
		VALUES (99, 2, ?, ?, ?, NULLIF(?, ''), 0)`, username, passwordHash, sid, email)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}
