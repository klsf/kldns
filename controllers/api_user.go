package controllers

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"

	"kldns/app"
	"kldns/middlewares"
	"kldns/models"
	"kldns/pkg/auth"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
	"kldns/repositories"
	"kldns/services"
)

type DomainAPIController struct {
	APIController
}

func (c *DomainAPIController) Get() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	items, err := repositories.NewAPIRepository(app.DB()).ListAvailableDomains(c.Ctx.Request.Context(), user.GroupID, repositories.DomainFilter{
		Keyword: strings.TrimSpace(c.GetString("keyword")),
	})
	if err != nil {
		c.Internal("获取主域失败")
		return
	}
	enrichDomainLines(c.Ctx.Request.Context(), items)
	c.OK(items)
}

func (c *DomainAPIController) Public() {
	items, err := repositories.NewAPIRepository(app.DB()).ListPublicDomains(c.Ctx.Request.Context())
	if err != nil {
		c.Internal("获取公开主域失败")
		return
	}
	c.OK(items)
}

func enrichDomainLines(ctx context.Context, items []repositories.DomainSummary) {
	resolver := services.DBProviderResolver{}
	for i := range items {
		items[i].Line = []dns.RecordLine{{ID: "0", Name: "默认"}}
		provider, err := resolver.Resolve(ctx, models.Domain{
			ID:                       items[i].ID,
			ProviderKey:              items[i].ProviderKey,
			ProviderConfigCiphertext: items[i].ProviderConfigCiphertext,
			RemoteZoneID:             items[i].RemoteZoneID,
			Domain:                   items[i].Domain,
		})
		if err != nil {
			continue
		}
		lines, err := provider.ListRecordLines(ctx, dns.Zone{ID: items[i].RemoteZoneID, Domain: items[i].Domain})
		if err != nil || len(lines) == 0 {
			continue
		}
		items[i].Line = normalizeRecordLines(lines)
	}
}

func normalizeRecordLines(lines []dns.RecordLine) []dns.RecordLine {
	seen := map[string]bool{}
	out := make([]dns.RecordLine, 0, len(lines))
	for _, line := range lines {
		id := strings.TrimSpace(line.ID)
		name := strings.TrimSpace(line.Name)
		if id == "" {
			id = "0"
		}
		if name == "" {
			name = id
		}
		if id == "0" && name == "0" {
			name = "默认"
		}
		if seen[id] {
			continue
		}
		seen[id] = true
		out = append(out, dns.RecordLine{ID: id, Name: name})
	}
	if len(out) == 0 {
		return []dns.RecordLine{{ID: "0", Name: "默认"}}
	}
	return out
}

type RecordAPIController struct {
	APIController
}

func (c *RecordAPIController) Get() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	items, err := repositories.NewAPIRepository(app.DB()).ListRecords(c.Ctx.Request.Context(), user.ID, repositories.RecordFilter{
		DID:         queryInt64(c, "did"),
		SubdomainID: queryInt64(c, "subdomain_id"),
		Type:        strings.TrimSpace(c.GetString("type")),
		Keyword:     strings.TrimSpace(c.GetString("keyword")),
	})
	if err != nil {
		c.Internal("获取记录失败")
		return
	}
	c.OK(items)
}

func (c *RecordAPIController) Post() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	var input struct {
		DID         int64  `json:"did"`
		SubdomainID int64  `json:"subdomain_id"`
		Name        string `json:"name"`
		Type        string `json:"type"`
		Value       string `json:"value"`
		LineID      string `json:"line_id"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	service := services.RecordService{
		Repo:     repositories.NewRecordRepository(app.DB()),
		Resolver: services.DBProviderResolver{},
		Reserved: settingsReserveNames(c.Ctx.Request.Context()),
	}
	result, appErr := service.Submit(c.Ctx.Request.Context(), services.SubmitRecordInput{
		UserID:      user.ID,
		DID:         input.DID,
		SubdomainID: input.SubdomainID,
		Name:        input.Name,
		Type:        input.Type,
		Value:       input.Value,
		LineID:      input.LineID,
		Source:      "api",
	})
	if appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	c.OK(result)
}

func (c *RecordAPIController) Put() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	id, _ := strconv.ParseInt(c.Ctx.Input.Param(":id"), 10, 64)
	var input struct {
		DID         int64  `json:"did"`
		SubdomainID int64  `json:"subdomain_id"`
		Name        string `json:"name"`
		Type        string `json:"type"`
		Value       string `json:"value"`
		LineID      string `json:"line_id"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	service := services.RecordService{
		Repo:     repositories.NewRecordRepository(app.DB()),
		Resolver: services.DBProviderResolver{},
		Reserved: settingsReserveNames(c.Ctx.Request.Context()),
	}
	result, appErr := service.Submit(c.Ctx.Request.Context(), services.SubmitRecordInput{
		UserID: user.ID, ID: id, DID: input.DID, SubdomainID: input.SubdomainID, Name: input.Name,
		Type: input.Type, Value: input.Value, LineID: input.LineID, Source: "api",
	})
	if appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	c.OK(result)
}

