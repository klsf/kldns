package repositories

import (
	"database/sql"
	"fmt"
	"io/fs"
	"os"
	"path"
	"path/filepath"
	"sort"
	"strings"
)

const downMarker = "-- +kldns Down"

func RunMigrations(db *sql.DB, dir string) error {
	return runMigrations(db, dir, os.ReadDir, func(name string) ([]byte, error) {
		return os.ReadFile(name)
	}, filepath.Join)
}

func RunMigrationsFS(db *sql.DB, source fs.FS, dir string) error {
	return runMigrations(db, dir, func(name string) ([]fs.DirEntry, error) {
		return fs.ReadDir(source, name)
	}, func(name string) ([]byte, error) {
		return fs.ReadFile(source, name)
	}, path.Join)
}

func runMigrations(
	db *sql.DB,
	dir string,
	readDir func(string) ([]fs.DirEntry, error),
	readFile func(string) ([]byte, error),
	join func(...string) string,
) error {
	if _, err := db.Exec(`CREATE TABLE IF NOT EXISTS schema_migrations (
		version TEXT PRIMARY KEY,
		applied_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
	)`); err != nil {
		return err
	}

	entries, err := readDir(dir)
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
	if len(files) == 0 {
		return fmt.Errorf("no migration files found in %s", dir)
	}

	for _, file := range files {
		var exists int
		if err := db.QueryRow("SELECT COUNT(1) FROM schema_migrations WHERE version = ?", file).Scan(&exists); err != nil {
			return err
		}
		if exists > 0 {
			continue
		}
		body, err := readFile(join(dir, file))
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
