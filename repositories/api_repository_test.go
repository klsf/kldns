package repositories

import (
	"context"
	"database/sql"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"kldns/models"
	"kldns/pkg/auth"

	_ "modernc.org/sqlite"
)

func TestAPIRepositoryAuthenticatesBearerTokenAndUpdatesUsage(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	seedAPIUser(t, db)

	plain, hash, hint, err := auth.NewAPIToken()
	if err != nil {
		t.Fatal(err)
	}
	repo := NewAPIRepository(db)
	if _, err := repo.CreateToken(context.Background(), 1, "default", hash, hint, 0); err != nil {
		t.Fatal(err)
	}
	token, err := repo.AuthenticateToken(context.Background(), auth.HashBearerToken(plain))
	if err != nil {
		t.Fatal(err)
	}
	if token.User.ID != 1 || token.User.Status != 2 {
		t.Fatalf("unexpected user: %#v", token.User)
	}
	var lastUsed int64
	if err := db.QueryRow(`SELECT last_used_at FROM api_tokens WHERE id = ?`, token.ID).Scan(&lastUsed); err != nil {
		t.Fatal(err)
	}
	if lastUsed == 0 {
		t.Fatal("last_used_at was not updated")
	}
}

func TestAPIRepositoryThrottlesBearerTokenUsageUpdate(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	seedAPIUser(t, db)

	plain, hash, hint, err := auth.NewAPIToken()
	if err != nil {
		t.Fatal(err)
	}
	repo := NewAPIRepository(db)
	tokenID, err := repo.CreateToken(context.Background(), 1, "default", hash, hint, 0)
	if err != nil {
		t.Fatal(err)
	}
	const recent = 2000000000
	if _, err := db.Exec(`UPDATE api_tokens SET last_used_at = ? WHERE id = ?`, recent, tokenID); err != nil {
		t.Fatal(err)
	}
	if _, err := repo.AuthenticateToken(context.Background(), auth.HashBearerToken(plain)); err != nil {
		t.Fatal(err)
	}
	var lastUsed int64
	if err := db.QueryRow(`SELECT last_used_at FROM api_tokens WHERE id = ?`, tokenID).Scan(&lastUsed); err != nil {
		t.Fatal(err)
	}
	if lastUsed != recent {
		t.Fatalf("expected throttled last_used_at to stay %d, got %d", recent, lastUsed)
	}
}

func TestAPIRepositoryRejectsDisabledUserToken(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, email, points)
		VALUES (1, 100, 0, 'disabled', 'hash', 'sid', 'disabled@example.com', 100)`); err != nil {
		t.Fatal(err)
	}
	plain, hash, hint, err := auth.NewAPIToken()
	if err != nil {
		t.Fatal(err)
	}
	repo := NewAPIRepository(db)
	if _, err := repo.CreateToken(context.Background(), 1, "default", hash, hint, 0); err != nil {
		t.Fatal(err)
	}
	if _, err := repo.AuthenticateToken(context.Background(), auth.HashBearerToken(plain)); err == nil {
		t.Fatal("disabled user token authenticated")
	}
}

func TestAuthRepositoryUserSettingsAndLoginLookup(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	repo := NewAuthRepository(db)
	settings, err := repo.UserSettings(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if !settings.RegisterOpen || settings.ReviewMode != "auto" || settings.InitialPoints != 100 {
		t.Fatalf("unexpected default settings: %#v", settings)
	}
	if _, err := repo.CreateUser(context.Background(), models.User{
		GroupID: 100, Status: 2, Username: "alice", Email: "alice@example.com", Points: 88,
	}, "hash", "sid"); err != nil {
		t.Fatal(err)
	}
	user, passwordHash, err := repo.FindLoginUser(context.Background(), "alice@example.com")
	if err != nil {
		t.Fatal(err)
	}
	if user.Username != "alice" || passwordHash != "hash" {
		t.Fatalf("unexpected login user: %#v hash=%q", user, passwordHash)
	}
}

func TestAPIRepositoryListsOnlyAvailableDomains(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	seedAPIUser(t, db)
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('fake', '{}')`); err != nil {
		t.Fatal(err)
	}
	_, err := db.Exec(`INSERT INTO domains(provider_key, remote_zone_id, domain, group_policy, record_types) VALUES
		('fake', 'z1', 'open.example', '0', 'A,CNAME'),
		('fake', 'z2', 'group.example', '100', 'A'),
		('fake', 'z3', 'closed.example', '101', 'A')`)
	if err != nil {
		t.Fatal(err)
	}
	items, err := NewAPIRepository(db).ListAvailableDomains(context.Background(), 100, DomainFilter{})
	if err != nil {
		t.Fatal(err)
	}
	if len(items) != 2 {
		t.Fatalf("available domains = %d, want 2: %#v", len(items), items)
	}
}

