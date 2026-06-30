package controllers

import (
	"encoding/json"
	"net/http"
	"strings"
	"time"

	"kldns/app"
	"kldns/middlewares"
	"kldns/models"
	"kldns/pkg/auth"
	apperrors "kldns/pkg/errors"
	"kldns/pkg/turnstile"
	"kldns/pkg/validation"
	"kldns/repositories"
)

type AuthController struct {
	APIController
}

func (c *AuthController) Register() {
	var input struct {
		Username       string `json:"username"`
		Email          string `json:"email"`
		Password       string `json:"password"`
		TurnstileToken string `json:"turnstile_token"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	input.Username = strings.TrimSpace(input.Username)
	input.Email = strings.ToLower(strings.TrimSpace(input.Email))
	if len(input.Username) < 4 {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "用户名太短")
		return
	}
	if input.Email != "" && !validation.IsValidEmail(input.Email) {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "邮箱格式不正确")
		return
	}
	if len(input.Password) < 8 {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "密码至少 8 位")
		return
	}
	repo := repositories.NewAuthRepository(app.DB())
	settings, err := repo.UserSettings(c.Ctx.Request.Context())
	if err != nil {
		c.Internal("读取注册配置失败")
		return
	}
	if !settings.RegisterOpen {
		c.Fail(http.StatusForbidden, apperrors.CodeForbidden, "暂时关闭注册")
		return
	}
	if appErr := c.verifyTurnstile(input.TurnstileToken, "register"); appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	passwordHash, err := auth.HashPassword(input.Password)
	if err != nil {
		c.Internal("生成密码哈希失败")
		return
	}
	status := 2
	if settings.ReviewMode == "manual" {
		status = 1
	}
	id, err := repo.CreateUser(c.Ctx.Request.Context(), models.User{
		GroupID: 100, Status: status, Username: input.Username, Email: input.Email, Points: settings.InitialPoints,
	}, passwordHash, randomSID())
	if err != nil {
		c.Fail(http.StatusConflict, apperrors.CodeConflict, "用户名或邮箱已被注册")
		return
	}
	c.OK(map[string]any{"id": id, "status": status, "review_required": status == 1})
}

func (c *AuthController) Login() {
	c.login(false)
}

func (c *AuthController) AdminLogin() {
	c.login(true)
}

func (c *AuthController) login(adminOnly bool) {
	var input struct {
		Login          string `json:"login"`
		Username       string `json:"username"`
		Password       string `json:"password"`
		TurnstileToken string `json:"turnstile_token"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	login := strings.TrimSpace(input.Login)
	if login == "" {
		login = strings.TrimSpace(input.Username)
	}
	if appErr := c.verifyTurnstile(input.TurnstileToken, "login"); appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	user, passwordHash, err := repositories.NewAuthRepository(app.DB()).FindLoginUser(c.Ctx.Request.Context(), login)
	if err != nil || !auth.CheckPassword(input.Password, passwordHash) {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "账号或者密码不正确")
		return
	}
	if user.Status == 0 {
		c.Fail(http.StatusForbidden, apperrors.CodeForbidden, "账户已被禁用")
		return
	}
	if adminOnly && user.GroupID != 99 {
		c.Fail(http.StatusForbidden, apperrors.CodeForbidden, "无后台权限")
		return
	}
	plain, hash, hint, err := auth.NewAPIToken()
	if err != nil {
		c.Internal("生成登录令牌失败")
		return
	}
	id, err := repositories.NewAPIRepository(app.DB()).CreateSession(c.Ctx.Request.Context(), user.ID, hash, hint, time.Now().Add(30*24*time.Hour).Unix())
	if err != nil {
		c.Internal("保存登录令牌失败")
		return
	}
	c.OK(map[string]any{
		"token_id": id,
		"token":    plain,
		"user":     user,
	})
}

func (c *AuthController) verifyTurnstile(token string, scene string) *apperrors.AppError {
	cfg, err := repositories.NewSettingsRepository(app.DB()).TurnstileSettings(c.Ctx.Request.Context(), appSecret())
	if err != nil {
		return apperrors.Wrap(apperrors.CodeInternal, "读取人机验证配置失败", err)
	}
	enabled := false
	switch scene {
	case "register":
		enabled = cfg.RegisterEnabled
	case "login":
		enabled = cfg.LoginEnabled
	}
	if !enabled {
		return nil
	}
	if strings.TrimSpace(cfg.SiteKey) == "" || strings.TrimSpace(cfg.SecretKey) == "" {
		return apperrors.New(apperrors.CodeInvalidArgument, "人机验证配置不完整")
	}
	if err := (turnstile.Client{}).Verify(c.Ctx.Request.Context(), cfg.SecretKey, token, c.Ctx.Input.IP()); err != nil {
		return apperrors.Wrap(apperrors.CodeForbidden, "人机验证失败，请重试", err)
	}
	return nil
}

func (c *AuthController) Me() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	c.OK(user)
}

func (c *AuthController) ChangePassword() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	var input struct {
		OldPassword string `json:"old_password"`
		NewPassword string `json:"new_password"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	_, passwordHash, err := repositories.NewAuthRepository(app.DB()).FindLoginUser(c.Ctx.Request.Context(), user.Username)
	if err != nil || !auth.CheckPassword(input.OldPassword, passwordHash) {
		c.Fail(http.StatusForbidden, apperrors.CodeForbidden, "旧密码验证失败")
		return
	}
	if len(input.NewPassword) < 8 {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "新密码至少 8 位")
		return
	}
	nextHash, err := auth.HashPassword(input.NewPassword)
	if err != nil {
		c.Internal("生成密码哈希失败")
		return
	}
	if err := repositories.NewAuthRepository(app.DB()).UpdatePassword(c.Ctx.Request.Context(), user.ID, nextHash, randomSID()); err != nil {
		c.Internal("修改密码失败")
		return
	}
	c.OK(map[string]any{"changed": true})
}
