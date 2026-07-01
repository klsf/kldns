package middleware

import (
	"context"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"testing"

	"kldns/app"
	"kldns/pkg/auth"
	"kldns/repositories"

	"github.com/gin-gonic/gin"
)

func TestAPIBearerAuthSetsSourceForSessionAndAPIToken(t *testing.T) {
	gin.SetMode(gin.TestMode)
	db, err := repositories.OpenSQLite(filepath.Join(t.TempDir(), "kldns.db"), 1000, false)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	if err := repositories.RunMigrations(db.SQLDB(), "../migrations"); err != nil {
		t.Fatal(err)
	}
	app.SetDB(db)
	t.Cleanup(func() { app.SetDB(nil) })
	if _, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, points)
		VALUES (1, 100, 2, 'alice', 'hash', 'sid', 100)`); err != nil {
		t.Fatal(err)
	}
	repo := repositories.NewAPIRepository(db)
	sessionPlain := "session-token"
	apiPlain := "api-token"
	if _, err := repo.CreateSession(context.Background(), 1, auth.HashBearerToken(sessionPlain), "session", 0); err != nil {
		t.Fatal(err)
	}
	if _, err := repo.CreateToken(context.Background(), 1, "default", auth.HashBearerToken(apiPlain), "api", 0); err != nil {
		t.Fatal(err)
	}

	if got := authSourceForToken(t, sessionPlain); got != AuthSourceWeb {
		t.Fatalf("session source = %q, want %q", got, AuthSourceWeb)
	}
	if got := authSourceForToken(t, apiPlain); got != AuthSourceAPI {
		t.Fatalf("api token source = %q, want %q", got, AuthSourceAPI)
	}
}

func authSourceForToken(t *testing.T, token string) string {
	t.Helper()
	router := gin.New()
	router.GET("/me", APIBearerAuth(), func(ctx *gin.Context) {
		ctx.String(http.StatusOK, SourceFromContext(ctx.Request.Context()))
	})
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/me", nil)
	req.Header.Set("Authorization", "Bearer "+token)
	router.ServeHTTP(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("status = %d body = %s", rec.Code, rec.Body.String())
	}
	return rec.Body.String()
}