func TestAPIRepositoryListsPublicDomainsExcludingAdminOnly(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('fake', '{}')`); err != nil {
		t.Fatal(err)
	}
	_, err := db.Exec(`INSERT INTO domains(provider_key, remote_zone_id, domain, group_policy, record_types) VALUES
		('fake', 'z1', 'open.example', '0', 'A,CNAME'),
		('fake', 'z2', 'member.example', '100', 'A'),
		('fake', 'z3', 'mixed.example', '99,100', 'A,TXT'),
		('fake', 'z4', 'admin.example', '99', 'A')`)
	if err != nil {
		t.Fatal(err)
	}
	items, err := NewAPIRepository(db).ListPublicDomains(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	got := map[string]bool{}
	for _, item := range items {
		got[item.Domain] = true
	}
	for _, domain := range []string{"open.example", "member.example", "mixed.example"} {
		if !got[domain] {
			t.Fatalf("public domains missing %s: %#v", domain, items)
		}
	}
	if got["admin.example"] {
		t.Fatalf("admin-only domain was public: %#v", items)
	}
}

func TestAPIRepositoryPointsOverviewReturnsBalanceAndRecentRecords(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	seedAPIUser(t, db)
	now := time.Now().Unix()
	monthStart := time.Now()
	monthStart = time.Date(monthStart.Year(), monthStart.Month(), 1, 0, 0, 0, 0, monthStart.Location())
	lastMonth := monthStart.Add(-24 * time.Hour).Unix()
	if _, err := db.Exec(`INSERT INTO point_records(uid, action, points, rest, remark, created_at) VALUES
		(1, '消费', -20, 80, '注册二级域名[api.example.com]', ?),
		(1, '充值', 200, 280, '管理员调整积分', ?),
		(1, '消费', -30, 250, '注册二级域名[cdn.example.com]', ?)`, now, now, lastMonth); err != nil {
		t.Fatal(err)
	}
	overview, err := NewAPIRepository(db).PointsOverview(context.Background(), 1)
	if err != nil {
		t.Fatal(err)
	}
	if overview.Balance != 100 || overview.MonthSpent != 20 || overview.TotalSpent != 50 {
		t.Fatalf("unexpected overview: %#v", overview)
	}
	if len(overview.RecentRecords) != 3 {
		t.Fatalf("recent records = %d, want 3", len(overview.RecentRecords))
	}
	if overview.RecentRecords[0].Remark != "注册二级域名[cdn.example.com]" || overview.RecentRecords[2].Remark != "注册二级域名[api.example.com]" {
		t.Fatalf("records not ordered by newest id first: %#v", overview.RecentRecords)
	}
}

func TestAdminRepositoryDeleteGroupMovesUsersBeforeDeleting(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO "groups"(id, name) VALUES (101, 'VIP')`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, email, points)
		VALUES (1, 101, 2, 'alice', 'hash', 'sid', 'alice@example.com', 100)`); err != nil {
		t.Fatal(err)
	}
	deleted, err := NewAdminRepository(db).DeleteGroup(context.Background(), 101)
	if err != nil {
		t.Fatal(err)
	}
	if !deleted {
		t.Fatal("group was not deleted")
	}
	var groupID int64
	if err := db.QueryRow(`SELECT group_id FROM users WHERE id = 1`).Scan(&groupID); err != nil {
		t.Fatal(err)
	}
	if groupID != 100 {
		t.Fatalf("user group_id = %d, want 100", groupID)
	}
}

func TestAdminRepositoryUpdateUserCanCertifyAndResetPassword(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, email, points)
		VALUES (1, 100, 1, 'alice', 'old-hash', 'old-sid', 'alice@example.com', 100)`); err != nil {
		t.Fatal(err)
	}
	updated, err := NewAdminRepository(db).UpdateUser(context.Background(), UserWrite{
		ID:           1,
		GroupID:      99,
		Status:       2,
		Username:     "alice-admin",
		Email:        "alice-admin@example.com",
		Points:       350,
		PasswordHash: "new-hash",
		SID:          "new-sid",
	})
	if err != nil {
		t.Fatal(err)
	}
	if !updated {
		t.Fatal("user was not updated")
	}
	var groupID, points int64
	var status int
	var username, email, passwordHash, sid string
	if err := db.QueryRow(`SELECT group_id, status, username, COALESCE(email, ''), points, password_hash, sid FROM users WHERE id = 1`).
		Scan(&groupID, &status, &username, &email, &points, &passwordHash, &sid); err != nil {
		t.Fatal(err)
	}
	if groupID != 99 || status != 2 || username != "alice-admin" || email != "alice-admin@example.com" || points != 350 || passwordHash != "new-hash" || sid != "new-sid" {
		t.Fatalf("unexpected user after update: group=%d status=%d username=%s email=%s points=%d hash=%s sid=%s", groupID, status, username, email, points, passwordHash, sid)
	}
}

