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
	"strconv"
	"strings"
	"time"

	"kldns/pkg/dns"
	"kldns/pkg/dns/providerhttp"
)

const westDefaultBaseURL = "https://api.west.cn/api/v2/domain/"

func init() {
	dns.Register("West", func() dns.Provider {
		return &westProvider{
			baseURL: westDefaultBaseURL,
			client:  providerhttp.NewClient(),
			nowMS:   func() int64 { return time.Now().UnixMilli() },
		}
	})
}

type westProvider struct {
	username string
	password string
	baseURL  string
	client   *http.Client
	nowMS    func() int64
}

type westResponse struct {
	Result int      `json:"result"`
	Msg    string   `json:"msg"`
	Data   westData `json:"data"`
}

type westData struct {
	ID    json.RawMessage `json:"id"`
	Items []westItem      `json:"items"`
}

type westItem struct {
	ID     json.RawMessage `json:"id"`
	Domain string          `json:"domain"`
	Item   string          `json:"item"`
	Type   string          `json:"type"`
	Value  string          `json:"value"`
	Line   string          `json:"line"`
}

func (p *westProvider) Key() string {
	return "West"
}

func (p *westProvider) Label() string {
	return "西部数码"
}

func (p *westProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "Username", Label: "Username", Required: true},
		{Name: "ApiPassword", Label: "ApiPassword", Required: true, Secret: true},
	}
}

func (p *westProvider) Configure(config map[string]string) error {
	p.username = strings.TrimSpace(config["Username"])
	p.password = strings.TrimSpace(config["ApiPassword"])
	if baseURL := strings.TrimSpace(config["BaseURL"]); baseURL != "" {
		p.baseURL = baseURL
	}
	if p.baseURL == "" {
		p.baseURL = westDefaultBaseURL
	}
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.nowMS == nil {
		p.nowMS = func() int64 { return time.Now().UnixMilli() }
	}
	return nil
}

func (p *westProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *westProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	ret, err := p.doRequest(ctx, http.MethodGet, "getdomains", map[string]string{"limit": "500", "page": "1"}, "list_zones")
	if err != nil {
		return nil, err
	}
	zones := make([]dns.Zone, 0, len(ret.Data.Items))
	for _, item := range ret.Data.Items {
		if strings.TrimSpace(item.Domain) != "" {
			zones = append(zones, dns.Zone{ID: item.Domain, Domain: item.Domain})
		}
	}
	return zones, nil
}

func (p *westProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{
		{ID: "", Name: "默认"},
		{ID: "LTEL", Name: "电信"},
		{ID: "LCNC", Name: "联通"},
		{ID: "LMOB", Name: "移动"},
		{ID: "LEDU", Name: "教育网"},
		{ID: "LSEO", Name: "搜索引擎"},
	}, nil
}

func (p *westProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	params := westRecordParams(zone, input)
	ret, err := p.doRequest(ctx, http.MethodPost, "adddnsrecord", params, "create_record")
	if err != nil {
		return dns.Record{}, err
	}
	result := dns.Record{
		RemoteID: rawString(ret.Data.ID),
		Name:     denormalizeWestHost(params["host"]),
		Type:     params["type"],
		Value:    params["value"],
		LineID:   params["line"],
		Line:     westLineName(params["line"]),
	}
	if result.RemoteID == "" {
		return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "create_record", Message: "missing record id in response"}
	}
	return result, nil
}

func (p *westProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	params := westRecordParams(zone, input)
	params["id"] = strings.TrimSpace(remoteID)
	if _, err := p.doRequest(ctx, http.MethodPost, "moddnsrecord", params, "update_record"); err != nil {
		return dns.Record{}, err
	}
	return dns.Record{
		RemoteID: remoteID,
		Name:     denormalizeWestHost(params["host"]),
		Type:     params["type"],
		Value:    params["value"],
		LineID:   params["line"],
		Line:     westLineName(params["line"]),
	}, nil
}

func (p *westProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	_, err := p.doRequest(ctx, http.MethodPost, "deldnsrecord", map[string]string{
		"domain": zone.Domain,
		"id":     strings.TrimSpace(remoteID),
	}, "delete_record")
	return err
}

