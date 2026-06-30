package providers

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
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

const huaweiDefaultRegion = "cn-north-4"
const huaweiIntlRegion = "ap-southeast-3"

func init() {
	dns.Register("HuaweiCloud", func() dns.Provider {
		return &huaweiProvider{
			region:  huaweiDefaultRegion,
			baseURL: huaweiBaseURL(huaweiDefaultRegion),
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now().UTC() },
		}
	})
}

type huaweiProvider struct {
	accessKeyID     string
	secretAccessKey string
	region          string
	enterpriseID    string
	explicitRegion  bool
	customBaseURL   bool
	baseURL         string
	client          *http.Client
	now             func() time.Time
}

type huaweiZoneList struct {
	Zones []huaweiZone `json:"zones"`
	Links huaweiLinks  `json:"links"`
}

type huaweiZone struct {
	ID       string `json:"id"`
	Name     string `json:"name"`
	ZoneType string `json:"zone_type"`
}

type huaweiRecordSetList struct {
	RecordSets []huaweiRecordSet `json:"recordsets"`
	Links      huaweiLinks       `json:"links"`
}

type huaweiRecordSet struct {
	ID      string   `json:"id"`
	Name    string   `json:"name"`
	Type    string   `json:"type"`
	TTL     int      `json:"ttl"`
	Records []string `json:"records"`
}

type huaweiLinks struct {
	Next string `json:"next"`
}

type huaweiErrorResponse struct {
	Message  string `json:"message"`
	ErrorMsg string `json:"error_msg"`
}

func (p *huaweiProvider) Key() string {
	return "HuaweiCloud"
}

func (p *huaweiProvider) Label() string {
	return "华为云 DNS"
}

func (p *huaweiProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "AccessKeyId", Label: "AccessKeyId", Required: true, Secret: true},
		{Name: "SecretAccessKey", Label: "SecretAccessKey", Required: true, Secret: true},
		{Name: "Region", Label: "Region", Description: "可选；留空自动尝试 cn-north-4 和 ap-southeast-3"},
		{Name: "EnterpriseProjectId", Label: "EnterpriseProjectId", Description: "可选；默认企业项目填 0，非默认企业项目填企业项目 ID"},
	}
}

func (p *huaweiProvider) Configure(config map[string]string) error {
	p.accessKeyID = strings.TrimSpace(config["AccessKeyId"])
	p.secretAccessKey = strings.TrimSpace(config["SecretAccessKey"])
	p.region = strings.TrimSpace(config["Region"])
	p.explicitRegion = p.region != ""
	if p.region == "" {
		p.region = huaweiDefaultRegion
	}
	p.enterpriseID = strings.TrimSpace(config["EnterpriseProjectId"])
	if baseURL := strings.TrimSpace(config["BaseURL"]); baseURL != "" {
		p.customBaseURL = true
		p.baseURL = providerhttp.NormalizeBaseURL(baseURL, huaweiBaseURL(p.region), false)
	} else {
		p.customBaseURL = false
		p.baseURL = huaweiBaseURL(p.region)
	}
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now().UTC() }
	}
	return nil
}

func (p *huaweiProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *huaweiProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	if p.explicitRegion || p.customBaseURL {
		return p.listZones(ctx)
	}
	var firstErr error
	hadSuccess := false
	for _, region := range []string{huaweiDefaultRegion, huaweiIntlRegion} {
		p.region = region
		p.baseURL = huaweiBaseURL(region)
		zones, err := p.listZones(ctx)
		if err != nil {
			if firstErr == nil {
				firstErr = err
			}
			continue
		}
		hadSuccess = true
		if len(zones) > 0 {
			return zones, nil
		}
	}
	if hadSuccess {
		return []dns.Zone{}, nil
	}
	if firstErr != nil {
		return nil, firstErr
	}
	return []dns.Zone{}, nil
}

func (p *huaweiProvider) listZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	marker := ""
	for {
		query := map[string]string{"type": "public", "limit": "500"}
		if p.enterpriseID != "" {
			query["enterprise_project_id"] = p.enterpriseID
		}
		if marker != "" {
			query["marker"] = marker
		}
		var payload huaweiZoneList
		if err := p.doJSON(ctx, http.MethodGet, "/v2/zones", query, nil, &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, zone := range payload.Zones {
			if zone.ID == "" || zone.Name == "" {
				continue
			}
			if zone.ZoneType != "" && zone.ZoneType != "public" {
				continue
			}
			zones = append(zones, dns.Zone{ID: zone.ID, Domain: strings.TrimSuffix(zone.Name, ".")})
		}
		marker = huaweiNextMarker(payload.Links.Next)
		if marker == "" {
			break
		}
	}
	return zones, nil
}

