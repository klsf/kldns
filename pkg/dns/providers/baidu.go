package providers

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/md5"
	"crypto/rand"
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

const baiduDefaultBaseURL = "https://dns.baidubce.com"

func init() {
	dns.Register("BaiduCloud", func() dns.Provider {
		return &baiduProvider{
			baseURL: baiduDefaultBaseURL,
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now().UTC() },
			token:   baiduClientToken,
		}
	})
}

type baiduProvider struct {
	accessKeyID     string
	secretAccessKey string
	baseURL         string
	client          *http.Client
	now             func() time.Time
	token           func() string
}

type baiduZoneList struct {
	Zones       []baiduZone `json:"zones"`
	IsTruncated bool        `json:"isTruncated"`
	NextMarker  string      `json:"nextMarker"`
	Message     string      `json:"message"`
	MessageZh   string      `json:"messageZh"`
}

type baiduZone struct {
	ID   string `json:"id"`
	Name string `json:"name"`
}

type baiduRecordList struct {
	Records     []baiduRecord `json:"records"`
	IsTruncated bool          `json:"isTruncated"`
	NextMarker  string        `json:"nextMarker"`
	Message     string        `json:"message"`
	MessageZh   string        `json:"messageZh"`
}

type baiduRecord struct {
	ID    string `json:"id"`
	RR    string `json:"rr"`
	Type  string `json:"type"`
	Value string `json:"value"`
	Line  string `json:"line"`
}

type baiduLineList struct {
	Lines     []baiduLine `json:"lineList"`
	Message   string      `json:"message"`
	MessageZh string      `json:"messageZh"`
}

type baiduLine struct {
	Name string `json:"name"`
}

type baiduErrorResponse struct {
	Message   string `json:"message"`
	MessageZh string `json:"messageZh"`
}

func (p *baiduProvider) Key() string {
	return "BaiduCloud"
}

func (p *baiduProvider) Label() string {
	return "百度智能云 DNS"
}

func (p *baiduProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "AccessKeyId", Label: "AccessKeyId", Required: true, Secret: true},
		{Name: "SecretAccessKey", Label: "SecretAccessKey", Required: true, Secret: true},
	}
}

func (p *baiduProvider) Configure(config map[string]string) error {
	p.accessKeyID = strings.TrimSpace(config["AccessKeyId"])
	p.secretAccessKey = strings.TrimSpace(config["SecretAccessKey"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], baiduDefaultBaseURL, false)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now().UTC() }
	}
	if p.token == nil {
		p.token = baiduClientToken
	}
	return nil
}

func (p *baiduProvider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *baiduProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	marker := ""
	for {
		query := map[string]string{"maxKeys": "1000"}
		if marker != "" {
			query["marker"] = marker
		}
		var payload baiduZoneList
		if err := p.doJSON(ctx, http.MethodGet, "/v1/dns/zone", query, nil, &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, zone := range payload.Zones {
			if strings.TrimSpace(zone.Name) == "" {
				continue
			}
			id := strings.TrimSpace(zone.ID)
			if id == "" {
				id = strings.TrimSpace(zone.Name)
			}
			zones = append(zones, dns.Zone{ID: id, Domain: strings.TrimSpace(zone.Name)})
		}
		if !payload.IsTruncated || strings.TrimSpace(payload.NextMarker) == "" {
			break
		}
		marker = payload.NextMarker
	}
	return zones, nil
}

func (p *baiduProvider) ListRecordLines(ctx context.Context, zone dns.Zone) ([]dns.RecordLine, error) {
	lines := baiduDefaultLines()
	var payload baiduLineList
	if err := p.doJSON(ctx, http.MethodGet, "/v1/dns/customline", map[string]string{"maxKeys": "1000"}, nil, &payload, "list_record_lines"); err != nil {
		return lines, nil
	}
	for _, line := range payload.Lines {
		name := strings.TrimSpace(line.Name)
		if name == "" {
			continue
		}
		lines = append(lines, dns.RecordLine{ID: name, Name: name})
	}
	return lines, nil
}

func (p *baiduProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	body := baiduRecordBody(input)
	if err := p.doJSON(ctx, http.MethodPost, "/v1/dns/zone/"+url.PathEscape(zone.Domain)+"/record", map[string]string{"clientToken": p.token()}, body, nil, "create_record"); err != nil {
		return dns.Record{}, err
	}
	record, err := p.findLatestRecord(ctx, zone, input)
	if err != nil {
		return dns.Record{}, err
	}
	return record, nil
}

func (p *baiduProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	body := baiduRecordBody(input)
	if err := p.doJSON(ctx, http.MethodPut, "/v1/dns/zone/"+url.PathEscape(zone.Domain)+"/record/"+url.PathEscape(strings.TrimSpace(remoteID)), nil, body, nil, "update_record"); err != nil {
		return dns.Record{}, err
	}
	record := baiduRecordToDomain(baiduRecord{ID: remoteID, RR: body["rr"].(string), Type: body["type"].(string), Value: body["value"].(string), Line: baiduLineID(input.LineID)})
	return record, nil
}

func (p *baiduProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	return p.doJSON(ctx, http.MethodDelete, "/v1/dns/zone/"+url.PathEscape(zone.Domain)+"/record/"+url.PathEscape(strings.TrimSpace(remoteID)), nil, nil, nil, "delete_record")
}

func (p *baiduProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	var payload baiduRecordList
	if err := p.doJSON(ctx, http.MethodGet, "/v1/dns/zone/"+url.PathEscape(zone.Domain)+"/record", map[string]string{"id": strings.TrimSpace(remoteID)}, nil, &payload, "get_record"); err != nil {
		return dns.Record{}, err
	}
	for _, record := range payload.Records {
		if record.ID == strings.TrimSpace(remoteID) {
			return baiduRecordToDomain(record), nil
		}
	}
	return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "get_record", Message: "record not found"}
}

