package repositories

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"

	"github.com/beego/beego/v2/server/web"
	_ "modernc.org/sqlite"
)

func OpenFromConfig() (*sql.DB, error) {
	path, err := web.AppConfig.String("db_path")
	if err != nil || path == "" {
		path = "data/kldns.db"
	}
	busyTimeout, err := web.AppConfig.Int("db_busy_timeout_ms")
	if err != nil || busyTimeout <= 0 {
		busyTimeout = 5000
	}
	wal, err := web.AppConfig.Bool("db_wal")
	if err != nil {
		wal = true
	}
	return OpenSQLite(path, busyTimeout, wal)
}

func OpenSQLite(path string, busyTimeoutMS int, wal bool) (*sql.DB, error) {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return nil, err
	}
	db, err := sql.Open("sqlite", path)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(1)
	if err := ConfigureSQLite(db, busyTimeoutMS, wal); err != nil {
		_ = db.Close()
		return nil, err
	}
	return db, nil
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