func (p *huaweiProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{{ID: "default", Name: "默认"}}, nil
}

func (p *huaweiProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	body := map[string]any{
		"name":    huaweiRecordName(input.Name, zone.Domain),
		"type":    strings.ToUpper(strings.TrimSpace(input.Type)),
		"ttl":     300,
		"records": []string{huaweiFormatValue(input.Type, input.Value)},
	}
	var record huaweiRecordSet
	if err := p.doJSON(ctx, http.MethodPost, "/v2/zones/"+url.PathEscape(zone.ID)+"/recordsets", nil, body, &record, "create_record"); err != nil {
		return dns.Record{}, err
	}
	if record.ID == "" {
		return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "create_record", Message: "missing record id in response"}
	}
	return huaweiRecordToDomain(record, zone.Domain), nil
}

func (p *huaweiProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	body := map[string]any{
		"name":    huaweiRecordName(input.Name, zone.Domain),
		"type":    strings.ToUpper(strings.TrimSpace(input.Type)),
		"ttl":     300,
		"records": []string{huaweiFormatValue(input.Type, input.Value)},
	}
	var record huaweiRecordSet
	if err := p.doJSON(ctx, http.MethodPut, "/v2/zones/"+url.PathEscape(zone.ID)+"/recordsets/"+url.PathEscape(remoteID), nil, body, &record, "update_record"); err != nil {
		return dns.Record{}, err
	}
	if record.ID == "" {
		record.ID = remoteID
	}
	return huaweiRecordToDomain(record, zone.Domain), nil
}

func (p *huaweiProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	return p.doJSON(ctx, http.MethodDelete, "/v2/zones/"+url.PathEscape(zone.ID)+"/recordsets/"+url.PathEscape(remoteID), nil, nil, nil, "delete_record")
}

func (p *huaweiProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	var record huaweiRecordSet
	if err := p.doJSON(ctx, http.MethodGet, "/v2/zones/"+url.PathEscape(zone.ID)+"/recordsets/"+url.PathEscape(remoteID), nil, nil, &record, "get_record"); err != nil {
		return dns.Record{}, err
	}
	return huaweiRecordToDomain(record, zone.Domain), nil
}

func (p *huaweiProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	var records []dns.Record
	marker := ""
	for {
		query := map[string]string{"limit": "500"}
		if marker != "" {
			query["marker"] = marker
		}
		var payload huaweiRecordSetList
		if err := p.doJSON(ctx, http.MethodGet, "/v2/zones/"+url.PathEscape(zone.ID)+"/recordsets", query, nil, &payload, "list_records"); err != nil {
			return nil, err
		}
		for _, record := range payload.RecordSets {
			records = append(records, huaweiRecordToDomain(record, zone.Domain))
		}
		marker = huaweiNextMarker(payload.Links.Next)
		if marker == "" {
			break
		}
	}
	return records, nil
}

func (p *huaweiProvider) doJSON(ctx context.Context, method string, path string, query map[string]string, body any, out any, operation string) error {
	if err := p.validateAuth(operation); err != nil {
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
	u.RawQuery = huaweiCanonicalQuery(query)
	req, err := http.NewRequestWithContext(ctx, method, u.String(), bytes.NewBufferString(payload))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	date := p.now().UTC().Format("20060102T150405Z")
	host := u.Host
	auth := huaweiAuthorization(method, path, query, payload, host, date, p.accessKeyID, p.secretAccessKey)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Host", host)
	req.Header.Set("X-Sdk-Date", date)
	req.Header.Set("Authorization", auth)
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
		var errorBody huaweiErrorResponse
		_ = json.Unmarshal(data, &errorBody)
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: huaweiErrorMessage(resp.StatusCode, errorBody)}
	}
	if out != nil && len(data) > 0 {
		if err := json.Unmarshal(data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed", Cause: err}
		}
	}
	return nil
}

func (p *huaweiProvider) validateAuth(operation string) error {
	if p.accessKeyID != "" && p.secretAccessKey != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing AccessKeyId or SecretAccessKey"}
}

