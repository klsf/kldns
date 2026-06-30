package providers

import (
	"bytes"
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha1"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"encoding/pem"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"sort"
	"strings"
	"time"

	"kldns/pkg/dns"
	"kldns/pkg/dns/providerhttp"
)

const (
	googleDNSDefaultBaseURL = "https://dns.googleapis.com/dns/v1"
	googleDefaultTokenURL   = "https://oauth2.googleapis.com/token"
	googleDNSScope          = "https://www.googleapis.com/auth/ndev.clouddns.readwrite"
)

func init() {
	dns.Register("GoogleCloudDns", func() dns.Provider {
		return &googleProvider{
			baseURL: googleDNSDefaultBaseURL,
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now().UTC() },
		}
	})
}

type googleProvider struct {
	serviceAccount googleServiceAccount
	projectID      string
	baseURL        string
	client         *http.Client
	now            func() time.Time
	accessToken    string
	tokenExpiresAt time.Time
}

type googleServiceAccount struct {
	ProjectID    string `json:"project_id"`
	ClientEmail  string `json:"client_email"`
	PrivateKey   string `json:"private_key"`
	PrivateKeyID string `json:"private_key_id"`
	TokenURI     string `json:"token_uri"`
}

type googleTokenResponse struct {
	AccessToken      string `json:"access_token"`
	ExpiresIn        int    `json:"expires_in"`
	Error            string `json:"error"`
	ErrorDescription string `json:"error_description"`
}

type googleManagedZonesResponse struct {
	ManagedZones  []googleManagedZone `json:"managedZones"`
	NextPageToken string              `json:"nextPageToken"`
	Error         googleAPIError      `json:"error"`
	Message       string              `json:"message"`
}

type googleManagedZone struct {
	Name       string `json:"name"`
	DNSName    string `json:"dnsName"`
	Visibility string `json:"visibility"`
}

type googleRRSetResponse struct {
	RRSets        []googleRRSet  `json:"rrsets"`
	NextPageToken string         `json:"nextPageToken"`
	Error         googleAPIError `json:"error"`
	Message       string         `json:"message"`
}

type googleRRSet struct {
	Name    string   `json:"name"`
	Type    string   `json:"type"`
	TTL     int      `json:"ttl"`
	RRDatas []string `json:"rrdatas"`
}

type googleChangeResponse struct {
	ID      string         `json:"id"`
	Status  string         `json:"status"`
	Error   googleAPIError `json:"error"`
	Message string         `json:"message"`
}

type googleAPIError struct {
	Message string `json:"message"`
}

func (p *googleProvider) Key() string {
	return "GoogleCloudDns"
}

func (p *googleProvider) Label() string {
	return "Google Cloud DNS"
}

func (p *googleProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "ProjectId", Label: "ProjectId", Description: "Optional; defaults to service account project_id"},
		{Name: "ServiceAccountJson", Label: "ServiceAccountJson", Required: true, Secret: true, Description: "Google Cloud service account JSON"},
	}
}

func (p *googleProvider) Configure(config map[string]string) error {
	p.serviceAccount = googleServiceAccount{}
	if raw := strings.TrimSpace(config["ServiceAccountJson"]); raw != "" {
		_ = json.Unmarshal([]byte(raw), &p.serviceAccount)
	}
	p.projectID = strings.TrimSpace(config["ProjectId"])
	if p.projectID == "" {
		p.projectID = strings.TrimSpace(p.serviceAccount.ProjectID)
	}
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], googleDNSDefaultBaseURL, false)
	if tokenURL := strings.TrimSpace(config["TokenURL"]); tokenURL != "" {
		p.serviceAccount.TokenURI = tokenURL
	}
	if p.serviceAccount.TokenURI == "" {
		p.serviceAccount.TokenURI = googleDefaultTokenURL
	}
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now().UTC() }
	}
	p.accessToken = ""
	p.tokenExpiresAt = time.Time{}
	return nil
}

