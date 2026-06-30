package controllers

import (
	"kldns/app"
	"kldns/repositories"
)

type SettingsAPIController struct {
	APIController
}

func (c *SettingsAPIController) DNSPolicy() {
	allowed, err := repositories.NewSettingsRepository(app.DB()).AllowUnlimitedSubdomainRecords(c.Ctx.Request.Context())
	if err != nil {
		c.Internal("获取解析策略失败")
		return
	}
	c.OK(map[string]any{"unlimited_subdomain_records": allowed})
}

func (c *SettingsAPIController) Turnstile() {
	cfg, err := repositories.NewSettingsRepository(app.DB()).TurnstileSettings(c.Ctx.Request.Context(), appSecret())
	if err != nil {
		c.Internal("获取人机验证配置失败")
		return
	}
	c.OK(map[string]any{
		"site_key":         cfg.SiteKey,
		"register_enabled": cfg.RegisterEnabled,
		"login_enabled":    cfg.LoginEnabled,
	})
}
