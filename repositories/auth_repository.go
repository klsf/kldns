package repositories

import (
	"context"
	"database/sql"
	"encoding/json"
	"strconv"
	"strings"

	"kldns/models"
)

type AuthRepository struct {
	DB *sql.DB
}

func NewAuthRepository(db *sql.DB) *AuthRepository {
	return &AuthRepository{DB: db}
}

type UserSettings struct {
	RegisterOpen  bool
	ReviewMode    string
	InitialPoints int64
}

func (r *AuthRepository) UserSettings(ctx context.Context) (UserSettings, error) {
	settings := UserSettings{RegisterOpen: true, ReviewMode: "auto", InitialPoints: 100}
	var raw sql.NullString
	if err := r.DB.QueryRowContext(ctx, `SELECT value FROM settings WHERE key = 'array_user'`).Scan(&raw); err != nil {
		if err == sql.ErrNoRows {
			return settings, nil
		}
		return settings, err
	}
	if !raw.Valid {
		return settings, nil
	}
	var data map[string]string
	if err := json.Unmarshal([]byte(raw.String), &data); err != nil {
		return settings, nil
	}
	settings.RegisterOpen = data["reg"] != "0"
	switch strings.ToLower(strings.TrimSpace(data["review_mode"])) {
	case "manual":
		settings.ReviewMode = "manual"
	default:
		settings.ReviewMode = "auto"
	}
	if point := strings.TrimSpace(data["point"]); point != "" {
		parsed, err := strconv.ParseInt(point, 10, 64)
		if err == nil && parsed >= 0 {
			settings.InitialPoints = parsed
		}
	}
	return settings, nil
}

func (r *AuthRepository) CreateUser(ctx context.Context, user models.User, passwordHash string, sid string) (int64, error) {
	res, err := r.DB.ExecContext(ctx, `INSERT INTO users(group_id, status, username, password_hash, sid, email, points)
		VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?)`, user.GroupID, user.Status, user.Username, passwordHash, sid, user.Email, user.Points)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (r *AuthRepository) FindLoginUser(ctx context.Context, login string) (models.User, string, error) {
	login = strings.TrimSpace(login)
	var user models.User
	var passwordHash string
	err := r.DB.QueryRowContext(ctx, `SELECT id, group_id, status, username, COALESCE(email, ''), points, password_hash
		FROM users WHERE username = ? OR email = ?`, login, strings.ToLower(login)).
		Scan(&user.ID, &user.GroupID, &user.Status, &user.Username, &user.Email, &user.Points, &passwordHash)
	return user, passwordHash, err
}

func (r *AuthRepository) UpdatePassword(ctx context.Context, uid int64, passwordHash string, sid string) error {
	_, err := r.DB.ExecContext(ctx, `UPDATE users SET password_hash = ?, sid = ?, updated_at = strftime('%s','now') WHERE id = ?`, passwordHash, sid, uid)
	return err
}
