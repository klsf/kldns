package repositories

import (
	"context"
	"database/sql"
	"testing"
)

func TestRecordRepositoryConflictAllowsDifferentTypesAndProtectsCNAME(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	seedAPIUser(t, db)
	seedRecordDomain(t, db)
	if _, err := db.Exec(`INSERT INTO records(uid, did, record_id, name, type, value, line_id)
		VALUES (1, 1, 'remote-a', 'test', 'A', '1.1.1.1', '0')`); err != nil {
		t.Fatal(err)
	}

	repo := NewRecordRepository(db)
	conflict, err := repo.RecordNameExists(context.Background(), 1, "test", "AAAA", 0)
	if err != nil {
		t.Fatal(err)
	}
	if conflict {
		t.Fatal("AAAA should not conflict with existing A record")
	}
	conflict, err = repo.RecordNameExists(context.Background(), 1, "test", "A", 0)
	if err != nil {
		t.Fatal(err)
	}
	if !conflict {
		t.Fatal("same record type should conflict")
	}
	conflict, err = repo.RecordNameExists(context.Background(), 1, "test", "CNAME", 0)
	if err != nil {
		t.Fatal(err)
	}
	if !conflict {
		t.Fatal("CNAME should conflict with existing records at the same name")
	}
}

func TestRecordRepositoryAllowsUnlimitedSubdomainRecordsSetting(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()

	repo := NewRecordRepository(db)
	allowed, err := repo.AllowUnlimitedSubdomainRecords(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if !allowed {
		t.Fatal("default setting should allow unlimited lower-level records")
	}

	if _, err := db.Exec(`UPDATE settings SET value = '{"unlimited_subdomain_records":"0"}' WHERE key = 'array_dns'`); err != nil {
		t.Fatal(err)
	}
	allowed, err = repo.AllowUnlimitedSubdomainRecords(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if allowed {
		t.Fatal("disabled setting should reject unlimited lower-level records")
	}
}

func seedRecordDomain(t *testing.T, db *sql.DB) {
	t.Helper()
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('fake', '')`); err != nil {
		t.Fatal(err)
	}
	_, err := db.Exec(`INSERT INTO domains(id, provider_key, remote_zone_id, domain, group_policy, record_types, points_cost)
		VALUES (1, 'fake', 'zone-1', 'example.com', '0', 'A,AAAA,CNAME,MX,TXT', 0)`)
	if err != nil {
		t.Fatal(err)
	}
}