func (c *RecordAPIController) Delete() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	id, _ := strconv.ParseInt(c.Ctx.Input.Param(":id"), 10, 64)
	service := services.RecordService{
		Repo:     repositories.NewRecordRepository(app.DB()),
		Resolver: services.DBProviderResolver{},
		Reserved: settingsReserveNames(c.Ctx.Request.Context()),
	}
	result, appErr := service.Delete(c.Ctx.Request.Context(), user.ID, id, "api")
	if appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	c.OK(result)
}

type SubdomainAPIController struct {
	APIController
}

func (c *SubdomainAPIController) Get() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	items, err := repositories.NewAPIRepository(app.DB()).ListSubdomains(c.Ctx.Request.Context(), user.ID)
	if err != nil {
		c.Internal("获取二级域名失败")
		return
	}
	c.OK(items)
}

func (c *SubdomainAPIController) Post() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	var input struct {
		DID  int64  `json:"did"`
		Name string `json:"name"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	service := services.SubdomainService{
		Repo:     repositories.NewRecordRepository(app.DB()),
		Reserved: settingsReserveNames(c.Ctx.Request.Context()),
	}
	result, appErr := service.Register(c.Ctx.Request.Context(), services.RegisterSubdomainInput{
		UserID: user.ID, DID: input.DID, Name: input.Name, Source: "api",
	})
	if appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	c.OK(result)
}

func (c *SubdomainAPIController) Delete() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	id, _ := strconv.ParseInt(c.Ctx.Input.Param(":id"), 10, 64)
	var input struct {
		ConfirmFullDomain string `json:"confirm_full_domain"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	service := services.SubdomainService{
		Repo: repositories.NewRecordRepository(app.DB()),
	}
	result, appErr := service.Delete(c.Ctx.Request.Context(), services.DeleteSubdomainInput{
		UserID: user.ID, ID: id, ConfirmFullDomain: input.ConfirmFullDomain, Source: "api",
	})
	if appErr != nil {
		c.Fail(statusForCode(appErr.Code), appErr.Code, appErr.Message)
		return
	}
	c.OK(result)
}

type TokenAPIController struct {
	APIController
}

func (c *TokenAPIController) Get() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	items, err := repositories.NewAPIRepository(app.DB()).ListTokens(c.Ctx.Request.Context(), user.ID)
	if err != nil {
		c.Internal("获取令牌失败")
		return
	}
	c.OK(items)
}

func (c *TokenAPIController) Post() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	var input struct {
		Name string `json:"name"`
		Days int    `json:"days"`
	}
	if err := json.Unmarshal(c.Ctx.Input.RequestBody, &input); err != nil {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请求 JSON 格式不正确")
		return
	}
	input.Name = strings.TrimSpace(input.Name)
	if input.Name == "" {
		c.Fail(http.StatusBadRequest, apperrors.CodeInvalidArgument, "请输入令牌名称")
		return
	}
	if user.Status != 2 {
		c.Fail(http.StatusForbidden, apperrors.CodeForbidden, "账号待审核，暂不能创建 API Token")
		return
	}
	plain, hash, hint, err := auth.NewAPIToken()
	if err != nil {
		c.Internal("生成令牌失败")
		return
	}
	var expiresAt int64
	if input.Days > 0 {
		expiresAt = time.Now().Add(time.Duration(input.Days) * 24 * time.Hour).Unix()
	}
	id, err := repositories.NewAPIRepository(app.DB()).CreateToken(c.Ctx.Request.Context(), user.ID, input.Name, hash, hint, expiresAt)
	if err != nil {
		c.Internal("创建令牌失败")
		return
	}
	c.OK(map[string]any{"id": id, "token": plain, "token_hint": hint, "expires_at": expiresAt})
}

func (c *TokenAPIController) Delete() {
	user, ok := middlewares.UserFromContext(c.Ctx.Request.Context())
	if !ok {
		c.Fail(http.StatusUnauthorized, apperrors.CodeUnauthorized, "未登录")
		return
	}
	id, _ := strconv.ParseInt(c.Ctx.Input.Param(":id"), 10, 64)
	deleted, err := repositories.NewAPIRepository(app.DB()).DeleteToken(c.Ctx.Request.Context(), user.ID, id)
	if err != nil {
		c.Internal("删除令牌失败")
		return
	}
	if !deleted {
		c.Fail(http.StatusNotFound, apperrors.CodeNotFound, "令牌不存在")
		return
	}
	c.OK(map[string]any{"deleted": true})
}

func statusForCode(code apperrors.Code) int {
	switch code {
	case apperrors.CodeInvalidArgument:
		return http.StatusBadRequest
	case apperrors.CodeUnauthorized:
		return http.StatusUnauthorized
	case apperrors.CodeForbidden:
		return http.StatusForbidden
	case apperrors.CodeNotFound:
		return http.StatusNotFound
	case apperrors.CodeConflict:
		return http.StatusConflict
	case apperrors.CodeInsufficientPoints:
		return http.StatusPaymentRequired
	case apperrors.CodeDNSProviderFailed:
		return http.StatusBadGateway
	default:
		return http.StatusInternalServerError
	}
}