func (p *westProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
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

func (p *westProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	ret, err := p.doRequest(ctx, http.MethodPost, "getdnsrecord", map[string]string{
		"domain": zone.Domain,
		"limit":  "500",
		"pageno": "1",
	}, "list_records")
	if err != nil {
		return nil, err
	}
	records := make([]dns.Record, 0, len(ret.Data.Items))
	for _, item := range ret.Data.Items {
		line := normalizeWestLine(item.Line)
		records = append(records, dns.Record{
			RemoteID: rawString(item.ID),
			Name:     denormalizeWestHost(item.Item),
			Type:     strings.ToUpper(strings.TrimSpace(item.Type)),
			Value:    item.Value,
			LineID:   line,
			Line:     westLineName(line),
		})
	}
	return records, nil
}

func (p *westProvider) doRequest(ctx context.Context, method string, action string, params map[string]string, operation string) (westResponse, error) {
	if err := p.validateAuth(operation); err != nil {
		return westResponse{}, err
	}
	values := url.Values{}
	timestamp := strconv.FormatInt(p.nowMS(), 10)
	values.Set("username", p.username)
	values.Set("time", timestamp)
	values.Set("token", westToken(p.username, p.password, timestamp))
	values.Set("act", action)
	for key, value := range params {
		values.Set(key, value)
	}
	endpoint := p.baseURL
	var body io.Reader
	if method == http.MethodGet {
		sep := "?"
		if strings.Contains(endpoint, "?") {
			sep = "&"
		}
		endpoint += sep + values.Encode()
	} else {
		body = strings.NewReader(values.Encode())
	}
	req, err := http.NewRequestWithContext(ctx, method, endpoint, body)
	if err != nil {
		return westResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	if method != http.MethodGet {
		req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	}
	resp, err := p.client.Do(req)
	if err != nil {
		return westResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return westResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var ret westResponse
	if err := json.Unmarshal(data, &ret); err != nil {
		return westResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || ret.Result != 200 {
		return westResponse{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: westErrorMessage(resp.StatusCode, ret.Msg)}
	}
	return ret, nil
}

func (p *westProvider) validateAuth(operation string) error {
	if p.username != "" && p.password != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing Username or ApiPassword"}
}

func westRecordParams(zone dns.Zone, input dns.RecordInput) map[string]string {
	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	return map[string]string{
		"domain": zone.Domain,
		"host":   normalizeWestHost(input.Name),
		"type":   recordType,
		"value":  strings.TrimSpace(input.Value),
		"line":   normalizeWestLine(input.LineID),
		"ttl":    "900",
		"level":  westLevel(recordType),
	}
}

func westToken(username string, password string, timestamp string) string {
	sum := md5.Sum([]byte(username + password + timestamp))
	return hex.EncodeToString(sum[:])
}

func normalizeWestHost(host string) string {
	host = strings.TrimSpace(host)
	if host == "" {
		return "@"
	}
	return host
}

func denormalizeWestHost(host string) string {
	host = strings.TrimSpace(host)
	if host == "@" {
		return ""
	}
	return host
}

func normalizeWestLine(line string) string {
	line = strings.TrimSpace(line)
	if line == "0" {
		return ""
	}
	return line
}

func westLineName(line string) string {
	switch normalizeWestLine(line) {
	case "":
		return "默认"
	case "LTEL":
		return "电信"
	case "LCNC":
		return "联通"
	case "LMOB":
		return "移动"
	case "LEDU":
		return "教育网"
	case "LSEO":
		return "搜索引擎"
	default:
		return line
	}
}

func westLevel(recordType string) string {
	if strings.ToUpper(strings.TrimSpace(recordType)) == "MX" {
		return "10"
	}
	return "10"
}

func westErrorMessage(status int, message string) string {
	message = strings.TrimSpace(message)
	if message != "" {
		return message
	}
	if status > 0 {
		return fmt.Sprintf("West API returned HTTP %d", status)
	}
	return "West API request failed"
}
