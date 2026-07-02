package routes

import (
	"kldns/controllers"
	"kldns/middleware"

	"github.com/gin-gonic/gin"
)

func NewRouter() *gin.Engine {
	router := gin.New()
	router.Use(gin.Logger(), gin.Recovery())

	router.GET("/", spaHandler((*controllers.SPAController).Index))
	router.GET("/favicon.svg", spaHandler((*controllers.SPAController).Favicon))
	router.GET("/assets/*filepath", spaHandler((*controllers.SPAController).Asset))

	api := router.Group("/api", middleware.NoSniff())
	{
		api.GET("/health", apiHandler((*controllers.HealthController).Get))
		api.POST("/install/admin", apiHandler((*controllers.InstallController).CreateAdmin))
		api.POST("/auth/register", apiHandler((*controllers.AuthController).Register))
		api.POST("/auth/login", apiHandler((*controllers.AuthController).Login))
		api.POST("/admin/auth/login", apiHandler((*controllers.AuthController).AdminLogin))
		api.GET("/public/domains", apiHandler((*controllers.DomainAPIController).Public))
		api.GET("/settings/turnstile", apiHandler((*controllers.SettingsAPIController).Turnstile))

		auth := api.Group("", middleware.APIBearerAuth(), middleware.OpenAPIAccessOnly())
		{
			auth.GET("/auth/me", apiHandler((*controllers.AuthController).Me))
			auth.PUT("/auth/password", apiHandler((*controllers.AuthController).ChangePassword))
			auth.GET("/domains", apiHandler((*controllers.DomainAPIController).Get))
			auth.GET("/settings/dns-policy", apiHandler((*controllers.SettingsAPIController).DNSPolicy))
			auth.GET("/subdomains", apiHandler((*controllers.SubdomainAPIController).Get))
			auth.POST("/subdomains", apiHandler((*controllers.SubdomainAPIController).Post))
			auth.DELETE("/subdomains/:id", apiHandler((*controllers.SubdomainAPIController).Delete))
			auth.GET("/records", apiHandler((*controllers.RecordAPIController).Get))
			auth.POST("/records", apiHandler((*controllers.RecordAPIController).Post))
			auth.PUT("/records/:id", apiHandler((*controllers.RecordAPIController).Put))
			auth.DELETE("/records/:id", apiHandler((*controllers.RecordAPIController).Delete))
			auth.GET("/points", apiHandler((*controllers.PointsAPIController).Get))
			auth.GET("/tokens", apiHandler((*controllers.TokenAPIController).Get))
			auth.POST("/tokens", apiHandler((*controllers.TokenAPIController).Post))
			auth.DELETE("/tokens/:id", apiHandler((*controllers.TokenAPIController).Delete))
		}

		admin := api.Group("/admin", middleware.APIBearerAuth(), middleware.OpenAPIAccessOnly(), middleware.AdminOnly())
		{
			admin.GET("/users", apiHandler((*controllers.AdminListController).Users))
			admin.PUT("/users/:id", apiHandler((*controllers.AdminListController).SaveUser))
			admin.DELETE("/users/:id", apiHandler((*controllers.AdminListController).DeleteUser))
			admin.POST("/users/:id/points", apiHandler((*controllers.AdminListController).AdjustUserPoints))
			admin.GET("/points", apiHandler((*controllers.AdminListController).Points))
			admin.GET("/groups", apiHandler((*controllers.AdminListController).Groups))
			admin.POST("/groups", apiHandler((*controllers.AdminListController).SaveGroup))
			admin.DELETE("/groups/:id", apiHandler((*controllers.AdminListController).DeleteGroup))
			admin.GET("/domains", apiHandler((*controllers.AdminListController).Domains))
			admin.POST("/domains", apiHandler((*controllers.AdminListController).SaveDomain))
			admin.POST("/domains/:id/sync-records", apiHandler((*controllers.AdminListController).SyncDomainRecords))
			admin.PUT("/domains/:id", apiHandler((*controllers.AdminListController).SaveDomain))
			admin.DELETE("/domains/:id", apiHandler((*controllers.AdminListController).DeleteDomain))
			admin.GET("/dns-providers", apiHandler((*controllers.AdminListController).Providers))
			admin.POST("/dns-providers/zones", apiHandler((*controllers.AdminListController).ProviderZones))
			admin.GET("/records", apiHandler((*controllers.AdminListController).Records))
			admin.POST("/records", apiHandler((*controllers.AdminListController).SaveRecord))
			admin.PUT("/records/:id", apiHandler((*controllers.AdminListController).SaveRecord))
			admin.DELETE("/records/:id", apiHandler((*controllers.AdminListController).DeleteRecord))
			admin.GET("/subdomains", apiHandler((*controllers.AdminListController).Subdomains))
			admin.POST("/subdomains/:id/approve", apiHandler((*controllers.AdminListController).ApproveSubdomain))
			admin.POST("/subdomains/:id/reject", apiHandler((*controllers.AdminListController).RejectSubdomain))
			admin.DELETE("/subdomains/:id", apiHandler((*controllers.AdminListController).DeleteSubdomain))
			admin.GET("/logs", apiHandler((*controllers.AdminListController).Logs))
			admin.GET("/settings", apiHandler((*controllers.AdminListController).Settings))
			admin.PUT("/settings", apiHandler((*controllers.AdminListController).SaveSettings))
		}
	}

	router.NoRoute(spaHandler((*controllers.SPAController).Index))
	return router
}

func apiHandler[T any](action func(*T)) gin.HandlerFunc {
	return func(ctx *gin.Context) {
		controller := new(T)
		if setter, ok := any(controller).(interface{ SetContext(*gin.Context) }); ok {
			setter.SetContext(ctx)
		}
		action(controller)
	}
}

func spaHandler(action func(*controllers.SPAController)) gin.HandlerFunc {
	return func(ctx *gin.Context) {
		controller := &controllers.SPAController{}
		controller.SetContext(ctx)
		action(controller)
	}
}