func (p *baiduProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	var records []dns.Record
	marker := ""
	for {
		query := map[string]string{"maxKeys": "1000"}
		if marker != "" {
			query["marker"] = marker
		}
		var payload baiduRecordList
		if err := p.doJSON(ctx, http.MethodGet, "/v1/dns/zone/"+url.PathEscape(zone.Domain)+"/record", query, nil, &payload, "list_records"); err != nil {
			return nil, err
		}
		for _, record := range payload.Records {
			records = append(records, baiduRecordToDomain(record))
		}
		if !payload.IsTruncated || strings.TrimSpace(payload.NextMarker) == "" {
			break
		}
		marker = payload.NextMarker
	}
	return records, nil
}

func (p *baiduProvider) doJSON(ctx context.Context, method string, path string, query map[string]string, body any, out any, operation string) error {
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
	u.RawQuery = baiduCanonicalQuery(query)
	req, err := http.NewRequestWithContext(ctx, method, u.String(), bytes.NewBufferString(payload))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	timestamp := p.now().UTC().Format("2006-01-02T15:04:05Z")
	headers := baiduSignedHeaders(u.Host, timestamp, payload)
	auth := baiduAuthorization(method, path, query, payload, u.Host, timestamp, p.accessKeyID, p.secretAccessKey)
	for key, value := range headers {
		req.Header.Set(http.CanonicalHeaderKey(key), value)
	}
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
		var errorBody baiduErrorResponse
		_ = json.Unmarshal(data, &errorBody)
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: baiduErrorMessage(resp.StatusCode, errorBody)}
	}
	if out != nil && len(data) > 0 {
		if err := json.Unmarshal(data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed", Cause: err}
		}
	}
	return nil
}

func (p *baiduProvider) validateAuth(operation string) error {
	if p.accessKeyID != "" && p.secretAccessKey != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing AccessKeyId or SecretAccessKey"}
}

func (p *baiduProvider) findLatestRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	records, err := p.ListRecords(ctx, zone)
	if err != nil {
		return dns.Record{}, err
	}
	targetName := baiduHost(input.Name)
	if targetName == "@" {
		targetName = ""
	}
	targetType := strings.ToUpper(strings.TrimSpace(input.Type))
	targetValue := strings.TrimSpace(input.Value)
	for i := len(records) - 1; i >= 0; i-- {
		record := records[i]
		if record.Name == targetName && strings.ToUpper(record.Type) == targetType && strings.TrimSpace(record.Value) == targetValue {
			return record, nil
		}
	}
	return dns.Record{}, &dns.ProviderError{Provider: p.Key(), Operation: "create_record", Message: "record created but lookup failed"}
}

func baiduAuthorization(method string, path string, query map[string]string, payload string, host string, timestamp string, accessKey string, secretKey string) string {
	headers := baiduSignedHeaders(host, timestamp, payload)
	signedHeaders := baiduSignedHeaderNames(payload)
	canonicalRequest := strings.ToUpper(method) + "\n" +
		baiduNormalizePath(path) + "\n" +
		baiduCanonicalQuery(query) + "\n" +
		baiduCanonicalHeaders(headers)
	authPrefix := "bce-auth-v1/" + accessKey + "/" + timestamp + "/1800"
	signingKey := hmacSHA256Hex(authPrefix, secretKey)
	signature := hmacSHA256Hex(canonicalRequest, signingKey)
	return authPrefix + "/" + signedHeaders + "/" + signature
}

