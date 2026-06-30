package providers

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"

	"kldns/pkg/dns"
	"kldns/pkg/dns/providerhttp"
)

const dnslaDefaultBaseURL = "https://api.dns.la"

func init() {
	dns.Register("DnsLa", func() dns.Provider {
		return &dnslaProvider{
			baseURL: dnslaDefaultBaseURL,
			client:  providerhttp.NewClient(),
		}
	})
}

var dnslaRecordTypes = map[string]int{
	"A": 1, "NS": 2, "CNAME": 5, "MX": 15, "TXT": 16,
	"AAAA": 28, "SRV": 33, "URL": 256, "CAA": 257,
}

var dnslaRecordTypeNames = map[int]string{
	1: "A", 2: "NS", 5: "CNAME", 15: "MX", 16: "TXT",
	28: "AAAA", 33: "SRV", 256: "URL", 257: "CAA",
}

type dnslaProvider struct {
	apiID     string
	apiSecret string
	baseURL   string
	client    *http.Client
}

type dnslaResponse struct {
	Code int             `json:"code"`
	Msg  string          `json:"msg"`
	Data json.RawMessage `json:"data"`
}

type dnslaPage struct {
	Total   int           `json:"total"`
	Results []dnslaRecord `json:"results"`
}

type dnslaDomainPage struct {
	Total   int           `json:"total"`
	Results []dnslaDomain `json:"results"`
}

type dnslaDomain struct {
	ID            json.RawMessage `json:"id"`
	Domain        string          `json:"domain"`
	DisplayDomain string          `json:"displayDomain"`
}

type dnslaRecord struct {
	ID          json.RawMessage `json:"id"`
	Host        string          `json:"host"`
	DisplayHost string          `json:"displayHost"`
	Type        int             `json:"type"`
	Data        string          `json:"data"`
	DisplayData string          `json:"displayData"`
	LineID      json.RawMessage `json:"lineId"`
	LineName    string          `json:"lineName"`
}

type dnslaLine struct {
	ID       json.RawMessage `json:"id"`
	Name     string          `json:"name"`
	Value    string          `json:"value"`
	Children []dnslaLine     `json:"children"`
}

func (p *dnslaProvider) Key() string {
	return "DnsLa"
}

func (p *dnslaProvider) Label() string {
	return "DNSLA"
}

func (p *dnslaProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "ApiId", Label: "ApiId", Required: true, Secret: true},
		{Name: "ApiSecret", Label: "ApiSecret", Required: true, Secret: true},
	}
}

func (p *dnslaProvider) Configure(config map[string]string) error {
	p.apiID = strings.TrimSpace(config["ApiId"])
	p.apiSecret = strings.TrimSpace(config["ApiSecret"])
	if p.apiSecret == "" {
		p.apiSecret = strings.TrimSpace(config["ApiKey"])
	}
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], dnslaDefaultBaseURL, false)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	return nil
}

