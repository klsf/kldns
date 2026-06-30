package repositories

import (
	"context"
	"database/sql"
	"strings"

	"kldns/pkg/dns"
)

// AdminRepository is a facade that aggregates domain-specific repositories.
// Prefer using the individual repositories directly in new code.
type AdminRepository struct {
	DB      *sql.DB
	users   *UsersRepository
	domains *DomainsRepository
	groups  *GroupsRepository
	logs    *LogsRepository
}

func NewAdminRepository(db *sql.DB) *AdminRepository {
	return &AdminRepository{
		DB:      db,
		users:   NewUsersRepository(db),
		domains: NewDomainsRepository(db),
		groups:  NewGroupsRepository(db),
		logs:    NewLogsRepository(db),
	}
}

// --- User delegation ---

func (r *AdminRepository) ListUsers(ctx context.Context, filter UserAdminFilter) ([]UserAdminSummary, error) {
	return r.users.ListUsers(ctx, filter)
}

func (r *AdminRepository) ListUsersPage(ctx context.Context, filter UserAdminFilter, page PageQuery) (PageResult[UserAdminSummary], error) {
	return r.users.ListUsersPage(ctx, filter, page)
}

func (r *AdminRepository) UpdateUser(ctx context.Context, input UserWrite) (bool, error) {
	return r.users.UpdateUser(ctx, input)
}

// --- Group delegation ---

func (r *AdminRepository) ListGroups(ctx context.Context, filter GroupFilter) ([]GroupSummary, error) {
	return r.groups.ListGroups(ctx, filter)
}

func (r *AdminRepository) UpsertGroup(ctx context.Context, id int64, name string) (int64, error) {
	return r.groups.UpsertGroup(ctx, id, name)
}

func (r *AdminRepository) DeleteGroup(ctx context.Context, id int64) (bool, error) {
	return r.groups.DeleteGroup(ctx, id)
}

// --- Domain delegation ---

func (r *AdminRepository) ListDomains(ctx context.Context, filter DomainAdminFilter) ([]DomainAdminSummary, error) {
	return r.domains.ListDomains(ctx, filter)
}

func (r *AdminRepository) ListDomainsPage(ctx context.Context, filter DomainAdminFilter, page PageQuery) (PageResult[DomainAdminSummary], error) {
	return r.domains.ListDomainsPage(ctx, filter, page)
}

func (r *AdminRepository) StoredProviders(ctx context.Context) (map[string]bool, error) {
	return r.domains.StoredProviders(ctx)
}

func (r *AdminRepository) FindDomainConflict(ctx context.Context, input DomainWrite) (DomainConflict, bool, error) {
	return r.domains.FindDomainConflict(ctx, input)
}

func (r *AdminRepository) UpsertDomain(ctx context.Context, input DomainWrite) (int64, error) {
	return r.domains.UpsertDomain(ctx, input)
}

func (r *AdminRepository) DomainProviderConfig(ctx context.Context, id int64) (DomainProviderConfig, error) {
	return r.domains.DomainProviderConfig(ctx, id)
}

func (r *AdminRepository) DeleteDomain(ctx context.Context, id int64) (bool, error) {
	return r.domains.DeleteDomain(ctx, id)
}

// --- Log delegation ---

func (r *AdminRepository) ListLogs(ctx context.Context, filter LogFilter) ([]LogSummary, error) {
	return r.logs.ListLogs(ctx, filter)
}

func (r *AdminRepository) ListLogsPage(ctx context.Context, filter LogFilter, page PageQuery) (PageResult[LogSummary], error) {
	return r.logs.ListLogsPage(ctx, filter, page)
}

// --- Inline: records / subdomains / settings / provider summary ---
// These remain here because they require joins across multiple entity types
// or depend on settings logic not yet extracted.

type RecordAdminFilter struct {
	DID         int64
	SubdomainID int64
	UID         int64
	Type        string
	Keyword     string
}

type SubdomainAdminFilter struct {
	DID     int64
	Keyword string
}

type ProviderSummary struct {
	Key    string            `json:"key"`
	Label  string            `json:"label"`
	Fields []dns.ConfigField `json:"fields"`
	Stored bool              `json:"stored"`
}

type SettingSummary struct {
	Key   string `json:"key"`
	Value string `json:"value"`
}

type AdminSubdomainSummary struct {
	ID               int64  `json:"id"`
	UID              int64  `json:"uid"`
	DID              int64  `json:"did"`
	Name             string `json:"name"`
	FullDomain       string `json:"full_domain"`
	Status           int    `json:"status"`
	Username         string `json:"username"`
	Domain           string `json:"domain"`
	RecordCount      int64  `json:"record_count"`
	RegistrationCost int64  `json:"registration_cost"`
	CreatedAt        int64  `json:"created_at"`
}

