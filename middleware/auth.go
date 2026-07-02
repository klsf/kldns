package middleware

import (
	stdctx "context"
	"net/http"
	"strings"

	"kldns/app"
	"kldns/dto"
	"kldns/models"
	"kldns/pkg/auth"
	"kldns/repositories"

	"github.com/gin-gonic/gin"
)

type contextKey string

const userContextKey contextKey = "auth_user"
const authSourceContextKey contextKey = "auth_source"
const userGinKey = "auth_user"
const authSourceGinKey = "auth_source"

const (
	AuthSourceWeb = "web"
	AuthSourceAPI = "api"
)

func APIBearerAuth() gin.HandlerFunc {
	return func(ctx *gin.Context) {
		header := ctx.GetHeader("Authorization")
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
		source := AuthSourceWeb
		if err != nil {
			token, err = repo.AuthenticateToken(ctx.Request.Context(), tokenHash)
			source = AuthSourceAPI
		}
		if err != nil {
			writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "令牌不存在或已失效")
			return
		}
		reqCtx := stdctx.WithValue(ctx.Request.Context(), userContextKey, token.User)
		reqCtx = stdctx.WithValue(reqCtx, authSourceContextKey, source)
		ctx.Request = ctx.Request.WithContext(reqCtx)
		ctx.Set(userGinKey, token.User)
		ctx.Set(authSourceGinKey, source)
		ctx.Next()
	}
}

func UserFromContext(ctx stdctx.Context) (models.User, bool) {
	user, ok := ctx.Value(userContextKey).(models.User)
	return user, ok
}

func SourceFromContext(ctx stdctx.Context) string {
	source, _ := ctx.Value(authSourceContextKey).(string)
	if source == AuthSourceAPI {
		return AuthSourceAPI
	}
	return AuthSourceWeb
}

func OpenAPIAccessOnly() gin.HandlerFunc {
	return func(ctx *gin.Context) {
		if SourceFromContext(ctx.Request.Context()) != AuthSourceAPI {
			ctx.Next()
			return
		}
		if !isOpenAPIAllowedRoute(ctx.Request.Method, ctx.FullPath()) {
			writeAuthError(ctx, http.StatusForbidden, "FORBIDDEN", "API Token 仅允许管理解析记录和查询已注册二级域名")
			return
		}
		ctx.Next()
	}
}

func isOpenAPIAllowedRoute(method string, fullPath string) bool {
	switch method {
	case http.MethodGet:
		return fullPath == "/api/subdomains"
	case http.MethodPost:
		return fullPath == "/api/records"
	case http.MethodPut, http.MethodDelete:
		return fullPath == "/api/records/:id"
	default:
		return false
	}
}

func AdminOnly() gin.HandlerFunc {
	return func(ctx *gin.Context) {
		user, ok := UserFromContext(ctx.Request.Context())
		if !ok {
			writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "未登录")
			return
		}
		if user.GroupID != 99 {
			writeAuthError(ctx, http.StatusForbidden, "FORBIDDEN", "无后台权限")
			return
		}
		ctx.Next()
	}
}

func NoSniff() gin.HandlerFunc {
	return func(ctx *gin.Context) {
		ctx.Header("X-Content-Type-Options", "nosniff")
		ctx.Next()
	}
}

func writeAuthError(ctx *gin.Context, status int, code string, message string) {
	ctx.AbortWithStatusJSON(status, dto.Response[any]{
		Code:    code,
		Message: message,
	})
}