func (p *googleProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *googleProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	pageToken := ""
	for {
		query := map[string]string{"maxResults": "100"}
		if pageToken != "" {
			query["pageToken"] = pageToken
		}
		var payload googleManagedZonesResponse
		if err := p.doJSON(ctx, http.MethodGet, "/projects/"+url.PathEscape(p.projectID)+"/managedZones", query, nil, &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, zone := range payload.ManagedZones {
			if zone.Visibility != "" && zone.Visibility != "public" {
				continue
			}
			if strings.TrimSpace(zone.Name) == "" || strings.TrimSpace(zone.DNSName) == "" {
				continue
			}
			zones = append(zones, dns.Zone{ID: strings.TrimSpace(zone.Name), Domain: strings.TrimSuffix(strings.TrimSpace(zone.DNSName), ".")})
		}
		pageToken = strings.TrimSpace(payload.NextPageToken)
		if pageToken == "" {
			break
		}
	}
	return zones, nil
}

func (p *googleProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{{ID: "default", Name: "默认"}}, nil
}

func (p *googleProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	record := googleRecordFromInput(zone, input)
	body := map[string]any{"additions": []googleRRSet{record}}
	if err := p.doJSON(ctx, http.MethodPost, "/projects/"+url.PathEscape(p.projectID)+"/managedZones/"+url.PathEscape(zone.ID)+"/changes", nil, body, nil, "create_record"); err != nil {
		return dns.Record{}, err
	}
	return googleRecordToDomain(record, zone.Domain), nil
}

func (p *googleProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	current, err := p.findRecord(ctx, zone, remoteID)
	if err != nil {
		return dns.Record{}, err
	}
	next := googleRecordFromInput(zone, input)
	body := map[string]any{"deletions": []googleRRSet{current}, "additions": []googleRRSet{next}}
	if err := p.doJSON(ctx, http.MethodPost, "/projects/"+url.PathEscape(p.projectID)+"/managedZones/"+url.PathEscape(zone.ID)+"/changes", nil, body, nil, "update_record"); err != nil {
		return dns.Record{}, err
	}
	return googleRecordToDomain(next, zone.Domain), nil
}

func (p *googleProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	current, err := p.findRecord(ctx, zone, remoteID)
	if err != nil {
		return err
	}
	return p.doJSON(ctx, http.MethodPost, "/projects/"+url.PathEscape(p.projectID)+"/managedZones/"+url.PathEscape(zone.ID)+"/changes", nil, map[string]any{"deletions": []googleRRSet{current}}, nil, "delete_record")
}

func (p *googleProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	record, err := p.findRecord(ctx, zone, remoteID)
	if err != nil {
		return dns.Record{}, err
	}
	return googleRecordToDomain(record, zone.Domain), nil
}

func (p *googleProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	sets, err := p.listRRSets(ctx, zone)
	if err != nil {
		return nil, err
	}
	records := make([]dns.Record, 0, len(sets))
	for _, set := range sets {
		records = append(records, googleRecordToDomain(set, zone.Domain))
	}
	return records, nil
}

func (p *googleProvider) listRRSets(ctx context.Context, zone dns.Zone) ([]googleRRSet, error) {
	var records []googleRRSet
	pageToken := ""
	for {
		query := map[string]string{"maxResults": "100"}
		if pageToken != "" {
			query["pageToken"] = pageToken
		}
		var payload googleRRSetResponse
		if err := p.doJSON(ctx, http.MethodGet, "/projects/"+url.PathEscape(p.projectID)+"/managedZones/"+url.PathEscape(zone.ID)+"/rrsets", query, nil, &payload, "list_records"); err != nil {
			return nil, err
		}
		for _, record := range payload.RRSets {
			if strings.TrimSpace(record.Name) == "" || strings.TrimSpace(record.Type) == "" {
				continue
			}
			records = append(records, record)
		}
		pageToken = strings.TrimSpace(payload.NextPageToken)
		if pageToken == "" {
			break
		}
	}
	return records, nil
}

func (p *googleProvider) findRecord(ctx context.Context, zone dns.Zone, remoteID string) (googleRRSet, error) {
	records, err := p.listRRSets(ctx, zone)
	if err != nil {
		return googleRRSet{}, err
	}
	for _, record := range records {
		if googleRecordID(googleExtractHost(record.Name, zone.Domain), record.Type) == strings.TrimSpace(remoteID) {
			return record, nil
		}
	}
	return googleRRSet{}, &dns.ProviderError{Provider: p.Key(), Operation: "get_record", Message: "record not found"}
}

func (p *googleProvider) doJSON(ctx context.Context, method string, path string, query map[string]string, body any, out any, operation string) error {
	token, err := p.getAccessToken(ctx, operation)
	if err != nil {
		return err
	}
	payload := ""
	if body != nil {
		data, err := json.Marshal(body)
		if err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "encode request failed", Cause: err}
		}
		payload = string(data)
	}
	u, err := url.Parse(p.baseURL + path)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "build request failed", Cause: err}
	}
	u.RawQuery = googleQuery(query)
	req, err := http.NewRequestWithContext(ctx, method, u.String(), bytes.NewBufferString(payload))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	resp, err := p.client.Do(req)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		var errorBody googleChangeResponse
		_ = json.Unmarshal(data, &errorBody)
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: googleErrorMessage(resp.StatusCode, errorBody)}
	}
	if out != nil && len(data) > 0 {
		if err := json.Unmarshal(data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed", Cause: err}
		}
	}
	return nil
}

