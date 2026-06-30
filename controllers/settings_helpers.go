package controllers

import (
	"context"
	"database/sql"
	"strings"

	"kldns/app"
)

func settingsReserveNames(ctx context.Context) []string {
	db := app.DB()
	if db == nil {
		return []string{"www", "w", "m", "3g", "4g", "qq"}
	}
	var value sql.NullString
	if err := db.QueryRowContext(ctx, `SELECT value FROM settings WHERE key = 'reserve_domain_name'`).Scan(&value); err != nil || !value.Valid {
		return []string{"www", "w", "m", "3g", "4g", "qq"}
	}
	parts := strings.Split(value.String, ",")
	out := make([]string, 0, len(parts))
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part != "" {
			out = append(out, part)
		}
	}
	return out
}
