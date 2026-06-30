package repositories

import (
	"context"
	"testing"
)

func TestInstallRepositoryCreatesOnlyCountedAdmin(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	repo := NewInstallRepository(db)
	count, err := repo.UserCount(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if count != 0 {
		t.Fatalf("new database user count = %d, want 0", count)
	}
	if _, err := repo.CreateAdmin(context.Background(), "admin", "hash", "admin@example.com", "sid"); err != nil {
		t.Fatal(err)
	}
	count, err = repo.UserCount(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if count != 1 {
		t.Fatalf("user count after admin = %d, want 1", count)
	}
}