func TestAdminRepositoryFindDomainConflict(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('Cloudflare', '{}'), ('Aliyun', '{}')`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO domains(id, provider_key, remote_zone_id, domain, group_policy, record_types)
		VALUES (1, 'Cloudflare', 'zone-1', 'example.com', '0', 'A')`); err != nil {
		t.Fatal(err)
	}
	repo := NewAdminRepository(db)

	conflict, found, err := repo.FindDomainConflict(context.Background(), DomainWrite{
		ProviderKey: "Aliyun", RemoteZoneID: "zone-2", Domain: "example.com",
	})
	if err != nil {
		t.Fatal(err)
	}
	if !found || conflict.ID != 1 || conflict.Domain != "example.com" {
		t.Fatalf("domain conflict not detected: found=%v conflict=%#v", found, conflict)
	}

	conflict, found, err = repo.FindDomainConflict(context.Background(), DomainWrite{
		ProviderKey: "Cloudflare", RemoteZoneID: "zone-1", Domain: "other.example",
	})
	if err != nil {
		t.Fatal(err)
	}
	if !found || conflict.RemoteZoneID != "zone-1" {
		t.Fatalf("provider zone conflict not detected: found=%v conflict=%#v", found, conflict)
	}

	_, found, err = repo.FindDomainConflict(context.Background(), DomainWrite{
		ID: 1, ProviderKey: "Cloudflare", RemoteZoneID: "zone-1", Domain: "example.com",
	})
	if err != nil {
		t.Fatal(err)
	}
	if found {
		t.Fatal("current domain should not conflict with itself")
	}
}

