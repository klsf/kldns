package providers

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"sort"
	"strconv"
	"strings"
	"time"

	"kldns/pkg/dns"
	"kldns/pkg/dns/providerhttp"
)

const dnscomDefaultBaseURL = "https://openapi.dns.com/api/"

func init() {
	dns.Register("DnsCom", func() dns.Provider {
		return &dnscomProvider{
			baseURL: dnscomDefaultBaseURL,
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now() },
		}
	})
}

type dnscomProvider struct {
	apiKey    string
	apiSecret string
	baseURL   string
	client    *http.Client
	now       func() time.Time
}

type dnscomResponse struct {
	Code    int             `json:"code"`
	Message string          `json:"message"`
	Data    json.RawMessage `json:"data"`
}

type dnscomPage struct {
	Total int            `json:"total"`
	Data  []dnscomRecord `json:"data"`
}

type dnscomDomainPage struct {
	Total int            `json:"total"`
	Data  []dnscomDomain `json:"data"`
}

type dnscomDomain struct {
	DomainID json.RawMessage `json:"domain_id"`
	Domain   string          `json:"domain"`
}

type dnscomRecord struct {
	RecordID json.RawMessage `json:"record_id"`
	Record   string          `json:"record"`
	Type     string          `json:"type"`
	Value    string          `json:"value"`
	ViewID   json.RawMessage `json:"view_id"`
	ViewName string          `json:"view_name"`
}

type dnscomLine struct {
	ID       json.RawMessage `json:"id"`
	ViewName string          `json:"view_name"`
}

func (p *dnscomProvider) Key() string {
	return "DnsCom"
}

func (p *dnscomProvider) Label() string {
	return "DNS.com"
}

func (p *dnscomProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "ApiKey", Label: "ApiKey", Required: true, Secret: true},
		{Name: "ApiSecret", Label: "ApiSecret", Required: true, Secret: true},
	}
}

func (p *dnscomProvider) Configure(config map[string]string) error {
	p.apiKey = strings.TrimSpace(config["ApiKey"])
	p.apiSecret = strings.TrimSpace(config["ApiSecret"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], dnscomDefaultBaseURL, true)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now() }
	}
	return nil
}

func (p *dnscomProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *dnscomProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	page := 1
	for {
		params := map[string]string{"page": strconv.Itoa(page), "paginate": "100"}
		var payload dnscomDomainPage
		if err := p.doForm(ctx, "domain/lists/", params, &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, domain := range payload.Data {
			zones = append(zones, dns.Zone{ID: rawString(domain.DomainID), Domain: domain.Domain})
		}
		if len(zones) >= payload.Total || len(payload.Data) == 0 {
			break
		}
		page++
	}
	return zones, nil
}

func (p *dnscomProvider) ListRecordLines(ctx context.Context, zone dns.Zone) ([]dns.RecordLine, error) {
	domain := dnscomDomainKey(zone)
	if domain == "" {
		return []dns.RecordLine{{ID: "0", Name: "默认"}}, nil
	}
	var lines []dnscomLine
	if err := p.doForm(ctx, "domain/getView/", map[string]string{"domain": domain}, &lines, "list_record_lines"); err != nil {
		return []dns.RecordLine{{ID: "0", Name: "默认"}}, nil
	}
	out := make([]dns.RecordLine, 0, len(lines))
	for _, line := range lines {
		out = append(out, dns.RecordLine{ID: rawString(line.ID), Name: line.ViewName})
	}
	if len(out) == 0 {
		return []dns.RecordLine{{ID: "0", Name: "默认"}}, nil
	}
	return out, nil
}

func (p *dnscomProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"domain":  dnscomDomainKey(zone),
		"record":  dnscomHost(input.Name),
		"type":    strings.ToUpper(strings.TrimSpace(input.Type)),
		"value":   strings.TrimSpace(input.Value),
		"view_id": dnscomLineID(input.LineID),
	}
	var record dnscomRecord
	if err := p.doForm(ctx, "record/create/", params, &record, "create_record"); err != nil {
		return dns.Record{}, err
	}
	return dnscomRecordToDomain(record, params), nil
}

func (p *dnscomProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"domain":    dnscomDomainKey(zone),
		"record_id": strings.TrimSpace(remoteID),
		"record":    dnscomHost(input.Name),
		"type":      strings.ToUpper(strings.TrimSpace(input.Type)),
		"value":     strings.TrimSpace(input.Value),
		"view_id":   dnscomLineID(input.LineID),
	}
	var record dnscomRecord
	if err := p.doForm(ctx, "record/update/", params, &record, "update_record"); err != nil {
		return dns.Record{}, err
	}
	result := dnscomRecordToDomain(record, params)
	if result.RemoteID == "" {
		result.RemoteID = remoteID
	}
	return result, nil
}