func (r *AdminRepository) ListAllRecords(ctx context.Context, filter RecordAdminFilter) ([]RecordSummary, error) {
	result, err := r.ListAllRecordsPage(ctx, filter, PageQuery{})
	return result.Items, err
}

func (r *AdminRepository) ListAllRecordsPage(ctx context.Context, filter RecordAdminFilter, page PageQuery) (PageResult[RecordSummary], error) {
	fromWhere := `FROM records r
		LEFT JOIN users u ON u.id = r.uid
		LEFT JOIN domains d ON d.id = r.did
		LEFT JOIN subdomains s ON s.id = r.subdomain_id
		WHERE 1 = 1`
	args := []any{}
	if filter.DID > 0 {
		fromWhere += ` AND r.did = ?`
		args = append(args, filter.DID)
	}
	if filter.SubdomainID > 0 {
		fromWhere += ` AND r.subdomain_id = ?`
		args = append(args, filter.SubdomainID)
	}
	if filter.UID > 0 {
		fromWhere += ` AND r.uid = ?`
		args = append(args, filter.UID)
	}
	if recordType := strings.ToUpper(strings.TrimSpace(filter.Type)); recordType != "" {
		fromWhere += ` AND r.type = ?`
		args = append(args, recordType)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		fromWhere += ` AND (
			lower(COALESCE(u.username, '')) LIKE ? OR lower(COALESCE(d.domain, '')) LIKE ? OR
			lower(COALESCE(s.full_domain, '')) LIKE ? OR lower(r.name) LIKE ? OR lower(r.type) LIKE ? OR
			lower(r.value) LIKE ? OR lower(COALESCE(r.line, '')) LIKE ?
		)`
		args = append(args, term, term, term, term, term, term, term)
	}
	total := int64(0)
	if page.Enabled() {
		var err error
		total, err = countRows(ctx, r.DB, fromWhere, args)
		if err != nil {
			return PageResult[RecordSummary]{}, err
		}
	}
	query := `SELECT
			r.id, r.uid, r.did, COALESCE(r.subdomain_id, 0), r.name, r.type, r.value, r.line_id, COALESCE(r.line, ''),
			COALESCE(u.username, ''), COALESCE(d.domain, ''), COALESCE(s.name, ''), COALESCE(s.full_domain, '')
		` + fromWhere
	query += ` ORDER BY r.id DESC`
	if page.Enabled() {
		page = page.Normalize()
		query, args = applyPage(query, args, page)
	}
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PageResult[RecordSummary]{}, err
	}
	defer rows.Close()
	items := []RecordSummary{}
	for rows.Next() {
		var item RecordSummary
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.SubdomainID, &item.Name, &item.Type, &item.Value, &item.LineID, &item.Line, &item.Username, &item.Domain, &item.SubdomainName, &item.Subdomain); err != nil {
			return PageResult[RecordSummary]{}, err
		}
		item.Host = relativeRecordName(item.Name, item.SubdomainName)
		item.FullName = joinRecordName(item.Name, item.Domain)
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PageResult[RecordSummary]{}, err
	}
	if !page.Enabled() {
		total = int64(len(items))
		page = PageQuery{Page: 1, PageSize: len(items)}
	}
	return PageResult[RecordSummary]{Items: items, Total: total, Page: page.Page, PageSize: page.PageSize}, nil
}

func (r *AdminRepository) ListAllSubdomains(ctx context.Context, filter SubdomainAdminFilter) ([]AdminSubdomainSummary, error) {
	result, err := r.ListAllSubdomainsPage(ctx, filter, PageQuery{})
	return result.Items, err
}