func (p *dnslaProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *dnslaProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	page := 1
	for {
		var payload dnslaDomainPage
		if err := p.doJSON(ctx, http.MethodGet, "/api/domainList", map[string]string{
			"pageIndex": strconv.Itoa(page), "pageSize": "100",
		}, nil, &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, domain := range payload.Results {
			name := strings.TrimSuffix(domain.DisplayDomain, ".")
			if name == "" {
				name = strings.TrimSuffix(domain.Domain, ".")
			}
			zones = append(zones, dns.Zone{ID: rawString(domain.ID), Domain: name})
		}
		totalPages := max(1, (payload.Total+99)/100)
		if page >= totalPages || len(payload.Results) == 0 {
			break
		}
		page++
	}
	return zones, nil
}

func (p *dnslaProvider) ListRecordLines(ctx context.Context, zone dns.Zone) ([]dns.RecordLine, error) {
	path := "/api/allLineList"
	query := map[string]string{}
	compatible := false
	if strings.TrimSpace(zone.Domain) != "" {
		path = "/api/availableLine"
		query["domain"] = strings.TrimSuffix(zone.Domain, ".")
		compatible = true
	}
	var lines []dnslaLine
	if err := p.doJSON(ctx, http.MethodGet, path, query, nil, &lines, "list_record_lines"); err != nil {
		return []dns.RecordLine{{ID: "0", Name: "默认"}}, nil
	}
	out := []dns.RecordLine{{ID: "0", Name: "默认"}}
	out = append(out, dnslaFormatLines(lines, compatible)...)
	return out, nil
}

func (p *dnslaProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	payload := map[string]any{
		"domainId": zone.ID,
		"type":     dnslaTypeCode(input.Type),
		"host":     dnslaHost(input.Name),
		"data":     strings.TrimSpace(input.Value),
		"ttl":      600,
	}
	if lineID := dnslaLineID(input.LineID); lineID != "0" {
		payload["lineId"] = lineID
	}
	var record dnslaRecord
	if err := p.doJSON(ctx, http.MethodPost, "/api/record", nil, payload, &record, "create_record"); err != nil {
		return dns.Record{}, err
	}
	result := dnslaRecordToDomain(record, nil)
	if result.RemoteID == "" {
		result.RemoteID = rawString(record.ID)
	}
	if result.Name == "" {
		result.Name = dnslaHost(input.Name)
	}
	if result.Type == "" {
		result.Type = strings.ToUpper(strings.TrimSpace(input.Type))
	}
	if result.Value == "" {
		result.Value = strings.TrimSpace(input.Value)
	}
	if result.LineID == "" {
		result.LineID = dnslaLineID(input.LineID)
	}
	if result.Line == "" {
		result.Line = dnslaLineName(result.LineID)
	}
	return result, nil
}

func (p *dnslaProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	payload := map[string]any{
		"id":   strings.TrimSpace(remoteID),
		"type": dnslaTypeCode(input.Type),
		"host": dnslaHost(input.Name),
		"data": strings.TrimSpace(input.Value),
		"ttl":  600,
	}
	if lineID := dnslaLineID(input.LineID); lineID != "0" {
		payload["lineId"] = lineID
	}
	var record dnslaRecord
	if err := p.doJSON(ctx, http.MethodPut, "/api/record", nil, payload, &record, "update_record"); err != nil {
		return dns.Record{}, err
	}
	result := dnslaRecordToDomain(record, nil)
	if result.RemoteID == "" {
		result.RemoteID = remoteID
	}
	if result.Name == "" {
		result.Name = dnslaHost(input.Name)
	}
	if result.Type == "" {
		result.Type = strings.ToUpper(strings.TrimSpace(input.Type))
	}
	if result.Value == "" {
		result.Value = strings.TrimSpace(input.Value)
	}
	if result.LineID == "" {
		result.LineID = dnslaLineID(input.LineID)
	}
	if result.Line == "" {
		result.Line = dnslaLineName(result.LineID)
	}
	return result, nil
}

func (p *dnslaProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	return p.doJSON(ctx, http.MethodDelete, "/api/record", map[string]string{"id": strings.TrimSpace(remoteID)}, nil, nil, "delete_record")
}

func (p *dnslaProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	records, err := p.ListRecords(ctx, zone)
	if err != nil {
		return dns.Record{}, err
	}
	for _, record := range records {
		if record.RemoteID == remoteID {
			return record, nil
		}
	}
	return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "get_record", Message: "record not found"}
}

func (p *dnslaProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	var records []dns.Record
	page := 1
	for {
		var payload dnslaPage
		if err := p.doJSON(ctx, http.MethodGet, "/api/recordList", map[string]string{
			"pageIndex": strconv.Itoa(page), "pageSize": "100", "domainId": zone.ID,
		}, nil, &payload, "list_records"); err != nil {
			return nil, err
		}
		for _, record := range payload.Results {
			records = append(records, dnslaRecordToDomain(record, nil))
		}
		totalPages := max(1, (payload.Total+99)/100)
		if page >= totalPages || len(payload.Results) == 0 {
			break
		}
		page++
	}
	return records, nil
}