func TestAdminRepositoryProtectsTurnstileSettings(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	repo := NewAdminRepository(db)
	const secret = "test-secret"
	if err := repo.UpsertSettings(context.Background(), map[string]string{
		"array_turnstile": `{"site_key":"site-key","secret_key":"turnstile-secret","register_enabled":"1","login_enabled":"1"}`,
	}, secret); err != nil {
		t.Fatal(err)
	}
	var stored string
	if err := db.QueryRow(`SELECT value FROM settings WHERE key = 'array_turnstile'`).Scan(&stored); err != nil {
		t.Fatal(err)
	}
	if strings.Contains(stored, "turnstile-secret") || strings.Contains(stored, `"secret_key"`) {
		t.Fatalf("stored turnstile settings leaked secret: %s", stored)
	}
	items, err := repo.ListSettings(context.Background(), secret)
	if err != nil {
		t.Fatal(err)
	}
	found := false
	for _, item := range items {
		if item.Key != "array_turnstile" {
			continue
		}
		found = true
		if strings.Contains(item.Value, "turnstile-secret") || strings.Contains(item.Value, "secret_key_ciphertext") {
			t.Fatalf("listed turnstile settings leaked secret: %s", item.Value)
		}
		if !strings.Contains(item.Value, `"secret_configured":"1"`) {
			t.Fatalf("listed turnstile settings missing configured flag: %s", item.Value)
		}
	}
	if !found {
		t.Fatal("array_turnstile setting was not listed")
	}
	if err := repo.UpsertSettings(context.Background(), map[string]string{
		"array_turnstile": `{"site_key":"site-key","secret_key":"","register_enabled":"1","login_enabled":"0"}`,
	}, secret); err != nil {
		t.Fatal(err)
	}
	cfg, err := NewSettingsRepository(db).TurnstileSettings(context.Background(), secret)
	if err != nil {
		t.Fatal(err)
	}
	if cfg.SecretKey != "turnstile-secret" || !cfg.RegisterEnabled || cfg.LoginEnabled {
		t.Fatalf("turnstile settings did not preserve existing secret: %#v", cfg)
	}
}

func TestRecordRepositorySyncDomainRecordsUsesSystemUserAndSkipsExisting(t *testing.T) {
	db := testMigratedDB(t)
	defer db.Close()
	if _, err := db.Exec(`INSERT INTO dns_providers(key, config_ciphertext) VALUES ('fake', '{}')`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO domains(id, provider_key, remote_zone_id, domain, group_policy, record_types) VALUES (1, 'fake', 'z1', 'example.com', '0', 'A,CNAME')`); err != nil {
		t.Fatal(err)
	}
	repo := NewRecordRepository(db)
	result, err := repo.SyncDomainRecords(context.Background(), models.Domain{ID: 1, Domain: "example.com", ProviderKey: "fake"}, []SyncedRecordInput{
		{RecordID: "remote-1", Name: "www", Type: "A", Value: "1.1.1.1", LineID: "0", Line: "默认"},
		{RecordID: "remote-1", Name: "www", Type: "A", Value: "1.1.1.1", LineID: "0", Line: "默认"},
	}, models.OperationLog{Source: "admin", Action: "domain.sync_records", Message: "sync"})
	if err != nil {
		t.Fatal(err)
	}
	if result.Total != 2 || result.Created != 1 || result.Skipped != 1 {
		t.Fatalf("sync result = %#v", result)
	}
	var uid int64
	var subdomainID int64
	if err := db.QueryRow(`SELECT uid, COALESCE(subdomain_id, 0) FROM records WHERE did = 1 AND name = 'www'`).Scan(&uid, &subdomainID); err != nil {
		t.Fatal(err)
	}
	if uid != 0 {
		t.Fatalf("synced record uid = %d, want 0", uid)
	}
	if subdomainID == 0 {
		t.Fatal("synced record should be attached to a system subdomain")
	}
}

func testMigratedDB(t *testing.T) *sql.DB {
	t.Helper()
	db, err := sql.Open("sqlite", filepath.Join(t.TempDir(), "kldns.db"))
	if err != nil {
		t.Fatal(err)
	}
	if err := ConfigureSQLite(db, 1000, false); err != nil {
		t.Fatal(err)
	}
	if err := RunMigrations(db, "../migrations"); err != nil {
		t.Fatal(err)
	}
	return db
}

func seedAPIUser(t *testing.T, db *sql.DB) {
	t.Helper()
	_, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, email, points)
		VALUES (1, 100, 2, 'alice', 'hash', 'sid', 'alice@example.com', 100)`)
	if err != nil {
		t.Fatal(err)
	}
}