func baiduSignedHeaders(host string, timestamp string, payload string) map[string]string {
	headers := map[string]string{
		"host":       host,
		"x-bce-date": timestamp,
	}
	if payload != "" {
		headers["content-type"] = "application/json; charset=utf-8"
	}
	return headers
}

func baiduSignedHeaderNames(payload string) string {
	if payload == "" {
		return "host;x-bce-date"
	}
	return "host;x-bce-date;content-type"
}

func baiduCanonicalHeaders(headers map[string]string) string {
	keys := make([]string, 0, len(headers))
	for key := range headers {
		keys = append(keys, strings.ToLower(key))
	}
	sort.Strings(keys)
	parts := make([]string, 0, len(keys))
	for _, key := range keys {
		parts = append(parts, key+":"+strings.TrimSpace(headers[key]))
	}
	return strings.Join(parts, "\n")
}

func baiduCanonicalQuery(query map[string]string) string {
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

func baiduNormalizePath(path string) string {
	segments := strings.Split(strings.TrimLeft(path, "/"), "/")
	for i, segment := range segments {
		decoded, err := url.PathUnescape(segment)
		if err == nil {
			segment = decoded
		}
		segments[i] = url.PathEscape(segment)
	}
	return "/" + strings.Join(segments, "/")
}

func baiduRecordBody(input dns.RecordInput) map[string]any {
	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	body := map[string]any{
		"rr":    baiduHost(input.Name),
		"type":  recordType,
		"value": strings.TrimSpace(input.Value),
		"ttl":   300,
	}
	line := baiduLineID(input.LineID)
	if line != "default" {
		body["line"] = line
	}
	if recordType == "MX" {
		body["priority"] = 10
	}
	return body
}

func baiduRecordToDomain(record baiduRecord) dns.Record {
	lineID := baiduLineID(record.Line)
	return dns.Record{
		RemoteID: strings.TrimSpace(record.ID),
		Name:     baiduDenormalizeHost(record.RR),
		Type:     strings.ToUpper(strings.TrimSpace(record.Type)),
		Value:    strings.TrimSpace(record.Value),
		LineID:   lineID,
		Line:     baiduLineName(lineID),
	}
}

func baiduDefaultLines() []dns.RecordLine {
	return []dns.RecordLine{
		{ID: "default", Name: "默认"},
		{ID: "ct", Name: "电信"},
		{ID: "cmnet", Name: "移动"},
		{ID: "cnc", Name: "联通"},
		{ID: "edu", Name: "教育网"},
		{ID: "search", Name: "搜索引擎"},
	}
}

func baiduLineID(line string) string {
	line = strings.TrimSpace(line)
	if line == "" || line == "0" {
		return "default"
	}
	return line
}

func baiduLineName(line string) string {
	line = baiduLineID(line)
	for _, item := range baiduDefaultLines() {
		if item.ID == line {
			return item.Name
		}
	}
	return line
}

func baiduHost(name string) string {
	name = strings.TrimSpace(name)
	if name == "" || name == "@" {
		return "@"
	}
	return name
}

func baiduDenormalizeHost(name string) string {
	name = strings.TrimSpace(name)
	if name == "@" {
		return ""
	}
	return name
}

func baiduClientToken() string {
	raw := make([]byte, 16)
	if _, err := rand.Read(raw); err != nil {
		sum := md5.Sum([]byte(fmt.Sprintf("%d", time.Now().UnixNano())))
		return hex.EncodeToString(sum[:])
	}
	sum := md5.Sum(raw)
	return hex.EncodeToString(sum[:])
}

func baiduErrorMessage(status int, body baiduErrorResponse) string {
	if strings.TrimSpace(body.Message) != "" {
		return body.Message
	}
	if strings.TrimSpace(body.MessageZh) != "" {
		return body.MessageZh
	}
	return fmt.Sprintf("BaiduCloud API returned HTTP %d", status)
}

func hmacSHA256Hex(value string, key string) string {
	mac := hmac.New(sha256.New, []byte(key))
	_, _ = mac.Write([]byte(value))
	return hex.EncodeToString(mac.Sum(nil))
}
