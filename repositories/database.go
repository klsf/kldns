package repositories

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	"github.com/glebarez/sqlite"
	"gorm.io/gorm"
)

type Database struct {
	gorm *gorm.DB
	sql  *sql.DB
}

func OpenSQLite(path string, busyTimeoutMS int, wal bool) (*Database, error) {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return nil, err
	}
	gormDB, err := gorm.Open(sqlite.Open(path), &gorm.Config{})
	if err != nil {
		return nil, err
	}
	sqlDB, err := gormDB.DB()
	if err != nil {
		return nil, err
	}
	sqlDB.SetMaxOpenConns(1)
	if err := ConfigureSQLite(sqlDB, busyTimeoutMS, wal); err != nil {
		_ = sqlDB.Close()
		return nil, err
	}
	return &Database{gorm: gormDB, sql: sqlDB}, nil
}

func (db *Database) Gorm() *gorm.DB {
	if db == nil {
		return nil
	}
	return db.gorm
}

func (db *Database) SQLDB() *sql.DB {
	if db == nil {
		return nil
	}
	return db.sql
}

func (db *Database) Close() error {
	if db == nil || db.sql == nil {
		return nil
	}
	return db.sql.Close()
}

func (db *Database) Exec(query string, args ...any) (sql.Result, error) {
	return db.sql.Exec(query, args...)
}

func (db *Database) QueryRow(query string, args ...any) *sql.Row {
	return db.sql.QueryRow(query, args...)
}

func (db *Database) QueryContext(ctx context.Context, query string, args ...any) (*sql.Rows, error) {
	return db.sql.QueryContext(ctx, query, args...)
}

func (db *Database) QueryRowContext(ctx context.Context, query string, args ...any) *sql.Row {
	return db.sql.QueryRowContext(ctx, query, args...)
}

func (db *Database) ExecContext(ctx context.Context, query string, args ...any) (sql.Result, error) {
	return db.sql.ExecContext(ctx, query, args...)
}

func (db *Database) BeginTx(ctx context.Context, opts *sql.TxOptions) (*sql.Tx, error) {
	return db.sql.BeginTx(ctx, opts)
}

func ConfigureSQLite(db *sql.DB, busyTimeoutMS int, wal bool) error {
	if _, err := db.Exec("PRAGMA foreign_keys = ON"); err != nil {
		return fmt.Errorf("enable foreign_keys: %w", err)
	}
	if _, err := db.Exec(fmt.Sprintf("PRAGMA busy_timeout = %d", busyTimeoutMS)); err != nil {
		return fmt.Errorf("set busy_timeout: %w", err)
	}
	if wal {
		if _, err := db.Exec("PRAGMA journal_mode = WAL"); err != nil {
			return fmt.Errorf("enable wal: %w", err)
		}
	}
	return nil
}
