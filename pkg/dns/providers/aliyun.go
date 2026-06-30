package providers

import (
	"context"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha1"
	"encoding/base64"
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

const aliyunDefaultBaseURL = "https://alidns.aliyuncs.com/"

func init() {
	dns.Register("Aliyun", func() dns.Provider {
		return &aliyunProvider{
			baseURL: aliyunDefaultBaseURL,
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now().UTC() },
			nonce:   aliyunNonce,
		}
	})
}

type aliyunProvider struct {
	accessKeyID     string
	accessKeySecret string
	baseURL         string
	client          *http.Client
	now             func() time.Time
	nonce           func() string
}

type aliyunResponse struct {
	Code          string              `json:"Code"`
	Message       string              `json:"Message"`
	RecordID      string              `json:"RecordId"`
	DomainName    string              `json:"DomainName"`
	RR            string              `json:"RR"`
	Type          string              `json:"Type"`
	Value         string              `json:"Value"`
	Line          string              `json:"Line"`
	Domains       aliyunDomains       `json:"Domains"`
	DomainRecords aliyunDomainRecords `json:"DomainRecords"`
}

type aliyunDomains struct {
	Domain []aliyunDomain `json:"Domain"`
}

type aliyunDomain struct {
	DomainID   string `json:"DomainId"`
	DomainName string `json:"DomainName"`
}

type aliyunDomainRecords struct {
	Record []aliyunRecord `json:"Record"`
}

type aliyunRecord struct {
	RecordID   string `json:"RecordId"`
	DomainName string `json:"DomainName"`
	RR         string `json:"RR"`
	Type       string `json:"Type"`
	Value      string `json:"Value"`
	Line       string `json:"Line"`
}

func (p *aliyunProvider) Key() string {
	return "Aliyun"
}

func (p *aliyunProvider) Label() string {
	return "阿里云 DNS"
}

func (p *aliyunProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "AccessKeyId", Label: "AccessKeyId", Required: true, Secret: true},
		{Name: "AccessKeySecret", Label: "AccessKeySecret", Required: true, Secret: true},
	}
}

func (p *aliyunProvider) Configure(config map[string]string) error {
	p.accessKeyID = strings.TrimSpace(config["AccessKeyId"])
	p.accessKeySecret = strings.TrimSpace(config["AccessKeySecret"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], aliyunDefaultBaseURL, true)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now().UTC() }
	}
	if p.nonce == nil {
		p.nonce = aliyunNonce
	}
	return nil
}

func (p *aliyunProvider) Check(ctx context.Context) error {
	_, err := p.doRPC(ctx, "DescribeDomains", nil, "check")
	return err
}

func (p *aliyunProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	ret, err := p.doRPC(ctx, "DescribeDomains", nil, "list_zones")
	if err != nil {
		return nil, err
	}
	zones := make([]dns.Zone, 0, len(ret.Domains.Domain))
	for _, domain := range ret.Domains.Domain {
		zones = append(zones, dns.Zone{ID: domain.DomainID, Domain: domain.DomainName})
	}
	return zones, nil
}

func (p *aliyunProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{
		{ID: "default", Name: "默认"},
		{ID: "telecom", Name: "电信"},
		{ID: "unicom", Name: "联通"},
		{ID: "mobile", Name: "移动"},
		{ID: "oversea", Name: "海外"},
		{ID: "edu", Name: "教育网"},
		{ID: "search", Name: "搜索引擎"},
		{ID: "google", Name: "谷歌"},
		{ID: "baidu", Name: "百度"},
		{ID: "biying", Name: "必应"},
		{ID: "youdao", Name: "有道"},
		{ID: "yahoo", Name: "雅虎"},
	}, nil
}

func (p *aliyunProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"DomainName": zone.Domain,
		"RR":         aliyunRR(input.Name),
		"Type":       strings.ToUpper(strings.TrimSpace(input.Type)),
		"Value":      strings.TrimSpace(input.Value),
		"Line":       aliyunLineID(input.LineID),
	}
	aliyunApplyRecordTypeParams(params)
	ret, err := p.doRPC(ctx, "AddDomainRecord", params, "create_record")
	if err != nil {
		return dns.Record{}, err
	}
	if ret.RecordID == "" {
		return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "create_record", Message: "missing RecordId in response"}
	}
	return dns.Record{RemoteID: ret.RecordID, Name: params["RR"], Type: params["Type"], Value: params["Value"], LineID: params["Line"], Line: aliyunLineName(params["Line"])}, nil
}

func (p *aliyunProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	params := map[string]string{
		"RecordId": strings.TrimSpace(remoteID),
		"RR":       aliyunRR(input.Name),
		"Type":     strings.ToUpper(strings.TrimSpace(input.Type)),
		"Value":    strings.TrimSpace(input.Value),
		"Line":     aliyunLineID(input.LineID),
	}
	aliyunApplyRecordTypeParams(params)
	if _, err := p.doRPC(ctx, "UpdateDomainRecord", params, "update_record"); err != nil {
		return dns.Record{}, err
	}
	return dns.Record{RemoteID: remoteID, Name: params["RR"], Type: params["Type"], Value: params["Value"], LineID: params["Line"], Line: aliyunLineName(params["Line"])}, nil
}

