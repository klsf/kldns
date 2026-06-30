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
const userGinKey = "auth_user"

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
		if err != nil {
			token, err = repo.AuthenticateToken(ctx.Request.Context(), tokenHash)
		}
		if err != nil {
			writeAuthError(ctx, http.StatusUnauthorized, "UNAUTHORIZED", "令牌不存在或已失效")
			return
		}
		ctx.Request = ctx.Request.WithContext(stdctx.WithValue(ctx.Request.Context(), userContextKey, token.User))
		ctx.Set(userGinKey, token.User)
		ctx.Next()
	}
}

func UserFromContext(ctx stdctx.Context) (models.User, bool) {
	user, ok := ctx.Value(userContextKey).(models.User)
	return user, ok
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
