package providers

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"

	"kldns/pkg/dns"
	"kldns/pkg/dns/providerhttp"
)

const dnsdunDefaultBaseURL = "https://api.dnsdun.com/"

func init() {
	dns.Register("DnsDun", func() dns.Provider {
		return &dnsdunProvider{
			baseURL: dnsdunDefaultBaseURL,
			client:  providerhttp.NewClient(),
		}
	})
}

type dnsdunProvider struct {
	uid     string
	apiKey  string
	baseURL string
	client  *http.Client
}

type dnsdunResponse struct {
	Status  dnsdunStatus   `json:"status"`
	Domains []dnsdunDomain `json:"domains"`
	Records []dnsdunRecord `json:"records"`
	Record  dnsdunRecord   `json:"record"`
}

type dnsdunStatus struct {
	Code    json.RawMessage `json:"code"`
	Message string          `json:"message"`
}

type dnsdunDomain struct {
	Domain string `json:"domain"`
}

type dnsdunRecord struct {
	ID         json.RawMessage `json:"id"`
	Name       string          `json:"name"`
	SubDomain  string          `json:"sub_domain"`
	Type       string          `json:"type"`
	RecordType string          `json:"record_type"`
	Value      string          `json:"value"`
	RecordLine string          `json:"record_line"`
}

func (p *dnsdunProvider) Key() string {
	return "DnsDun"
}

func (p *dnsdunProvider) Label() string {
	return "DnsDun"
}

func (p *dnsdunProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "UID", Label: "UID", Required: true, Secret: true},
		{Name: "API_KEY", Label: "API_KEY", Required: true, Secret: true},
	}
}

func (p *dnsdunProvider) Configure(config map[string]string) error {
	p.uid = strings.TrimSpace(config["UID"])
	p.apiKey = strings.TrimSpace(config["API_KEY"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], dnsdunDefaultBaseURL, true)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	return nil
}

func (p *dnsdunProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *dnsdunProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	ret, err := p.doForm(ctx, "c=domain&a=getList", map[string]string{"length": "100"}, "list_zones")
	if err != nil {
		return nil, err
	}
	zones := make([]dns.Zone, 0, len(ret.Domains))
	for _, domain := range ret.Domains {
		zones = append(zones, dns.Zone{ID: domain.Domain, Domain: domain.Domain})
	}
	return zones, nil
}

func (p *dnsdunProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	names := []string{"默认", "电信", "移动", "联通", "铁通", "教育网"}
	lines := make([]dns.RecordLine, 0, len(names))
	for _, name := range names {
		lines = append(lines, dns.RecordLine{ID: name, Name: name})
	}
	return lines, nil
}

func (p *dnsdunProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"domain":      zone.Domain,
		"sub_domain":  dnsdunHost(input.Name),
		"record_type": strings.ToUpper(strings.TrimSpace(input.Type)),
		"value":       strings.TrimSpace(input.Value),
		"record_line": dnsdunLineID(input.LineID),
	}
	ret, err := p.doForm(ctx, "c=record&a=add", params, "create_record")
	if err != nil {
		return dns.Record{}, err
	}
	result := dnsdunRecordToDomain(ret.Record, params)
	if result.RemoteID == "" {
		return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "create_record", Message: "missing record id in response"}
	}
	return result, nil
}

func (p *dnsdunProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"domain":      zone.Domain,
		"record_id":   strings.TrimSpace(remoteID),
		"sub_domain":  dnsdunHost(input.Name),
		"record_type": strings.ToUpper(strings.TrimSpace(input.Type)),
		"value":       strings.TrimSpace(input.Value),
		"record_line": dnsdunLineID(input.LineID),
	}
	ret, err := p.doForm(ctx, "c=record&a=modify", params, "update_record")
	if err != nil {
		return dns.Record{}, err
	}
	result := dnsdunRecordToDomain(ret.Record, params)
	if result.RemoteID == "" {
		result.RemoteID = remoteID
	}
	return result, nil
}

func (p *dnsdunProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	_, err := p.doForm(ctx, "c=record&a=del", map[string]string{"domain": zone.Domain, "record_id": strings.TrimSpace(remoteID)}, "delete_record")
	return err
}

func (p *dnsdunProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	ret, err := p.doForm(ctx, "c=record&a=info", map[string]string{"domain": zone.Domain, "record_id": strings.TrimSpace(remoteID)}, "get_record")
	if err != nil {
		return dns.Record{}, err
	}
	return dnsdunRecordToDomain(ret.Record, map[string]string{"record_id": remoteID}), nil
}

func (p *dnsdunProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	ret, err := p.doForm(ctx, "c=record&a=list", map[string]string{"domain": zone.Domain, "length": "100"}, "list_records")
	if err != nil {
		return nil, err
	}
	records := make([]dns.Record, 0, len(ret.Records))
	for _, record := range ret.Records {
		records = append(records, dnsdunRecordToDomain(record, nil))
	}
	return records, nil
}

func (p *dnsdunProvider) doForm(ctx context.Context, action string, params map[string]string, operation string) (dnsdunResponse, error) {
	if err := p.validateAuth(operation); err != nil {
		return dnsdunResponse{}, err
	}
	form := url.Values{}
	form.Set("uid", p.uid)
	form.Set("api_key", p.apiKey)
	form.Set("format", "json")
	form.Set("lang", "cn")
	for key, value := range params {
		form.Set(key, value)
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.endpoint(action), strings.NewReader(form.Encode()))
	if err != nil {
		return dnsdunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := p.client.Do(req)
	if err != nil {
		return dnsdunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return dnsdunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var ret dnsdunResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return dnsdunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || rawString(ret.Status.Code) != "1" {
		return dnsdunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: dnsdunErrorMessage(resp.StatusCode, ret.Status.Message)}
	}
	return ret, nil
}

func (p *dnsdunProvider) endpoint(action string) string {
	return strings.TrimRight(p.baseURL, "/") + "/?" + strings.TrimLeft(action, "?")
}

func (p *dnsdunProvider) validateAuth(operation string) error {
	if p.uid != "" && p.apiKey != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing UID or API_KEY"}
}

func dnsdunRecordToDomain(record dnsdunRecord, fallback map[string]string) dns.Record {
	name := record.Name
	if name == "" {
		name = record.SubDomain
	}
	if name == "" && fallback != nil {
		name = fallback["sub_domain"]
	}
	recordType := record.Type
	if recordType == "" {
		recordType = record.RecordType
	}
	if recordType == "" && fallback != nil {
		recordType = fallback["record_type"]
	}
	value := record.Value
	if value == "" && fallback != nil {
		value = fallback["value"]
	}
	line := record.RecordLine
	if line == "" && fallback != nil {
		line = fallback["record_line"]
	}
	line = dnsdunLineID(line)
	remoteID := rawString(record.ID)
	if remoteID == "" && fallback != nil {
		remoteID = fallback["record_id"]
	}
	return dns.Record{RemoteID: remoteID, Name: name, Type: recordType, Value: value, LineID: line, Line: line}
}

func dnsdunHost(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "@"
	}
	return name
}

func dnsdunLineID(lineID string) string {
	lineID = strings.TrimSpace(lineID)
	if lineID == "" || lineID == "0" {
		return "默认"
	}
	return lineID
}

func dnsdunErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("DnsDun API returned HTTP %d", status)
	}
	return "DnsDun API request failed"
}