func (p *dnscomProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	return p.doForm(ctx, "domain/operate/", map[string]string{
		"domain":    dnscomDomainKey(zone),
		"record_id": strings.TrimSpace(remoteID),
		"status":    "delete",
	}, nil, "delete_record")
}

func (p *dnscomProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	var record dnscomRecord
	if err := p.doForm(ctx, "record/getOne/", map[string]string{"domain": dnscomDomainKey(zone), "record_id": strings.TrimSpace(remoteID)}, &record, "get_record"); err != nil {
		return dns.Record{}, err
	}
	return dnscomRecordToDomain(record, map[string]string{"record_id": remoteID}), nil
}

func (p *dnscomProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	var records []dns.Record
	page := 1
	for {
		params := map[string]string{"domain": dnscomDomainKey(zone), "page": strconv.Itoa(page), "paginate": "100"}
		var payload dnscomPage
		if err := p.doForm(ctx, "record/lists/", params, &payload, "list_records"); err != nil {
			return nil, err
		}
		for _, record := range payload.Data {
			records = append(records, dnscomRecordToDomain(record, nil))
		}
		if len(records) >= payload.Total || len(payload.Data) == 0 {
			break
		}
		page++
	}
	return records, nil
}

func (p *dnscomProvider) doForm(ctx context.Context, action string, params map[string]string, out any, operation string) error {
	if err := p.validateAuth(operation); err != nil {
		return err
	}
	if params == nil {
		params = map[string]string{}
	}
	params["apiKey"] = p.apiKey
	params["timestamp"] = strconv.FormatInt(p.now().Unix(), 10)
	params["hash"] = dnscomHash(params, p.apiSecret)
	form := url.Values{}
	for key, value := range params {
		form.Set(key, value)
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.endpoint(action), strings.NewReader(form.Encode()))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := p.client.Do(req)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var ret dnscomResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || ret.Code != 0 {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: dnscomErrorMessage(resp.StatusCode, ret.Message)}
	}
	if out != nil && len(ret.Data) > 0 && string(ret.Data) != "null" {
		if err := json.Unmarshal(ret.Data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode result failed", Cause: err}
		}
	}
	return nil
}

func (p *dnscomProvider) endpoint(action string) string {
	return strings.TrimRight(p.baseURL, "/") + "/" + strings.TrimLeft(action, "/")
}

func (p *dnscomProvider) validateAuth(operation string) error {
	if p.apiKey != "" && p.apiSecret != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing ApiKey or ApiSecret"}
}

func dnscomHash(params map[string]string, secret string) string {
	keys := make([]string, 0, len(params))
	for key := range params {
		if key != "hash" {
			keys = append(keys, key)
		}
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, key+"="+params[key])
	}
	sum := md5.Sum([]byte(strings.Join(parts, "&") + secret))
	return hex.EncodeToString(sum[:])
}

func dnscomDomainKey(zone dns.Zone) string {
	if strings.TrimSpace(zone.ID) != "" {
		return strings.TrimSpace(zone.ID)
	}
	return strings.TrimSpace(zone.Domain)
}

func dnscomHost(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "@"
	}
	return name
}

func dnscomLineID(lineID string) string {
	lineID = strings.TrimSpace(lineID)
	if lineID == "" {
		return "0"
	}
	return lineID
}

func dnscomRecordToDomain(record dnscomRecord, fallback map[string]string) dns.Record {
	remoteID := rawString(record.RecordID)
	if remoteID == "" && fallback != nil {
		remoteID = fallback["record_id"]
	}
	name := record.Record
	if name == "" && fallback != nil {
		name = fallback["record"]
	}
	recordType := record.Type
	if recordType == "" && fallback != nil {
		recordType = fallback["type"]
	}
	value := record.Value
	if value == "" && fallback != nil {
		value = fallback["value"]
	}
	lineID := rawString(record.ViewID)
	if lineID == "" && fallback != nil {
		lineID = fallback["view_id"]
	}
	lineID = dnscomLineID(lineID)
	line := strings.TrimSpace(record.ViewName)
	if line == "" && lineID == "0" {
		line = "默认"
	}
	if line == "" {
		line = lineID
	}
	return dns.Record{RemoteID: remoteID, Name: name, Type: recordType, Value: value, LineID: lineID, Line: line}
}

func dnscomErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("DNS.com API returned HTTP %d", status)
	}
	return "DNS.com API request failed"
}
