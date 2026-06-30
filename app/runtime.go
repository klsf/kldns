package app

import (
	"kldns/config"
	"kldns/repositories"
	"sync"
)

var runtime struct {
	sync.RWMutex
	db  *repositories.Database
	cfg config.Config
}

func SetDB(db *repositories.Database) {
	runtime.Lock()
	defer runtime.Unlock()
	runtime.db = db
}

func DB() *repositories.Database {
	runtime.RLock()
	defer runtime.RUnlock()
	return runtime.db
}

func SetConfig(cfg config.Config) {
	runtime.Lock()
	defer runtime.Unlock()
	runtime.cfg = cfg
}

func Config() config.Config {
	runtime.RLock()
	defer runtime.RUnlock()
	return runtime.cfg
}

func SecretKey() string {
	cfg := Config()
	if cfg.Security.SecretKey == "" {
		return config.DefaultSecretKey
	}
	return cfg.Security.SecretKey
}