func (p *aliyunProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	_, err := p.doRPC(ctx, "DeleteDomainRecord", map[string]string{"RecordId": strings.TrimSpace(remoteID)}, "delete_record")
	return err
}

func (p *aliyunProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	ret, err := p.doRPC(ctx, "DescribeDomainRecordInfo", map[string]string{"RecordId": strings.TrimSpace(remoteID)}, "get_record")
	if err != nil {
		return dns.Record{}, err
	}
	return dns.Record{RemoteID: ret.RecordID, Name: ret.RR, Type: ret.Type, Value: ret.Value, LineID: aliyunLineID(ret.Line), Line: aliyunLineName(ret.Line)}, nil
}

func (p *aliyunProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	ret, err := p.doRPC(ctx, "DescribeDomainRecords", map[string]string{"DomainName": zone.Domain}, "list_records")
	if err != nil {
		return nil, err
	}
	records := make([]dns.Record, 0, len(ret.DomainRecords.Record))
	for _, record := range ret.DomainRecords.Record {
		lineID := aliyunLineID(record.Line)
		records = append(records, dns.Record{
			RemoteID: record.RecordID,
			Name:     record.RR,
			Type:     record.Type,
			Value:    record.Value,
			LineID:   lineID,
			Line:     aliyunLineName(lineID),
		})
	}
	return records, nil
}

func (p *aliyunProvider) doRPC(ctx context.Context, action string, actionParams map[string]string, operation string) (aliyunResponse, error) {
	if err := p.validateAuth(operation); err != nil {
		return aliyunResponse{}, err
	}
	params := map[string]string{
		"Format":           "JSON",
		"Version":          "2015-01-09",
		"AccessKeyId":      p.accessKeyID,
		"SignatureMethod":  "HMAC-SHA1",
		"SignatureVersion": "1.0",
		"Action":           action,
		"Timestamp":        p.now().UTC().Format("2006-01-02T15:04:05Z"),
		"SignatureNonce":   p.nonce(),
	}
	for key, value := range actionParams {
		params[key] = value
	}
	params["Signature"] = aliyunSignature("POST", params, p.accessKeySecret)
	form := url.Values{}
	for key, value := range params {
		form.Set(key, value)
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.baseURL, strings.NewReader(form.Encode()))
	if err != nil {
		return aliyunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := p.client.Do(req)
	if err != nil {
		return aliyunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return aliyunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var ret aliyunResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return aliyunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || ret.Code != "" {
		return aliyunResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: aliyunErrorMessage(resp.StatusCode, ret.Message)}
	}
	return ret, nil
}

func (p *aliyunProvider) validateAuth(operation string) error {
	if p.accessKeyID != "" && p.accessKeySecret != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing AccessKeyId or AccessKeySecret"}
}

func aliyunSignature(method string, params map[string]string, secret string) string {
	keys := make([]string, 0, len(params))
	for key := range params {
		if key != "Signature" {
			keys = append(keys, key)
		}
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, percentEncodeAliyun(key)+"="+percentEncodeAliyun(params[key]))
	}
	canonical := strings.Join(parts, "&")
	stringToSign := method + "&%2F&" + percentEncodeAliyun(canonical)
	mac := hmac.New(sha1.New, []byte(secret+"&"))
	_, _ = mac.Write([]byte(stringToSign))
	return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}

func percentEncodeAliyun(value string) string {
	encoded := url.QueryEscape(value)
	encoded = strings.ReplaceAll(encoded, "+", "%20")
	encoded = strings.ReplaceAll(encoded, "*", "%2A")
	encoded = strings.ReplaceAll(encoded, "%7E", "~")
	return encoded
}

func aliyunNonce() string {
	raw := make([]byte, 16)
	if _, err := rand.Read(raw); err != nil {
		return fmt.Sprintf("%d", time.Now().UnixNano())
	}
	return hex.EncodeToString(raw)
}

func aliyunErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("Aliyun API returned HTTP %d", status)
	}
	return "Aliyun API request failed"
}

func aliyunRR(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return "@"
	}
	return name
}

func aliyunApplyRecordTypeParams(params map[string]string) {
	if strings.ToUpper(strings.TrimSpace(params["Type"])) == "MX" {
		params["Priority"] = "10"
	}
}

func aliyunLineID(line string) string {
	line = strings.TrimSpace(line)
	if line == "" || line == "0" || line == "默认" {
		return "default"
	}
	return line
}

func aliyunLineName(line string) string {
	switch aliyunLineID(line) {
	case "default":
		return "默认"
	case "telecom":
		return "电信"
	case "unicom":
		return "联通"
	case "mobile":
		return "移动"
	case "oversea":
		return "海外"
	case "edu":
		return "教育网"
	case "search":
		return "搜索引擎"
	case "google":
		return "谷歌"
	case "baidu":
		return "百度"
	case "biying":
		return "必应"
	case "youdao":
		return "有道"
	case "yahoo":
		return "雅虎"
	default:
		return "默认"
	}
}
