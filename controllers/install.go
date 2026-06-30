package controllers

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"strings"

	"kldns/app"
	"kldns/pkg/auth"
	apperrors "kldns/pkg/errors"
	"kldns/repositories"
)

type InstallController struct {
	APIController
}

const (
	defaultInstallAdminUsername = "admin"
	defaultInstallAdminPassword = "123456"
)

func (c *InstallController) CreateAdmin() {
	var input struct {
		Username string `json:"username"`
		Password string `json:"password"`
		Email    string `json:"email"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	input.Username = strings.TrimSpace(input.Username)
	if input.Username == "" {
		input.Username = defaultInstallAdminUsername
	}
	if input.Password == "" {
		input.Password = defaultInstallAdminPassword
	}
	input.Email = strings.TrimSpace(strings.ToLower(input.Email))
	if len(input.Username) < 4 {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "用户名太短")
		return
	}
	if len(input.Password) < 8 && input.Password != defaultInstallAdminPassword {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "密码至少 8 位")
		return
	}
	repo := repositories.NewInstallRepository(app.DB())
	count, err := repo.UserCount(c.Ctx.Request.Context())
	if err != nil {
		c.Internal("检查安装状态失败")
		return
	}
	if count > 0 {
		c.Fail(http.StatusConflict, apperrors.CodeConflict, "系统已初始化")
		return
	}
	passwordHash, err := auth.HashPassword(input.Password)
	if err != nil {
		c.Internal("生成密码哈希失败")
		return
	}
	id, err := repo.CreateAdmin(c.Ctx.Request.Context(), input.Username, passwordHash, input.Email, randomSID())
	if err != nil {
		c.Internal("创建管理员失败")
		return
	}
	c.OK(map[string]any{"id": id, "username": input.Username})
}

func randomSID() string {
	buf := make([]byte, 16)
	if _, err := rand.Read(buf); err != nil {
		return "install-admin"
	}
	return hex.EncodeToString(buf)
}
