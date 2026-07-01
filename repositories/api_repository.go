package repositories

import (
	"context"
	"database/sql"
	"strings"
	"time"

	"kldns/models"
	"kldns/pkg/dns"
)

type APIRepository struct {
	DB *Database
}

const lastUsedUpdateIntervalSeconds int64 = 300

func NewAPIRepository(db *Database) *APIRepository {
	return &APIRepository{DB: db}
}

type TokenWithUser struct {
	ID        int64
	UID       int64
	Name      string
	TokenHint string
	ExpiresAt int64
	User      models.User
}

type DomainSummary struct {
	ID                       int64            `json:"id"`
	Domain                   string           `json:"domain"`
	PointsCost               int64            `json:"points_cost"`
	RegistrationCost         int64            `json:"registration_cost"`
	Description              string           `json:"description"`
	RecordTypes              []string         `json:"record_types"`
	Beian                    int              `json:"beian"`
	BeianText                string           `json:"beian_text"`
	RequireReview            int              `json:"require_review"`
	Line                     []dns.RecordLine `json:"line"`
	ProviderKey              string           `json:"-"`
	ProviderConfigCiphertext string           `json:"-"`
	RemoteZoneID             string           `json:"-"`
}

type RecordSummary struct {
	ID            int64  `json:"id"`
	UID           int64  `json:"uid,omitempty"`
	DID           int64  `json:"did"`
	SubdomainID   int64  `json:"subdomain_id"`
	Name          string `json:"name"`
	Host          string `json:"host"`
	Type          string `json:"type"`
	Value         string `json:"value"`
	LineID        string `json:"line_id"`
	Line          string `json:"line"`
	Username      string `json:"username,omitempty"`
	Domain        string `json:"domain,omitempty"`
	Subdomain     string `json:"subdomain,omitempty"`
	SubdomainName string `json:"subdomain_name,omitempty"`
	FullName      string `json:"full_name,omitempty"`
}

type SubdomainSummary struct {
	ID               int64    `json:"id"`
	UID              int64    `json:"uid,omitempty"`
	DID              int64    `json:"did"`
	Name             string   `json:"name"`
	FullDomain       string   `json:"full_domain"`
	Status           int      `json:"status"`
	Purpose          string   `json:"purpose"`
	RejectReason     string   `json:"reject_reason"`
	ReviewedBy       int64    `json:"reviewed_by"`
	ReviewedAt       int64    `json:"reviewed_at"`
	Domain           string   `json:"domain"`
	RegistrationCost int64    `json:"registration_cost"`
	RecordTypes      []string `json:"record_types"`
	RecordCount      int64    `json:"record_count"`
	CreatedAt        int64    `json:"created_at"`
}

type TokenSummary struct {
	ID         int64  `json:"id"`
	Name       string `json:"name"`
	TokenHint  string `json:"token_hint"`
	LastUsedAt int64  `json:"last_used_at"`
	ExpiresAt  int64  `json:"expires_at"`
	CreatedAt  int64  `json:"created_at"`
}

type PointRecordSummary struct {
	ID        int64  `json:"id"`
	Action    string `json:"action"`
	Points    int64  `json:"points"`
	Rest      int64  `json:"rest"`
	Remark    string `json:"remark"`
	CreatedAt int64  `json:"created_at"`
}

type PointsOverview struct {
	Balance       int64                `json:"balance"`
	MonthSpent    int64                `json:"month_spent"`
	TotalSpent    int64                `json:"total_spent"`
	Actions       []string             `json:"actions"`
	RecentRecords []PointRecordSummary `json:"recent_records"`
}

type DomainFilter struct {
	Keyword string
}

type RecordFilter struct {
	DID         int64
	SubdomainID int64
	Type        string
	Keyword     string
}

type SubdomainFilter struct {
	Status  *int
	Keyword string
}

type PointRecordFilter struct {
	Action  string
	Keyword string
	Since   int64
}

