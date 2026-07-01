package config

import (
	"errors"
	"fmt"
	"os"
	"strings"

	"gopkg.in/yaml.v3"
)

const (
	DefaultPath      = "config.yaml"
	EnvPath          = "KLDNS_CONFIG"
	DefaultSecretKey = "change-me-before-production-kldns-secret"
)

type Config struct {
	App      AppConfig      `yaml:"app"`
	Database DatabaseConfig `yaml:"database"`
	Security SecurityConfig `yaml:"security"`
}

type AppConfig struct {
	Name string `yaml:"name"`
	Port int    `yaml:"port"`
	Mode string `yaml:"mode"`
}

type DatabaseConfig struct {
	Path          string `yaml:"path"`
	BusyTimeoutMS int    `yaml:"busy_timeout_ms"`
	WAL           bool   `yaml:"wal"`
}

type SecurityConfig struct {
	SecretKey string `yaml:"secret_key"`
}

func Load() (Config, error) {
	path := strings.TrimSpace(os.Getenv(EnvPath))
	if path == "" {
		path = DefaultPath
	}
	return LoadFile(path)
}

func LoadFile(path string) (Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return Config{}, fmt.Errorf("read config %s: %w", path, err)
	}
	if len(strings.TrimSpace(string(data))) == 0 {
		return Config{}, errors.New("config file is empty")
	}
	cfg := Default()
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return Config{}, fmt.Errorf("parse config %s: %w", path, err)
	}
	cfg.Normalize()
	return cfg, nil
}

func Default() Config {
	return Config{
		App: AppConfig{
			Name: "kldns",
			Port: 8004,
			Mode: "dev",
		},
		Database: DatabaseConfig{
			Path:          "data/kldns.db",
			BusyTimeoutMS: 5000,
			WAL:           true,
		},
		Security: SecurityConfig{
			SecretKey: DefaultSecretKey,
		},
	}
}

func (c *Config) Normalize() {
	if strings.TrimSpace(c.App.Name) == "" {
		c.App.Name = "kldns"
	}
	if c.App.Port <= 0 {
		c.App.Port = 8004
	}
	if strings.TrimSpace(c.App.Mode) == "" {
		c.App.Mode = "dev"
	}
	if strings.TrimSpace(c.Database.Path) == "" {
		c.Database.Path = "data/kldns.db"
	}
	if c.Database.BusyTimeoutMS <= 0 {
		c.Database.BusyTimeoutMS = 5000
	}
	if strings.TrimSpace(c.Security.SecretKey) == "" {
		c.Security.SecretKey = DefaultSecretKey
	}
}