func (p *dnslaProvider) doJSON(ctx context.Context, method string, path string, query map[string]string, payload any, out any, operation string) error {
	if err := p.validateAuth(operation); err != nil {
		return err
	}
	u, err := url.Parse(p.baseURL + path)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "build request failed", Cause: err}
	}
	q := u.Query()
	for key, value := range query {
		q.Set(key, value)
	}
	u.RawQuery = q.Encode()
	var body io.Reader
	if method != http.MethodGet && method != http.MethodDelete && payload != nil {
		data, err := json.Marshal(payload)
		if err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "encode request failed", Cause: err}
		}
		body = bytes.NewReader(data)
	}
	req, err := http.NewRequestWithContext(ctx, method, u.String(), body)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Authorization", "Basic "+base64.StdEncoding.EncodeToString([]byte(p.apiID+":"+p.apiSecret)))
	req.Header.Set("Content-Type", "application/json; charset=utf-8")
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
	var ret dnslaResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || ret.Code != 200 {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: dnslaErrorMessage(resp.StatusCode, ret.Msg)}
	}
	if out != nil && len(ret.Data) > 0 && string(ret.Data) != "null" {
		if err := json.Unmarshal(ret.Data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode result failed", Cause: err}
		}
	}
	return nil
}

func (p *dnslaProvider) validateAuth(operation string) error {
	if p.apiID != "" && p.apiSecret != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing ApiId or ApiSecret"}
}

func dnslaRecordToDomain(record dnslaRecord, fallback map[string]string) dns.Record {
	name := record.DisplayHost
	if name == "" {
		name = record.Host
	}
	value := record.DisplayData
	if value == "" {
		value = record.Data
	}
	lineID := rawString(record.LineID)
	if lineID == "" {
		lineID = "0"
	}
	line := strings.TrimSpace(record.LineName)
	if line == "" {
		line = dnslaLineName(lineID)
	}
	return dns.Record{
		RemoteID: rawString(record.ID),
		Name:     name,
		Type:     dnslaTypeName(record.Type),
		Value:    value,
		LineID:   lineID,
		Line:     line,
	}
}

func dnslaTypeCode(recordType string) int {
	if parsed, err := strconv.Atoi(strings.TrimSpace(recordType)); err == nil {
		return parsed
	}
	code, ok := dnslaRecordTypes[strings.ToUpper(strings.TrimSpace(recordType))]
	if ok {
		return code
	}
	return 1
}

func dnslaTypeName(code int) string {
	if name, ok := dnslaRecordTypeNames[code]; ok {
		return name
	}
	if code == 0 {
		return ""
	}
	return strconv.Itoa(code)
}

func dnslaHost(host string) string {
	host = strings.TrimSpace(host)
	if host == "" {
		return "@"
	}
	return host
}

func dnslaLineID(lineID string) string {
	lineID = strings.TrimSpace(lineID)
	if lineID == "" {
		return "0"
	}
	return lineID
}

func dnslaLineName(lineID string) string {
	if lineID == "" || lineID == "0" {
		return "默认"
	}
	return lineID
}

func dnslaFormatLines(lines []dnslaLine, compatible bool) []dns.RecordLine {
	var out []dns.RecordLine
	for _, line := range lines {
		name := line.Name
		if compatible && line.Value != "" {
			name = line.Value
		}
		out = append(out, dns.RecordLine{ID: rawString(line.ID), Name: name})
		if !compatible && len(line.Children) > 0 {
			out = append(out, dnslaFormatLines(line.Children, false)...)
		}
	}
	return out
}

func dnslaErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("DNSLA API returned HTTP %d", status)
	}
	return "DNSLA API request failed"
}