func (r *AdminRepository) ListAllSubdomainsPage(ctx context.Context, filter SubdomainAdminFilter, page PageQuery) (PageResult[AdminSubdomainSummary], error) {
	fromWhere := `FROM subdomains s
		LEFT JOIN users u ON u.id = s.uid
		LEFT JOIN domains d ON d.id = s.did
		LEFT JOIN records rec ON rec.subdomain_id = s.id
		WHERE 1 = 1`
	args := []any{}
	if filter.DID > 0 {
		fromWhere += ` AND s.did = ?`
		args = append(args, filter.DID)
	}
	if term := likeTerm(filter.Keyword); term != "" {
		fromWhere += ` AND (
			lower(s.full_domain) LIKE ? OR lower(s.name) LIKE ? OR lower(COALESCE(d.domain, '')) LIKE ? OR lower(COALESCE(u.username, '')) LIKE ?
		)`
		args = append(args, term, term, term, term)
	}
	total := int64(0)
	if page.Enabled() {
		var err error
		countFromWhere := strings.Replace(fromWhere, "LEFT JOIN records rec ON rec.subdomain_id = s.id", "", 1)
		total, err = countRows(ctx, r.DB, countFromWhere, args)
		if err != nil {
			return PageResult[AdminSubdomainSummary]{}, err
		}
	}
	query := `SELECT
			s.id, s.uid, s.did, s.name, s.full_domain, s.status, s.created_at,
			COALESCE(u.username, ''), COALESCE(d.domain, ''), COALESCE(d.points_cost, 0), COUNT(rec.id)
		` + fromWhere
	query += ` GROUP BY s.id ORDER BY s.id DESC`
	if page.Enabled() {
		page = page.Normalize()
		query, args = applyPage(query, args, page)
	}
	rows, err := r.DB.QueryContext(ctx, query, args...)
	if err != nil {
		return PageResult[AdminSubdomainSummary]{}, err
	}
	defer rows.Close()
	items := []AdminSubdomainSummary{}
	for rows.Next() {
		var item AdminSubdomainSummary
		if err := rows.Scan(&item.ID, &item.UID, &item.DID, &item.Name, &item.FullDomain, &item.Status, &item.CreatedAt, &item.Username, &item.Domain, &item.RegistrationCost, &item.RecordCount); err != nil {
			return PageResult[AdminSubdomainSummary]{}, err
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return PageResult[AdminSubdomainSummary]{}, err
	}
	if !page.Enabled() {
		total = int64(len(items))
		page = PageQuery{Page: 1, PageSize: len(items)}
	}
	return PageResult[AdminSubdomainSummary]{Items: items, Total: total, Page: page.Page, PageSize: page.PageSize}, nil
}

func joinRecordName(name string, domain string) string {
	name = strings.TrimSpace(name)
	domain = strings.TrimSpace(domain)
	if name == "" {
		return domain
	}
	if domain == "" {
		return name
	}
	if name == "@" {
		return domain
	}
	return name + "." + domain
}

func (r *AdminRepository) ListSettings(ctx context.Context, secret string) ([]SettingSummary, error) {
	rows, err := r.DB.QueryContext(ctx, `SELECT key, COALESCE(value, '') FROM settings
		WHERE key IN ('array_user', 'array_dns', 'array_turnstile', 'reserve_domain_name')
		ORDER BY CASE key
			WHEN 'array_user' THEN 1
			WHEN 'array_turnstile' THEN 2
			WHEN 'array_dns' THEN 3
			WHEN 'reserve_domain_name' THEN 4
			ELSE 99
		END`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	items := []SettingSummary{}
	for rows.Next() {
		var item SettingSummary
		if err := rows.Scan(&item.Key, &item.Value); err != nil {
			return nil, err
		}
		if item.Key == "array_turnstile" {
			masked, err := MaskTurnstileSettings(item.Value)
			if err != nil {
				return nil, err
			}
			item.Value = masked
		}
		items = append(items, item)
	}
	return items, rows.Err()
}

func (r *AdminRepository) UpsertSettings(ctx context.Context, settings map[string]string, secret string) error {
	tx, err := r.DB.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	nextValues := make(map[string]string, len(settings))
	for key, value := range settings {
		nextValues[strings.TrimSpace(key)] = value
	}
	for key, value := range nextValues {
		key = strings.TrimSpace(key)
		if key == "" {
			continue
		}
		if !isManagedSettingKey(key) {
			continue
		}
		if key == "array_turnstile" {
			var existing string
			_ = tx.QueryRowContext(ctx, `SELECT COALESCE(value, '') FROM settings WHERE key = 'array_turnstile'`).Scan(&existing)
			protected, err := ProtectTurnstileSettings(value, existing, secret)
			if err != nil {
				_ = tx.Rollback()
				return err
			}
			value = protected
		}
		if _, err := tx.ExecContext(ctx, `INSERT INTO settings(key, value, created_at, updated_at)
			VALUES (?, ?, strftime('%s','now'), strftime('%s','now'))
			ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = strftime('%s','now')`, key, value); err != nil {
			_ = tx.Rollback()
			return err
		}
	}
	return tx.Commit()
}

func isManagedSettingKey(key string) bool {
	switch key {
	case "array_user", "reserve_domain_name", "array_dns", "array_turnstile":
		return true
	default:
		return false
	}
}

func boolInt(value int) int {
	if value != 0 {
		return 1
	}
	return 0
}

func boolStatus(value int) int {
	if value < 0 {
		return 0
	}
	if value > 2 {
		return 2
	}
	return value
}

func maxInt64(value int64, min int64) int64 {
	if value < min {
		return min
	}
	return value
}

func likeTerm(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "" {
		return ""
	}
	return "%" + value + "%"
}
