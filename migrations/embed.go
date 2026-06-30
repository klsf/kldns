package migrations

import "embed"

// FS contains the SQLite migration files compiled into the binary.
//
//go:embed *.sql
var FS embed.FS

const Dir = "."
