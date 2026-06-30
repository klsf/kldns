package config

import (
	"os"
	"path/filepath"
	"testing"
)

func TestLoadFileReadsYAMLAndDefaults(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "app.yaml")
	if err := os.WriteFile(path, []byte("app:\n  port: 9000\nsecurity:\n  secret_key: test-secret\n"), 0o600); err != nil {
		t.Fatal(err)
	}

	cfg, err := LoadFile(path)
	if err != nil {
		t.Fatalf("LoadFile() error = %v", err)
	}
	if cfg.App.Port != 9000 {
		t.Fatalf("port = %d", cfg.App.Port)
	}
	if cfg.Database.Path != "data/kldns.db" {
		t.Fatalf("database path default = %q", cfg.Database.Path)
	}
	if cfg.Security.SecretKey != "test-secret" {
		t.Fatalf("secret = %q", cfg.Security.SecretKey)
	}
}

func TestLoadFileRejectsEmptyConfig(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "app.yaml")
	if err := os.WriteFile(path, []byte(" \n"), 0o600); err != nil {
		t.Fatal(err)
	}

	if _, err := LoadFile(path); err == nil {
		t.Fatal("expected empty config error")
	}
}
