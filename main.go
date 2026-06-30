package main

import (
	"log"
	"strings"

	"kldns/app"
	"kldns/controllers"
	migrationassets "kldns/migrations"
	_ "kldns/pkg/dns/providers"
	"kldns/repositories"
	_ "kldns/routers"
	webassets "kldns/web"

	"github.com/beego/beego/v2/server/web"
)

const defaultSecretKey = "change-me-before-production-kldns-secret"

func main() {
	mustValidateRuntimeSecret()

	db, err := repositories.OpenFromConfig()
	if err != nil {
		log.Fatalf("open database: %v", err)
	}
	defer db.Close()

	if err := repositories.RunMigrationsFS(db, migrationassets.FS, migrationassets.Dir); err != nil {
		log.Fatalf("run migrations: %v", err)
	}
	app.SetDB(db)
	if err := controllers.SetFrontendFS(webassets.FS, webassets.DistDir); err != nil {
		log.Fatalf("load embedded frontend: %v", err)
	}

	web.Run()
}

func mustValidateRuntimeSecret() {
	runMode, _ := web.AppConfig.String("runmode")
	secret, _ := web.AppConfig.String("secret_key")
	if isProductionMode(runMode) && strings.TrimSpace(secret) == defaultSecretKey {
		log.Fatal("refusing to start in production with the default secret_key")
	}
}

func isProductionMode(runMode string) bool {
	switch strings.ToLower(strings.TrimSpace(runMode)) {
	case "prod", "production":
		return true
	default:
		return false
	}
}
