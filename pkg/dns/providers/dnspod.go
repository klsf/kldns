package providers

import (
	"context"
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

const dnspodDefaultBaseURL = "https://dnsapi.cn/"

func init() {
	dns.Register("Dnspod", func() dns.Provider {
		return &dnspodProvider{
			baseURL: dnspodDefaultBaseURL,
			client:  providerhttp.NewClient(),
		}
	})
}

type dnspodProvider struct {
	id      string
	token   string
	baseURL string
	client  *http.Client
}

type dnspodResponse struct {
	Status  dnspodStatus   `json:"status"`
	Domains []dnspodDomain `json:"domains"`
	Domain  dnspodDomain   `json:"domain"`
	Records []dnspodRecord `json:"records"`
	Record  dnspodRecord   `json:"record"`
}

type dnspodStatus struct {
	Code    json.RawMessage `json:"code"`
	Message string          `json:"message"`
}

type dnspodDomain struct {
	ID   json.RawMessage `json:"id"`
	Name string          `json:"name"`
}

type dnspodRecord struct {
	ID           json.RawMessage `json:"id"`
	Name         string          `json:"name"`
	SubDomain    string          `json:"sub_domain"`
	Type         string          `json:"type"`
	RecordType   string          `json:"record_type"`
	Value        string          `json:"value"`
	Domain       string          `json:"domain"`
	RecordLineID string          `json:"record_line_id"`
	Line         string          `json:"line"`
}

func (p *dnspodProvider) Key() string {
	return "Dnspod"
}

func (p *dnspodProvider) Label() string {
	return "DNSPod"
}

func (p *dnspodProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "ID", Label: "ID", Required: true, Secret: true, Description: "DNSPod Token ID"},
		{Name: "Token", Label: "Token", Required: true, Secret: true, Description: "DNSPod Token"},
	}
}

func (p *dnspodProvider) Configure(config map[string]string) error {
	p.id = strings.TrimSpace(config["ID"])
	p.token = strings.TrimSpace(config["Token"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], dnspodDefaultBaseURL, true)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	return nil
}

func (p *dnspodProvider) Check(ctx context.Context) error {
	_, err := p.doForm(ctx, "Info.Version", nil, "check")
	return err
}

func (p *dnspodProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	ret, err := p.doForm(ctx, "Domain.List", nil, "list_zones")
	if err != nil {
		return nil, err
	}
	zones := make([]dns.Zone, 0, len(ret.Domains))
	for _, domain := range ret.Domains {
		zones = append(zones, dns.Zone{ID: rawString(domain.ID), Domain: domain.Name})
	}
	return zones, nil
}

func (p *dnspodProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{
		{ID: "0", Name: "默认"},
		{ID: "7=0", Name: "国内"},
		{ID: "3=0", Name: "国外"},
		{ID: "10=0", Name: "电信"},
		{ID: "10=1", Name: "联通"},
		{ID: "10=2", Name: "教育网"},
		{ID: "10=3", Name: "移动"},
		{ID: "90=0", Name: "百度"},
		{ID: "90=1", Name: "谷歌"},
		{ID: "90=4", Name: "搜搜"},
		{ID: "90=2", Name: "有道"},
		{ID: "90=3", Name: "必应"},
		{ID: "90=5", Name: "搜狗"},
		{ID: "90=6", Name: "奇虎"},
		{ID: "80=0", Name: "搜索引擎"},
	}, nil
}

func (p *dnspodProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	params := p.zoneParams(zone)
	params.Set("sub_domain", dnspodHost(input.Name))
	params.Set("record_type", strings.ToUpper(strings.TrimSpace(input.Type)))
	params.Set("value", strings.TrimSpace(input.Value))
	params.Set("mx", "20")
	params.Set("record_line_id", dnspodLineID(input.LineID))
	ret, err := p.doForm(ctx, "Record.Create", params, "create_record")
	if err != nil {
		return dns.Record{}, err
	}
	record := dnspodRecordToDomain(ret.Record, zone.Domain)
	if record.RemoteID == "" {
		record.RemoteID = rawString(ret.Record.ID)
	}
	if record.Name == "" {
		record.Name = dnspodHost(input.Name)
	}
	if record.Type == "" {
		record.Type = strings.ToUpper(strings.TrimSpace(input.Type))
	}
	if record.Value == "" {
		record.Value = strings.TrimSpace(input.Value)
	}
	if rawString(ret.Record.ID) != "" && ret.Record.RecordLineID == "" {
		record.LineID = dnspodLineID(input.LineID)
		record.Line = ""
	}
	record.Line = dnspodLineName(record.LineID, record.Line)
	return record, nil
}

func (p *dnspodProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	params := p.zoneParams(zone)
	params.Set("record_id", strings.TrimSpace(remoteID))
	params.Set("sub_domain", dnspodHost(input.Name))
	params.Set("record_type", strings.ToUpper(strings.TrimSpace(input.Type)))
	params.Set("value", strings.TrimSpace(input.Value))
	params.Set("mx", "20")
	params.Set("record_line_id", dnspodLineID(input.LineID))
	ret, err := p.doForm(ctx, "Record.Modify", params, "update_record")
	if err != nil {
		return dns.Record{}, err
	}
	record := dnspodRecordToDomain(ret.Record, zone.Domain)
	if record.RemoteID == "" {
		record.RemoteID = remoteID
	}
	if record.Name == "" {
		record.Name = dnspodHost(input.Name)
	}
	if record.Type == "" {
		record.Type = strings.ToUpper(strings.TrimSpace(input.Type))
	}
	if record.Value == "" {
		record.Value = strings.TrimSpace(input.Value)
	}
	if rawString(ret.Record.ID) != "" && ret.Record.RecordLineID == "" {
		record.LineID = dnspodLineID(input.LineID)
		record.Line = ""
	}
	record.Line = dnspodLineName(record.LineID, record.Line)
	return record, nil
}

