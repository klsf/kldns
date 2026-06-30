package repositories

import (
	"database/sql"
	migrationassets "kldns/migrations"
	"path/filepath"
	"testing"

	_ "modernc.org/sqlite"
)

func TestInitialMigrationEnablesConstraints(t *testing.T) {
	db, err := sql.Open("sqlite", filepath.Join(t.TempDir(), "kldns.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	if err := ConfigureSQLite(db, 1000, false); err != nil {
		t.Fatal(err)
	}
	if err := RunMigrations(db, "../migrations"); err != nil {
		t.Fatal(err)
	}

	var foreignKeys int
	if err := db.QueryRow("PRAGMA foreign_keys").Scan(&foreignKeys); err != nil {
		t.Fatal(err)
	}
	if foreignKeys != 1 {
		t.Fatalf("foreign_keys = %d, want 1", foreignKeys)
	}

	_, err = db.Exec(`INSERT INTO records(uid, did, record_id, name, type, value, line_id, line)
		VALUES (999, 999, 'r1', 'www', 'A', '1.1.1.1', '0', '默认')`)
	if err == nil {
		t.Fatal("expected foreign key failure for orphan record")
	}

	var systemUserID int64
	if err := db.QueryRow(`SELECT id FROM users WHERE id = 0 AND username = 'system-sync'`).Scan(&systemUserID); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('fake', '{}')`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO domains(id, provider_key, remote_zone_id, domain, group_policy, record_types) VALUES (1, 'fake', 'z1', 'example.com', '0', 'A')`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO records(uid, did, record_id, name, type, value, line_id, line)
		VALUES (0, 1, 'remote-1', 'www', 'A', '1.1.1.1', '0', '默认')`); err != nil {
		t.Fatalf("system user should own synced records: %v", err)
	}
}

func TestEmbeddedMigrationsInitializeSchema(t *testing.T) {
	db, err := sql.Open("sqlite", filepath.Join(t.TempDir(), "kldns.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	if err := ConfigureSQLite(db, 1000, false); err != nil {
		t.Fatal(err)
	}
	if err := RunMigrationsFS(db, migrationassets.FS, migrationassets.Dir); err != nil {
		t.Fatal(err)
	}

	var count int
	if err := db.QueryRow("SELECT COUNT(1) FROM schema_migrations").Scan(&count); err != nil {
		t.Fatal(err)
	}
	if count == 0 {
		t.Fatal("embedded migrations did not apply")
	}
}
