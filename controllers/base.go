package controllers

import (
	"bytes"
	"encoding/json"
	"io"
	"net/http"
	"strconv"
	"strings"

	"kldns/dto"
	"kldns/middleware"
	"kldns/models"
	apperrors "kldns/pkg/errors"

	"github.com/gin-gonic/gin"
)

type APIController struct {
	Ctx *gin.Context
}

const rawBodyKey = "kldns_raw_body"

func (c *APIController) SetContext(ctx *gin.Context) {
	c.Ctx = ctx
}

func (c *APIController) OK(data any) {
	c.Ctx.JSON(http.StatusOK, dto.Response[any]{Code: string(apperrors.CodeOK), Message: "", Data: data})
}

func (c *APIController) Fail(status int, code apperrors.Code, message string) {
	c.Ctx.JSON(status, dto.Response[any]{Code: string(code), Message: message})
}

func (c *APIController) Internal(message string) {
	c.Fail(http.StatusInternalServerError, apperrors.CodeInternal, message)
}

func (c *APIController) BindJSON(dst any) bool {
	if err := json.Unmarshal(c.RawBody(), dst); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return false
	}
	return true
}

func (c *APIController) RawBody() []byte {
	if c.Ctx == nil || c.Ctx.Request == nil {
		return nil
	}
	if value, ok := c.Ctx.Get(rawBodyKey); ok {
		if body, ok := value.([]byte); ok {
			return body
		}
	}
	if c.Ctx.Request.Body == nil {
		return nil
	}
	body, err := io.ReadAll(c.Ctx.Request.Body)
	if err != nil {
		return nil
	}
	c.Ctx.Request.Body = io.NopCloser(bytes.NewBuffer(body))
	c.Ctx.Set(rawBodyKey, body)
	return body
}

func (c *APIController) FailApp(err *apperrors.AppError) {
	if err == nil {
		return
	}
	c.Fail(statusForCode(err.Code), err.Code, err.Message)
}

func (c *APIController) CurrentUser() (models.User, bool) {
	user, ok := middleware.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
	}
	return user, ok
}

func (c *APIController) PathInt64(key string) int64 {
	key = strings.TrimPrefix(key, ":")
	value, _ := strconv.ParseInt(strings.TrimSpace(c.Ctx.Param(key)), 10, 64)
	return value
}

func (c *APIController) GetString(key string, defaults ...string) string {
	value := c.Ctx.Query(key)
	if value == "" && len(defaults) > 0 {
		return defaults[0]
	}
	return value
}
