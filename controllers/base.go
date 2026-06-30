package controllers

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"kldns/dto"
	"kldns/middlewares"
	"kldns/models"
	apperrors "kldns/pkg/errors"

	"github.com/beego/beego/v2/server/web"
)

type APIController struct {
	web.Controller
}

func (c *APIController) OK(data any) {
	c.Data["json"] = dto.Response[any]{Code: string(apperrors.CodeOK), Message: "", Data: data}
	_ = c.ServeJSON()
}

func (c *APIController) Fail(status int, code apperrors.Code, message string) {
	c.Ctx.Output.SetStatus(status)
	c.Data["json"] = dto.Response[any]{Code: string(code), Message: message}
	_ = c.ServeJSON()
}

func (c *APIController) Internal(message string) {
	c.Fail(http.StatusInternalServerError, apperrors.CodeInternal, message)
}

func (c *APIController) BindJSON(dst any) bool {
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, dst); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return false
	}
	return true
}

func (c *APIController) FailApp(err *apperrors.AppError) {
	if err == nil {
		return
	}
	c.Fail(statusForCode(err.Code), err.Code, err.Message)
}

func (c *APIController) CurrentUser() (models.User, bool) {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
	}
	return user, ok
}

func (c *APIController) PathInt64(key string) int64 {
	value, _ := strconv.ParseInt(strings.TrimSpace(c.Ctx.Input.Param(key)), 10, 64)
	return value
}