func huaweiBaseURL(region string) string {
	region = strings.TrimSpace(region)
	if region == "" {
		region = huaweiDefaultRegion
	}
	return "https://dns." + region + ".myhuaweicloud.com"
}

func huaweiAuthorization(method string, path string, query map[string]string, payload string, host string, date string, accessKey string, secret string) string {
	headers := map[string]string{
		"content-type": "application/json",
		"host":         host,
		"x-sdk-date":   date,
	}
	headerKeys := []string{"content-type", "host", "x-sdk-date"}
	canonicalHeaders := ""
	for _, key := range headerKeys {
		canonicalHeaders += key + ":" + strings.TrimSpace(headers[key]) + "\n"
	}
	signedHeaders := strings.Join(headerKeys, ";")
	canonicalRequest := strings.ToUpper(method) + "\n" +
		huaweiNormalizePath(path) + "\n" +
		huaweiCanonicalQuery(query) + "\n" +
		canonicalHeaders + "\n" +
		signedHeaders + "\n" +
		sha256Hex(payload)
	stringToSign := "SDK-HMAC-SHA256\n" + date + "\n" + sha256Hex(canonicalRequest)
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(stringToSign))
	signature := hex.EncodeToString(mac.Sum(nil))
	return "SDK-HMAC-SHA256 Access=" + accessKey + ", SignedHeaders=" + signedHeaders + ", Signature=" + signature
}

func huaweiNormalizePath(path string) string {
	segments := strings.Split(strings.TrimLeft(path, "/"), "/")
	for i, segment := range segments {
		decoded, err := url.PathUnescape(segment)
		if err == nil {
			segment = decoded
		}
		segments[i] = url.PathEscape(segment)
	}
	normalized := "/" + strings.Join(segments, "/")
	if !strings.HasSuffix(normalized, "/") {
		normalized += "/"
	}
	return normalized
}

func huaweiCanonicalQuery(query map[string]string) string {
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
		parts = append(parts, url.QueryEscape(key)+"="+url.QueryEscape(query[key]))
	}
	return strings.Join(parts, "&")
}

func huaweiRecordToDomain(record huaweiRecordSet, domain string) dns.Record {
	value := ""
	if len(record.Records) > 0 {
		value = huaweiNormalizeValue(record.Type, record.Records[0])
	}
	return dns.Record{
		RemoteID: record.ID,
		Name:     huaweiExtractHost(record.Name, domain),
		Type:     strings.ToUpper(strings.TrimSpace(record.Type)),
		Value:    value,
		LineID:   "default",
		Line:     "默认",
	}
}

func huaweiRecordName(name string, domain string) string {
	name = strings.TrimSpace(name)
	domain = strings.Trim(strings.TrimSpace(domain), ".")
	if name == "" || name == "@" {
		return domain + "."
	}
	name = strings.Trim(name, ".")
	if domain != "" && strings.HasSuffix(name, "."+domain) {
		return name + "."
	}
	return strings.Trim(name+"."+domain, ".") + "."
}

func huaweiExtractHost(fqdn string, domain string) string {
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

func huaweiFormatValue(recordType string, value string) string {
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
	value = strings.TrimSpace(value)
	if recordType == "MX" {
		parts := strings.Fields(value)
		if len(parts) > 1 {
			return strings.TrimSpace(parts[0]) + " " + strings.Trim(parts[1], ".") + "."
		}
		return "10 " + strings.Trim(value, ".") + "."
	}
	if recordType == "CNAME" || recordType == "NS" {
		return strings.Trim(value, ".") + "."
	}
	return value
}

func huaweiNormalizeValue(recordType string, value string) string {
	recordType = strings.ToUpper(strings.TrimSpace(recordType))
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

func huaweiNextMarker(nextLink string) string {
	if strings.TrimSpace(nextLink) == "" {
		return ""
	}
	parsed, err := url.Parse(nextLink)
	if err != nil {
		return ""
	}
	return parsed.Query().Get("marker")
}

func huaweiErrorMessage(status int, body huaweiErrorResponse) string {
	if strings.TrimSpace(body.Message) != "" {
		return body.Message
	}
	if strings.TrimSpace(body.ErrorMsg) != "" {
		return body.ErrorMsg
	}
	return fmt.Sprintf("HuaweiCloud API returned HTTP %d", status)
}

func sha256Hex(value string) string {
	sum := sha256.Sum256([]byte(value))
	return hex.EncodeToString(sum[:])
}
