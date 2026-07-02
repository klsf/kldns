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
	sessionPlain, apiPlain := setupAuthTokens(t, 100)

	if got := authSourceForToken(t, sessionPlain); got != AuthSourceWeb {
		t.Fatalf("session source = %q, want %q", got, AuthSourceWeb)
	}
	if got := authSourceForToken(t, apiPlain); got != AuthSourceAPI {
		t.Fatalf("api token source = %q, want %q", got, AuthSourceAPI)
	}
}

func TestOpenAPIAccessOnlyRestrictsAPITokenRoutes(t *testing.T) {
	gin.SetMode(gin.TestMode)
	sessionPlain, apiPlain := setupAuthTokens(t, 99)

	router := gin.New()
	authGroup := router.Group("/api", APIBearerAuth(), OpenAPIAccessOnly())
	authGroup.GET("/subdomains", func(ctx *gin.Context) { ctx.String(http.StatusOK, "subdomains") })
	authGroup.POST("/subdomains", func(ctx *gin.Context) { ctx.String(http.StatusOK, "register") })
	authGroup.GET("/records", func(ctx *gin.Context) { ctx.String(http.StatusOK, "records") })
	authGroup.POST("/records", func(ctx *gin.Context) { ctx.String(http.StatusOK, "create") })
	authGroup.PUT("/records/:id", func(ctx *gin.Context) { ctx.String(http.StatusOK, "update") })
	authGroup.DELETE("/records/:id", func(ctx *gin.Context) { ctx.String(http.StatusOK, "delete") })
	authGroup.GET("/auth/me", func(ctx *gin.Context) { ctx.String(http.StatusOK, "me") })
	adminGroup := router.Group("/api/admin", APIBearerAuth(), OpenAPIAccessOnly(), AdminOnly())
	adminGroup.GET("/users", func(ctx *gin.Context) { ctx.String(http.StatusOK, "users") })

	for _, tc := range []struct {
		method string
		path   string
		status int
	}{
		{http.MethodGet, "/api/subdomains", http.StatusOK},
		{http.MethodPost, "/api/records", http.StatusOK},
		{http.MethodPut, "/api/records/1", http.StatusOK},
		{http.MethodDelete, "/api/records/1", http.StatusOK},
		{http.MethodGet, "/api/auth/me", http.StatusForbidden},
		{http.MethodPost, "/api/subdomains", http.StatusForbidden},
		{http.MethodGet, "/api/records", http.StatusForbidden},
		{http.MethodGet, "/api/admin/users", http.StatusForbidden},
	} {
		if got := statusForToken(router, tc.method, tc.path, apiPlain); got != tc.status {
			t.Fatalf("api token %s %s status = %d, want %d", tc.method, tc.path, got, tc.status)
		}
	}

	if got := statusForToken(router, http.MethodGet, "/api/auth/me", sessionPlain); got != http.StatusOK {
		t.Fatalf("session /auth/me status = %d, want %d", got, http.StatusOK)
	}
	if got := statusForToken(router, http.MethodGet, "/api/admin/users", sessionPlain); got != http.StatusOK {
		t.Fatalf("session /admin/users status = %d, want %d", got, http.StatusOK)
	}
}

func setupAuthTokens(t *testing.T, groupID int64) (string, string) {
	t.Helper()
	db, err := repositories.OpenSQLite(filepath.Join(t.TempDir(), "kldns.db"), 1000, false)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })
	if err := repositories.RunMigrations(db.SQLDB(), "../migrations"); err != nil {
		t.Fatal(err)
	}
	app.SetDB(db)
	t.Cleanup(func() { app.SetDB(nil) })
	if _, err := db.Exec(`INSERT INTO users(id, group_id, status, username, password_hash, sid, points)
		VALUES (1, ?, 2, 'alice', 'hash', 'sid', 100)`, groupID); err != nil {
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
	return sessionPlain, apiPlain
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

func statusForToken(router *gin.Engine, method string, path string, token string) int {
	rec := httptest.NewRecorder()
	req := httptest.NewRequest(method, path, nil)
	req.Header.Set("Authorization", "Bearer "+token)
	router.ServeHTTP(rec, req)
	return rec.Code
}
