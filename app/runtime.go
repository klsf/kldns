package app

import (
	"database/sql"
	"sync"
)

var runtime struct {
	sync.RWMutex
	db *sql.DB
}

func SetDB(db *sql.DB) {
	runtime.Lock()
	defer runtime.Unlock()
	runtime.db = db
}

func DB() *sql.DB {
	runtime.RLock()
	defer runtime.RUnlock()
	return runtime.db
}