func (r *APIRepository) AuthenticateSession(ctx context.Context, tokenHash string) (TokenWithUser, error) {
	var result TokenWithUser
	err := r.DB.QueryRowContext(ctx, `SELECT
			s.id, s.uid, 'session', s.token_hint, s.expires_at,
			u.id, u.group_id, u.status, u.username, COALESCE(u.email, ''), u.points
		FROM sessions s
		JOIN users u ON u.id = s.uid
		WHERE s.token_hash = ? AND u.status != 0`, tokenHash).
		Scan(&result.ID, &result.UID, &result.Name, &result.TokenHint, &result.ExpiresAt,
			&result.User.ID, &result.User.GroupID, &result.User.Status, &result.User.Username, &result.User.Email, &result.User.Points)
	if err != nil {
		return TokenWithUser{}, err
	}
	if result.ExpiresAt > 0 && result.ExpiresAt < time.Now().Unix() {
		return TokenWithUser{}, sql.ErrNoRows
	}
	if err := touchLastUsedAt(ctx, r.DB, "sessions", result.ID); err != nil {
		return TokenWithUser{}, err
	}
	return result, nil
}

func (r *APIRepository) AuthenticateToken(ctx context.Context, tokenHash string) (TokenWithUser, error) {
	var result TokenWithUser
	err := r.DB.QueryRowContext(ctx, `SELECT
			t.id, t.uid, t.name, t.token_hint, t.expires_at,
			u.id, u.group_id, u.status, u.username, COALESCE(u.email, ''), u.points
		FROM api_tokens t
		JOIN users u ON u.id = t.uid
		WHERE t.token_hash = ? AND u.status != 0`, tokenHash).
		Scan(&result.ID, &result.UID, &result.Name, &result.TokenHint, &result.ExpiresAt,
			&result.User.ID, &result.User.GroupID, &result.User.Status, &result.User.Username, &result.User.Email, &result.User.Points)
	if err != nil {
		return TokenWithUser{}, err
	}
	if result.ExpiresAt > 0 && result.ExpiresAt < time.Now().Unix() {
		return TokenWithUser{}, sql.ErrNoRows
	}
	if err := touchLastUsedAt(ctx, r.DB, "api_tokens", result.ID); err != nil {
		return TokenWithUser{}, err
	}
	return result, nil
}

func touchLastUsedAt(ctx context.Context, db *Database, table string, id int64) error {
	switch table {
	case "sessions", "api_tokens":
	default:
		return nil
	}
	_, err := db.ExecContext(ctx, `UPDATE `+table+` SET last_used_at = strftime('%s','now')
		WHERE id = ? AND last_used_at < strftime('%s','now') - ?`, id, lastUsedUpdateIntervalSeconds)
	return err
}