func (p *dnspodProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	params := p.zoneParams(zone)
	params.Set("record_id", strings.TrimSpace(remoteID))
	_, err := p.doForm(ctx, "Record.Remove", params, "delete_record")
	return err
}

func (p *dnspodProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	params := p.zoneParams(zone)
	params.Set("record_id", strings.TrimSpace(remoteID))
	ret, err := p.doForm(ctx, "Record.Info", params, "get_record")
	if err != nil {
		return dns.Record{}, err
	}
	return dnspodRecordToDomain(ret.Record, zone.Domain), nil
}

func (p *dnspodProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	params := p.zoneParams(zone)
	ret, err := p.doForm(ctx, "Record.List", params, "list_records")
	if err != nil {
		return nil, err
	}
	domain := zone.Domain
	if ret.Domain.Name != "" {
		domain = ret.Domain.Name
	}
	records := make([]dns.Record, 0, len(ret.Records))
	for _, record := range ret.Records {
		records = append(records, dnspodRecordToDomain(record, domain))
	}
	return records, nil
}

func (p *dnspodProvider) doForm(ctx context.Context, action string, params url.Values, operation string) (dnspodResponse, error) {
	if err := p.validateAuth(operation); err != nil {
		return dnspodResponse{}, err
	}
	if params == nil {
		params = url.Values{}
	}
	params.Set("login_token", p.id+","+p.token)
	params.Set("format", "json")
	params.Set("lang", "cn")
	params.Set("error_on_empty", "yes")
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.endpoint(action), strings.NewReader(params.Encode()))
	if err != nil {
		return dnspodResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := p.client.Do(req)
	if err != nil {
		return dnspodResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return dnspodResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var ret dnspodResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return dnspodResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || rawString(ret.Status.Code) != "1" {
		return dnspodResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: dnspodErrorMessage(resp.StatusCode, ret.Status.Message)}
	}
	return ret, nil
}

func (p *dnspodProvider) endpoint(action string) string {
	return strings.TrimRight(p.baseURL, "/") + "/" + strings.TrimLeft(action, "/")
}

func (p *dnspodProvider) validateAuth(operation string) error {
	if p.id != "" && p.token != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing ID or Token"}
}

func (p *dnspodProvider) zoneParams(zone dns.Zone) url.Values {
	params := url.Values{}
	if strings.TrimSpace(zone.ID) != "" {
		params.Set("domain_id", strings.TrimSpace(zone.ID))
	}
	if strings.TrimSpace(zone.Domain) != "" {
		params.Set("domain", strings.TrimSpace(zone.Domain))
	}
	return params
}

func dnspodErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("DNSPod API returned HTTP %d", status)
	}
	return "DNSPod API request failed"
}

func dnspodHost(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "@"
	}
	return name
}

func dnspodLineID(lineID string) string {
	lineID = strings.TrimSpace(lineID)
	if lineID == "" {
		return "0"
	}
	return lineID
}

func dnspodRecordToDomain(record dnspodRecord, domain string) dns.Record {
	name := record.Name
	if name == "" {
		name = record.SubDomain
	}
	recordType := record.Type
	if recordType == "" {
		recordType = record.RecordType
	}
	lineID := record.RecordLineID
	if lineID == "" {
		lineID = "0"
	}
	return dns.Record{
		RemoteID: rawString(record.ID),
		Name:     name,
		Type:     recordType,
		Value:    record.Value,
		LineID:   lineID,
		Line:     dnspodLineName(lineID, record.Line),
	}
}

func dnspodLineName(lineID string, fallback string) string {
	if strings.TrimSpace(fallback) != "" {
		return fallback
	}
	for _, line := range []dns.RecordLine{
		{ID: "0", Name: "默认"},
		{ID: "7=0", Name: "国内"},
		{ID: "3=0", Name: "国外"},
		{ID: "10=0", Name: "电信"},
		{ID: "10=1", Name: "联通"},
		{ID: "10=2", Name: "教育网"},
		{ID: "10=3", Name: "移动"},
		{ID: "90=0", Name: "百度"},
		{ID: "90=1", Name: "谷歌"},
		{ID: "90=4", Name: "搜搜"},
		{ID: "90=2", Name: "有道"},
		{ID: "90=3", Name: "必应"},
		{ID: "90=5", Name: "搜狗"},
		{ID: "90=6", Name: "奇虎"},
		{ID: "80=0", Name: "搜索引擎"},
	} {
		if line.ID == lineID {
			return line.Name
		}
	}
	return "默认"
}

func rawString(raw json.RawMessage) string {
	if len(raw) == 0 {
		return ""
	}
	var s string
	if err := json.Unmarshal(raw, &s); err == nil {
		return s
	}
	var n json.Number
	if err := json.Unmarshal(raw, &n); err == nil {
		return n.String()
	}
	var f float64
	if err := json.Unmarshal(raw, &f); err == nil {
		if f == float64(int64(f)) {
			return strconv.FormatInt(int64(f), 10)
		}
		return strconv.FormatFloat(f, 'f', -1, 64)
	}
	return strings.Trim(string(raw), `"`)
}
