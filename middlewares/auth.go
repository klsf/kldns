package middlewares

import (
	stdctx "context"
	"net/http"
	"strings"

	"kldns/app"
	"kldns/models"
	"kldns/pkg/auth"
	"kldns/repositories"

	beegoctx "github.com/beego/beego/v2/server/web/context"
)

type contextKey string

const userContextKey contextKey = "auth_user"

func APIBearerAuth(ctx *beegoctx.Context) {
	if isPublicAdminLogin(ctx) {
		return
	}
	header := ctx.Input.Header("Authorization")
	if !strings.HasPrefix(header, "Bearer ") {
		writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "缺少 Bearer Token")
		return
	}
	db := app.DB()
	if db == nil {
		writeAuthError(ctx, http.StatusInternalServerError, "INTERNAL", "数据库未初始化")
		return
	}
	tokenHash := auth.HashBearerToken(strings.TrimPrefix(header, "Bearer "))
	repo := repositories.NewAPIRepository(db)
	token, err := repo.AuthenticateSession(ctx.Request.Context(), tokenHash)
	if err != nil {
		token, err = repo.AuthenticateToken(ctx.Request.Context(), tokenHash)
	}
	if err != nil {
		writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "令牌不存在或已失效")
		return
	}
	ctx.Request = ctx.Request.WithContext(stdctx.WithValue(ctx.Request.Context(), userContextKey, token.User))
}

func UserFromContext(ctx stdctx.Context) (models.User, bool) {
	user, ok := ctx.Value(userContextKey).(models.User)
	return user, ok
}

func AdminOnly(ctx *beegoctx.Context) {
	if isPublicAdminLogin(ctx) {
		return
	}
	user, ok := UserFromContext(ctx.Request.Context())
	if !ok {
		writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "未登录")
		return
	}
	if user.GroupID != 99 {
		writeAuthError(ctx, http.StatusForbidden, "FORBIDDEN", "无后台权限")
		return
	}
}

func writeAuthError(ctx *beegoctx.Context, status int, code string, message string) {
	ctx.Output.SetStatus(status)
	_ = ctx.Output.JSON(map[string]any{
		"code":    code,
		"message": message,
	}, false, false)
}

func isPublicAdminLogin(ctx *beegoctx.Context) bool {
	return ctx.Request != nil && ctx.Request.URL != nil && ctx.Request.URL.Path == "/api/v1/admin/auth/login"
}
