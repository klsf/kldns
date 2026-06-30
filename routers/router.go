package routers

import (
	"kldns/controllers"
	"kldns/middlewares"

	"github.com/beego/beego/v2/server/web"
	"github.com/beego/beego/v2/server/web/context"
)

func init() {
	web.SetStaticPath("/assets", "web/dist/assets")
	web.Router("/", &controllers.SPAController{}, "get:Index")
	web.Router("/favicon.svg", &controllers.SPAController{}, "get:Favicon")

	web.Router("/api/v1/health", &controllers.HealthController{}, "get:Get")
	web.Router("/api/v1/install/admin", &controllers.InstallController{}, "post:CreateAdmin")
	web.Router("/api/v1/auth/register", &controllers.AuthController{}, "post:Register")
	web.Router("/api/v1/auth/login", &controllers.AuthController{}, "post:Login")
	web.Router("/api/v1/admin/auth/login", &controllers.AuthController{}, "post:AdminLogin")
	web.Router("/api/v1/public/domains", &controllers.DomainAPIController{}, "get:Public")
	web.InsertFilter("/api/v1/auth/me", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/auth/password", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/domains", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/settings/dns-policy", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/subdomains", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/subdomains/*", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/records", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/records/*", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/tokens", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/tokens/*", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/admin/*", web.BeforeRouter, middlewares.APIBearerAuth)
	web.InsertFilter("/api/v1/admin/*", web.BeforeRouter, middlewares.AdminOnly)

	web.Router("/api/v1/domains", &controllers.DomainAPIController{}, "get:Get")
	web.Router("/api/v1/settings/dns-policy", &controllers.SettingsAPIController{}, "get:DNSPolicy")
	web.Router("/api/v1/settings/turnstile", &controllers.SettingsAPIController{}, "get:Turnstile")
	web.Router("/api/v1/subdomains", &controllers.SubdomainAPIController{}, "get:Get;post:Post")
	web.Router("/api/v1/subdomains/:id", &controllers.SubdomainAPIController{}, "delete:Delete")
	web.Router("/api/v1/auth/me", &controllers.AuthController{}, "get:Me")
	web.Router("/api/v1/auth/password", &controllers.AuthController{}, "put:ChangePassword")
	web.Router("/api/v1/records", &controllers.RecordAPIController{}, "get:Get;post:Post")
	web.Router("/api/v1/records/:id", &controllers.RecordAPIController{}, "put:Put;delete:Delete")
	web.Router("/api/v1/tokens", &controllers.TokenAPIController{}, "get:Get;post:Post")
	web.Router("/api/v1/tokens/:id", &controllers.TokenAPIController{}, "delete:Delete")
	web.Router("/api/v1/admin/users", &controllers.AdminListController{}, "get:Users")
	web.Router("/api/v1/admin/users/:id", &controllers.AdminListController{}, "put:SaveUser;delete:DeleteUser")
	web.Router("/api/v1/admin/groups", &controllers.AdminListController{}, "get:Groups;post:SaveGroup")
	web.Router("/api/v1/admin/groups/:id", &controllers.AdminListController{}, "delete:DeleteGroup")
	web.Router("/api/v1/admin/domains", &controllers.AdminListController{}, "get:Domains;post:SaveDomain")
	web.Router("/api/v1/admin/domains/:id/sync-records", &controllers.AdminListController{}, "post:SyncDomainRecords")
	web.Router("/api/v1/admin/domains/:id", &controllers.AdminListController{}, "put:SaveDomain;delete:DeleteDomain")
	web.Router("/api/v1/admin/dns-providers", &controllers.AdminListController{}, "get:Providers")
	web.Router("/api/v1/admin/dns-providers/zones", &controllers.AdminListController{}, "post:ProviderZones")
	web.Router("/api/v1/admin/records", &controllers.AdminListController{}, "get:Records;post:SaveRecord")
	web.Router("/api/v1/admin/records/:id", &controllers.AdminListController{}, "put:SaveRecord;delete:DeleteRecord")
	web.Router("/api/v1/admin/subdomains", &controllers.AdminListController{}, "get:Subdomains")
	web.Router("/api/v1/admin/subdomains/:id", &controllers.AdminListController{}, "delete:DeleteSubdomain")
	web.Router("/api/v1/admin/logs", &controllers.AdminListController{}, "get:Logs")
	web.Router("/api/v1/admin/settings", &controllers.AdminListController{}, "get:Settings;put:SaveSettings")
	web.InsertFilter("/api/v1/*", web.FinishRouter, func(ctx *context.Context) {
		ctx.Output.Header("X-Content-Type-Options", "nosniff")
	})
	web.Router("/*", &controllers.SPAController{}, "get:Index")
}
