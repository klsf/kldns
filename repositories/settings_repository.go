package repositories

import (
	"context"
	"database/sql"
	"encoding/json"
	"strconv"
	"strings"

	"kldns/pkg/secrets"
)

type SettingsRepository struct {
	DB *sql.DB
}

func NewSettingsRepository(db *sql.DB) *SettingsRepository {
	return &SettingsRepository{DB: db}
}

func (r *SettingsRepository) AllowUnlimitedSubdomainRecords(ctx context.Context) (bool, error) {
	var raw sql.NullString
	err := r.DB.QueryRowContext(ctx, `SELECT value FROM settings WHERE key = 'array_dns'`).Scan(&raw)
	if err == sql.ErrNoRows {
		return true, nil
	}
	if err != nil {
		return false, err
	}
	if !raw.Valid || strings.TrimSpace(raw.String) == "" {
		return true, nil
	}
	var values map[string]string
	if err := json.Unmarshal([]byte(raw.String), &values); err != nil {
		return true, nil
	}
	return values["unlimited_subdomain_records"] != "0", nil
}

type TurnstileSettings struct {
	SiteKey          string `json:"site_key"`
	SecretKey        string `json:"-"`
	SecretConfigured bool   `json:"secret_configured,omitempty"`
	RegisterEnabled  bool   `json:"register_enabled"`
	LoginEnabled     bool   `json:"login_enabled"`
}

func (r *SettingsRepository) TurnstileSettings(ctx context.Context, secret string) (TurnstileSettings, error) {
	var raw sql.NullString
	err := r.DB.QueryRowContext(ctx, `SELECT value FROM settings WHERE key = 'array_turnstile'`).Scan(&raw)
	if err == sql.ErrNoRows {
		return TurnstileSettings{}, nil
	}
	if err != nil {
		return TurnstileSettings{}, err
	}
	return ParseTurnstileSettings(raw.String, secret)
}

func ParseTurnstileSettings(raw string, secret string) (TurnstileSettings, error) {
	values, err := parseSettingObject(raw)
	if err != nil {
		return TurnstileSettings{}, err
	}
	cfg := TurnstileSettings{
		SiteKey:         firstSetting(values, "site_key"),
		SecretKey:       firstSetting(values, "secret_key"),
		RegisterEnabled: truthySetting(firstSetting(values, "register_enabled")),
		LoginEnabled:    truthySetting(firstSetting(values, "login_enabled")),
	}
	if cipherText := firstSetting(values, "secret_key_ciphertext"); cipherText != "" {
		plain, err := secrets.Decrypt(secret, cipherText)
		if err != nil {
			return TurnstileSettings{}, err
		}
		cfg.SecretKey = plain
	}
	cfg.SecretConfigured = strings.TrimSpace(cfg.SecretKey) != ""
	return cfg, nil
}

func ProtectTurnstileSettings(raw string, existingRaw string, secret string) (string, error) {
	values, err := parseSettingObject(raw)
	if err != nil {
		return "", err
	}
	existing, _ := parseSettingObject(existingRaw)
	plainSecret := strings.TrimSpace(firstSetting(values, "secret_key"))
	if plainSecret == "" {
		if current := firstSetting(existing, "secret_key_ciphertext"); current != "" {
			values["secret_key_ciphertext"] = current
		}
	} else {
		cipherText, err := secrets.Encrypt(secret, plainSecret)
		if err != nil {
			return "", err
		}
		values["secret_key_ciphertext"] = cipherText
	}
	delete(values, "secret_key")
	return marshalSettingObject(values)
}

func MaskTurnstileSettings(raw string) (string, error) {
	values, err := parseSettingObject(raw)
	if err != nil {
		return "", err
	}
	configured := firstSetting(values, "secret_key_ciphertext", "secret_key") != ""
	delete(values, "secret_key")
	delete(values, "secret_key_ciphertext")
	if configured {
		values["secret_configured"] = "1"
	} else {
		values["secret_configured"] = "0"
	}
	return marshalSettingObject(values)
}

func parseSettingObject(raw string) (map[string]string, error) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return map[string]string{}, nil
	}
	var values map[string]string
	if err := json.Unmarshal([]byte(raw), &values); err == nil {
		if values == nil {
			values = map[string]string{}
		}
		return values, nil
	}
	var anyValues map[string]any
	if err := json.Unmarshal([]byte(raw), &anyValues); err != nil {
		return nil, err
	}
	values = make(map[string]string, len(anyValues))
	for key, value := range anyValues {
		switch typed := value.(type) {
		case string:
			values[key] = typed
		case bool:
			if typed {
				values[key] = "1"
			} else {
				values[key] = "0"
			}
		case float64:
			values[key] = strconv.FormatInt(int64(typed), 10)
		default:
			encoded, _ := json.Marshal(typed)
			values[key] = string(encoded)
		}
	}
	return values, nil
}

func marshalSettingObject(values map[string]string) (string, error) {
	data, err := json.Marshal(values)
	if err != nil {
		return "", err
	}
	return string(data), nil
}

func firstSetting(values map[string]string, keys ...string) string {
	for _, key := range keys {
		if value, ok := values[key]; ok {
			return strings.TrimSpace(value)
		}
	}
	return ""
}

func truthySetting(value string) bool {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "1", "true", "yes", "on", "enabled":
		return true
	default:
		return false
	}
}