func (p *googleProvider) getAccessToken(ctx context.Context, operation string) (string, error) {
	if strings.TrimSpace(p.accessToken) != "" && p.tokenExpiresAt.Add(-60*time.Second).After(p.now()) {
		return p.accessToken, nil
	}
	if strings.TrimSpace(p.projectID) == "" {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing ProjectId"}
	}
	if strings.TrimSpace(p.serviceAccount.ClientEmail) == "" || strings.TrimSpace(p.serviceAccount.PrivateKey) == "" {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "Google Cloud service account config is incomplete"}
	}
	assertion, err := p.buildJWT()
	if err != nil {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "Google Cloud private key signing failed", Cause: err}
	}
	form := url.Values{}
	form.Set("grant_type", "urn:ietf:params:oauth:grant-type:jwt-bearer")
	form.Set("assertion", assertion)
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.serviceAccount.TokenURI, strings.NewReader(form.Encode()))
	if err != nil {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create token request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := p.client.Do(req)
	if err != nil {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "token request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read token response failed", Cause: err}
	}
	var token googleTokenResponse
	_ = json.Unmarshal(data, &token)
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || strings.TrimSpace(token.AccessToken) == "" {
		return "", &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: googleTokenErrorMessage(resp.StatusCode, token)}
	}
	expiresIn := token.ExpiresIn
	if expiresIn <= 0 {
		expiresIn = 3600
	}
	p.accessToken = token.AccessToken
	p.tokenExpiresAt = p.now().Add(time.Duration(expiresIn) * time.Second)
	return p.accessToken, nil
}

func (p *googleProvider) buildJWT() (string, error) {
	now := p.now().Unix()
	header := map[string]any{"alg": "RS256", "typ": "JWT"}
	if strings.TrimSpace(p.serviceAccount.PrivateKeyID) != "" {
		header["kid"] = strings.TrimSpace(p.serviceAccount.PrivateKeyID)
	}
	claims := map[string]any{
		"iss":   strings.TrimSpace(p.serviceAccount.ClientEmail),
		"scope": googleDNSScope,
		"aud":   strings.TrimSpace(p.serviceAccount.TokenURI),
		"iat":   now,
		"exp":   now + 3600,
	}
	headerJSON, err := json.Marshal(header)
	if err != nil {
		return "", err
	}
	claimsJSON, err := json.Marshal(claims)
	if err != nil {
		return "", err
	}
	unsigned := base64.RawURLEncoding.EncodeToString(headerJSON) + "." + base64.RawURLEncoding.EncodeToString(claimsJSON)
	privateKey, err := parseGooglePrivateKey(p.serviceAccount.PrivateKey)
	if err != nil {
		return "", err
	}
	hash := sha256.Sum256([]byte(unsigned))
	signature, err := rsa.SignPKCS1v15(rand.Reader, privateKey, crypto.SHA256, hash[:])
	if err != nil {
		return "", err
	}
	return unsigned + "." + base64.RawURLEncoding.EncodeToString(signature), nil
}

func parseGooglePrivateKey(raw string) (*rsa.PrivateKey, error) {
	block, _ := pem.Decode([]byte(raw))
	if block == nil {
		return nil, fmt.Errorf("decode private key failed")
	}
	if key, err := x509.ParsePKCS8PrivateKey(block.Bytes); err == nil {
		if rsaKey, ok := key.(*rsa.PrivateKey); ok {
			return rsaKey, nil
		}
		return nil, fmt.Errorf("private key is not RSA")
	}
	if key, err := x509.ParsePKCS1PrivateKey(block.Bytes); err == nil {
		return key, nil
	}
	return nil, fmt.Errorf("parse private key failed")
}