func (r *APIRepository) CreateSession(ctx context.Context, uid int64, tokenHash string, tokenHint string, expiresAt int64) (int64, error) {
	res, err := r.DB.ExecContext(ctx, `INSERT INTO sessions(uid, token_hash, token_hint, expires_at) VALUES (?, ?, ?, ?)`, uid, tokenHash, tokenHint, expiresAt)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (r *APIRepository) ListAvailableDomains(ctx context.Context, gid int64, filter DomainFilter) ([]DomainSummary, error) {
	query := `SELECT id, domain, points_cost, COALESCE(description, ''), record_types, beian, require_review,
			provider_key, COALESCE(provider_config_ciphertext, ''), remote_zone_id
		FROM domains
		WHERE (group_policy = '0' OR instr(',' || group_policy || ',', ',' || ? || ',') > 0)`
	args := []any{gid}
	if term := likeTerm(filter.Keyword); term != "" {
		query += ` AND lower(domain) LIKE ?`
		args = append(args, term)
	}
	query += ` ORDER BY id DESC`
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []DomainSummary{}
	for rows.Next() {
		var item DomainSummary
		var recordTypes string
		if err := rows.Scan(&item.ID, &item.Domain, &item.PointsCost, &item.Description, &recordTypes, &item.Beian, &item.RequireReview, &item.ProviderKey, &item.ProviderConfigCiphertext, &item.RemoteZoneID); err != nil {
			return nil, err
		}
		item.RegistrationCost = item.PointsCost
		item.RecordTypes = splitCSV(recordTypes)
		if item.Beian == 1 {
			item.BeianText = "已备案"
		} else {
			item.BeianText = "未备案"
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *APIRepository) ListPublicDomains(ctx context.Context) ([]DomainSummary, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, domain, points_cost, COALESCE(description, ''), record_types, beian, require_review, group_policy
		FROM domains
		ORDER BY id DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []DomainSummary{}
	for rows.Next() {
		var item DomainSummary
		var recordTypes string
		var groupPolicy string
		if err := rows.Scan(&item.ID, &item.Domain, &item.PointsCost, &item.Description, &recordTypes, &item.Beian, &item.RequireReview, &groupPolicy); err != nil {
			return nil, err
		}
		if isAdminOnlyGroupPolicy(groupPolicy) {
			continue
		}
		item.RegistrationCost = item.PointsCost
		item.RecordTypes = splitCSV(recordTypes)
		if item.Beian == 1 {
			item.BeianText = "已备案"
		} else {
			item.BeianText = "未备案"
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *APIRepository) ListSubdomains(ctx context.Context, uid int64, filter SubdomainFilter) ([]SubdomainSummary, error) {
	query := `SELECT
			s.id, s.uid, s.did, s.name, s.full_domain, s.status, COALESCE(s.purpose, ''), COALESCE(s.reject_reason, ''), COALESCE(s.reviewed_by, 0), COALESCE(s.reviewed_at, 0), s.created_at,
			d.domain, d.points_cost, d.record_types, COUNT(r.id)
		FROM subdomains s
		JOIN domains d ON d.id = s.did
		LEFT JOIN records r ON r.subdomain_id = s.id
		WHERE s.uid = ?`
	args := []any{uid}
	if filter.Status != nil {
		query += ` AND s.status = ?`
		args = append(args, *filter.Status)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		query += ` AND (
			lower(s.name) LIKE ? OR lower(s.full_domain) LIKE ? OR lower(d.domain) LIKE ? OR
			lower(COALESCE(s.purpose, '')) LIKE ? OR lower(COALESCE(s.reject_reason, '')) LIKE ?
		)`
		args = append(args, term, term, term, term, term)
	}
	query += ` GROUP BY s.id ORDER BY s.id DESC`
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []SubdomainSummary{}
	for rows.Next() {
		var item SubdomainSummary
		var recordTypes string
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.Name, &item.FullDomain, &item.Status, &item.Purpose, &item.RejectReason, &item.ReviewedBy, &item.ReviewedAt, &item.CreatedAt, &item.Domain, &item.RegistrationCost, &recordTypes, &item.RecordCount); err != nil {
			return nil, err
		}
		item.RecordTypes = splitCSV(recordTypes)
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *APIRepository) ListRecords(ctx context.Context, uid int64, filter RecordFilter) ([]RecordSummary, error) {
	query := `SELECT r.id, r.did, COALESCE(r.subdomain_id, 0), r.name, r.type, r.value, r.line_id, COALESCE(r.line, ''),
			COALESCE(d.domain, ''), COALESCE(s.name, ''), COALESCE(s.full_domain, '')
		FROM records r
		LEFT JOIN domains d ON d.id = r.did
		LEFT JOIN subdomains s ON s.id = r.subdomain_id
		WHERE r.uid = ?`
	args := []any{uid}
	if filter.DID > 0 {
		query += ` AND r.did = ?`
		args = append(args, filter.DID)
	}
	if filter.SubdomainID > 0 {
		query += ` AND r.subdomain_id = ?`
		args = append(args, filter.SubdomainID)
	}
	if recordType := strings.ToUpper(strings.TrimSpace(filter.Type)); recordType != "" {
		query += ` AND r.type = ?`
		args = append(args, recordType)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		query += ` AND (
			lower(COALESCE(s.full_domain, '')) LIKE ? OR lower(r.name) LIKE ? OR lower(r.type) LIKE ? OR
			lower(r.value) LIKE ? OR lower(COALESCE(r.line, '')) LIKE ?
		)`
		args = append(args, term, term, term, term, term)
	}
	query += ` ORDER BY r.id DESC`
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []RecordSummary{}
	for rows.Next() {
		var item RecordSummary
		if err := rows.Scan(&item.ID, &item.DID, &item.SubdomainID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line, &item.Domain, &item.SubdomainName, &item.Subdomain); err != nil {
			return nil, err
		}
		item.Host = relativeRecordName(item.Name, item.SubdomainName)
		item.FullName = joinRecordName(item.Name, item.Domain)
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *APIRepository) ListTokens(ctx context.Context, uid int64) ([]TokenSummary, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT id, name, token_hint, last_used_at, expires_at, created_at FROM api_tokens WHERE uid = ? AND name != 'login' ORDER BY id DESC`, uid)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []TokenSummary{}
	for rows.Next() {
		var item TokenSummary
		if err := rows.Scan(&item.ID, &item.Name, &item.TokenHint, &item.LastUsedAt, &item.ExpiresAt, &item.CreatedAt); err != nil {
			return nil, err
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *APIRepository) PointsOverview(ctx context.Context, uid int64, filter PointRecordFilter) (PointsOverview, error) {
	var result PointsOverview
	if err := r.DB.QueryRowContext(ctx, `SELECT points FROM users WHERE id = ?`, uid).Scan(&result.Balance); err != nil {
		return PointsOverview{}, err
	}
	monthStart := time.Now().In(time.Local)
	monthStart = time.Date(monthStart.Year(), monthStart.Month(), 1, 0, 0, 0, 0, monthStart.Location())
	if err := r.DB.QueryRowContext(ctx, `SELECT
			COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END), 0),
			COALESCE(SUM(CASE WHEN points < 0 AND created_at >= ? THEN -points ELSE 0 END), 0)
		FROM point_records
		WHERE uid = ?`, monthStart.Unix(), uid).Scan(&result.TotalSpent, &result.MonthSpent); err != nil {
		return PointsOverview{}, err
	}

	actionRows, err := r.DB.QueryContext(ctx, `SELECT action
		FROM point_records
		WHERE uid = ? AND COALESCE(action, '') != ''
		GROUP BY action
		ORDER BY action ASC`, uid)
	if err != nil {
		return PointsOverview{}, err
	}
	result.Actions = []string{}
	for actionRows.Next() {
		var action string
		if err := actionRows.Scan(&action); err != nil {
			actionRows.Close()
			return PointsOverview{}, err
		}
		result.Actions = append(result.Actions, action)
	}
	if err := actionRows.Close(); err != nil {
		return PointsOverview{}, err
	}

	query := `SELECT id, action, points, rest, COALESCE(remark, ''), created_at
		FROM point_records
		WHERE uid = ?`
	args := []any{uid}
	if action := strings.TrimSpace(filter.Action); action != "" {
		query += ` AND action = ?`
		args = append(args, action)
	}
	if filter.Since > 0 {
		query += ` AND created_at >= ?`
		args = append(args, filter.Since)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		query += ` AND (lower(action) LIKE ? OR lower(COALESCE(remark, '')) LIKE ?)`
		args = append(args, term, term)
	}
	query += ` ORDER BY id DESC LIMIT 100`
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PointsOverview{}, err
	}
	defer rows.Close()
	result.RecentRecords = []PointRecordSummary{}
	for rows.Next() {
		var item PointRecordSummary
		if err := rows.Scan(&item.ID, &item.Action, &item.Points, &item.Rest, &item.Remark, &item.CreatedAt); err != nil {
			return PointsOverview{}, err
		}
		result.RecentRecords = append(result.RecentRecords, item)
	}
	return result, rows.Err()
}

func (r *APIRepository) CreateToken(ctx context.Context, uid int64, name string, tokenHash string, tokenHint string, expiresAt int64) (int64, error) {
	res, err := r.DB.ExecContext(ctx, `INSERT INTO api_tokens(uid, name, token_hash, token_hint, expires_at) VALUES (?, ?, ?, ?, ?)`, uid, name, tokenHash, tokenHint, expiresAt)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (r *APIRepository) DeleteToken(ctx context.Context, uid int64, id int64) (bool, error) {
	res, err := r.DB.ExecContext(ctx, `DELETE FROM api_tokens WHERE uid = ? AND id = ?`, uid, id)
	if err != nil {
		return false, err
	}
	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}
	return affected == 1, nil
}

func splitCSV(value string) []string {
	parts := strings.Split(value, ",")
	out := make([]string, 0, len(parts))
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part != "" {
			out = append(out, part)
		}
	}
	return out
}

func isAdminOnlyGroupPolicy(value string) bool {
	groups := splitCSV(value)
	if len(groups) == 0 {
		return false
	}
	for _, group := range groups {
		if group != "99" {
			return false
		}
	}
	return true
}

func relativeRecordName(name string, subdomain string) string {
	name = strings.ToLower(strings.TrimSpace(name))
	subdomain = strings.ToLower(strings.TrimSpace(subdomain))
	if name == "" || subdomain == "" {
		return name
	}
	if name == subdomain {
		return "@"
	}
	suffix := "." + subdomain
	if strings.HasSuffix(name, suffix) {
		return strings.TrimSuffix(name, suffix)
	}
	return name
}
