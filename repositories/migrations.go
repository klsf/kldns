package repositories

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

const downMarker = "-- +kldns Down"

func RunMigrations(db *sql.DB, dir string) error {
	if _, err := db.Exec(`CREATE TABLE IF NOT EXISTS schema_migrations (
		version TEXT PRIMARY KEY,
		applied_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
	)`); err != nil {
		return err
	}

	entries, err := os.ReadDir(dir)
	if err != nil {
		return err
	}
	files := make([]string, 0, len(entries))
	for _, entry := range entries {
		if !entry.IsDir() && strings.HasSuffix(entry.Name(), ".sql") {
			files = append(files, entry.Name())
		}
	}
	sort.Strings(files)

	for _, file := range files {
		var exists int
		if err := db.QueryRow("SELECT COUNT(1) FROM schema_migrations WHERE version = ?", file).Scan(&exists); err != nil {
			return err
		}
		if exists > 0 {
			continue
		}
		body, err := os.ReadFile(filepath.Join(dir, file))
		if err != nil {
			return err
		}
		sqlText := string(body)
		if idx := strings.Index(sqlText, downMarker); idx >= 0 {
			sqlText = sqlText[:idx]
		}
		if strings.TrimSpace(sqlText) == "" {
			return fmt.Errorf("migration %s has empty up section", file)
		}
		tx, err := db.Begin()
		if err != nil {
			return err
		}
		if _, err := tx.Exec(sqlText); err != nil {
			_ = tx.Rollback()
			return fmt.Errorf("apply migration %s: %w", file, err)
		}
		if _, err := tx.Exec("INSERT INTO schema_migrations(version) VALUES (?)", file); err != nil {
			_ = tx.Rollback()
			return err
		}
		if err := tx.Commit(); err != nil {
			return err
		}
	}
	return nil
}