func googleRecordFromInput(zone dns.Zone, input dns.RecordInput) googleRRSet {
	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	return googleRRSet{
		Name:    googleRecordName(input.Name, zone.Domain),
		Type:    recordType,
		TTL:     300,
		RRDatas: []string{googleFormatValue(recordType, input.Value)},
	}
}

func googleRecordToDomain(record googleRRSet, domain string) dns.Record {
	host := googleExtractHost(record.Name, domain)
	recordType := strings.ToUpper(strings.TrimSpace(record.Type))
	value := ""
	if len(record.RRDatas) > 0 {
		value = googleDisplayValue(recordType, record.RRDatas[0])
	}
	return dns.Record{
		RemoteID: googleRecordID(host, recordType),
		Name:     host,
		Type:     recordType,
		Value:    value,
		LineID:   "default",
		Line:     "默认",
	}
}

func googleRecordName(name string, domain string) string {
	name = strings.Trim(strings.TrimSpace(name), ".")
	domain = strings.Trim(strings.TrimSpace(domain), ".")
	if name == "" || name == "@" {
		return domain + "."
	}
	if name == domain || strings.HasSuffix(name, "."+domain) {
		return name + "."
	}
	return name + "." + domain + "."
}

func googleExtractHost(fqdn string, domain string) string {
	fqdn = strings.Trim(strings.TrimSpace(fqdn), ".")
	domain = strings.Trim(strings.TrimSpace(domain), ".")
	if fqdn == "" || fqdn == domain {
		return ""
	}
	suffix := "." + domain
	if domain != "" && strings.HasSuffix(fqdn, suffix) {
		return strings.TrimSuffix(fqdn, suffix)
	}
	return fqdn
}

func googleFormatValue(recordType string, value string) string {
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
	value = strings.TrimSpace(value)
	if recordType == "TXT" {
		return `"` + strings.ReplaceAll(strings.Trim(value, `"`), `"`, `\"`) + `"`
	}
	if recordType == "MX" {
		fields := strings.Fields(value)
		if len(fields) > 1 && fields[0] >= "0" && fields[0] <= "99999" {
			return value
		}
		return "10 " + strings.Trim(value, ".") + "."
	}
	if recordType == "SRV" {
		parts := strings.Fields(value)
		if len(parts) >= 4 {
			parts[len(parts)-1] = strings.Trim(parts[len(parts)-1], ".") + "."
			return strings.Join(parts, " ")
		}
	}
	if recordType == "CNAME" || recordType == "NS" {
		return strings.Trim(value, ".") + "."
	}
	return value
}

func googleDisplayValue(recordType string, value string) string {
	value = strings.TrimSpace(value)
	if recordType == "TXT" {
		return strings.Trim(value, `"`)
	}
	if recordType == "MX" {
		parts := strings.Fields(value)
		if len(parts) > 1 {
			return strings.Trim(parts[1], ".")
		}
	}
	return strings.Trim(value, ".")
}

func googleRecordID(name string, recordType string) string {
	sum := sha1.Sum([]byte(strings.ToLower(strings.TrimSpace(name)) + "|" + strings.ToUpper(strings.TrimSpace(recordType))))
	return hex.EncodeToString(sum[:])
}

func googleQuery(query map[string]string) string {
	if len(query) == 0 {
		return ""
	}
	keys := make([]string, 0, len(query))
	for key, value := range query {
		if strings.TrimSpace(value) != "" {
			keys = append(keys, key)
		}
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, url.PathEscape(key)+"="+url.PathEscape(query[key]))
	}
	return strings.Join(parts, "&")
}

func googleErrorMessage(status int, body googleChangeResponse) string {
	if strings.TrimSpace(body.Error.Message) != "" {
		return body.Error.Message
	}
	if strings.TrimSpace(body.Message) != "" {
		return body.Message
	}
	return fmt.Sprintf("GoogleCloudDns API returned HTTP %d", status)
}

func googleTokenErrorMessage(status int, token googleTokenResponse) string {
	if strings.TrimSpace(token.ErrorDescription) != "" {
		return token.ErrorDescription
	}
	if strings.TrimSpace(token.Error) != "" {
		return token.Error
	}
	return fmt.Sprintf("GoogleCloudDns token API returned HTTP %d", status)
}
