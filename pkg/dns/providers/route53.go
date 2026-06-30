package providers

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha1"
	"crypto/sha256"
	"encoding/hex"
	"encoding/xml"
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

const route53DefaultBaseURL = "https://route53.amazonaws.com"

func init() {
	dns.Register("Route53", func() dns.Provider {
		return &route53Provider{
			baseURL: route53DefaultBaseURL,
			client:  providerhttp.NewClient(),
			now:     func() time.Time { return time.Now().UTC() },
		}
	})
}

type route53Provider struct {
	accessKeyID     string
	secretAccessKey string
	sessionToken    string
	baseURL         string
	client          *http.Client
	now             func() time.Time
}

type route53HostedZonesResponse struct {
	HostedZones []route53HostedZone `xml:"HostedZones>HostedZone"`
	IsTruncated string              `xml:"IsTruncated"`
	NextMarker  string              `xml:"NextMarker"`
}

type route53HostedZone struct {
	ID          string        `xml:"Id"`
	Name        string        `xml:"Name"`
	PrivateZone route53BoolEl `xml:"Config>PrivateZone"`
}

type route53BoolEl struct {
	Value string `xml:",chardata"`
}

type route53RecordSetsResponse struct {
	RecordSets           []route53RecordSet `xml:"ResourceRecordSets>ResourceRecordSet"`
	IsTruncated          string             `xml:"IsTruncated"`
	NextRecordName       string             `xml:"NextRecordName"`
	NextRecordType       string             `xml:"NextRecordType"`
	NextRecordIdentifier string             `xml:"NextRecordIdentifier"`
}

type route53RecordSet struct {
	Name            string                  `xml:"Name"`
	Type            string                  `xml:"Type"`
	TTL             int                     `xml:"TTL"`
	SetIdentifier   string                  `xml:"SetIdentifier"`
	ResourceRecords []route53ResourceRecord `xml:"ResourceRecords>ResourceRecord"`
	AliasTarget     route53AliasTarget      `xml:"AliasTarget"`
}

type route53ResourceRecord struct {
	Value string `xml:"Value"`
}

type route53AliasTarget struct {
	DNSName string `xml:"DNSName"`
}

type route53ErrorResponse struct {
	Message string `xml:"Error>Message"`
	Flat    string `xml:"Message"`
}

func (p *route53Provider) Key() string {
	return "Route53"
}

func (p *route53Provider) Label() string {
	return "Amazon Route 53"
}

func (p *route53Provider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "AccessKeyId", Label: "AccessKeyId", Required: true, Secret: true},
		{Name: "SecretAccessKey", Label: "SecretAccessKey", Required: true, Secret: true},
		{Name: "SessionToken", Label: "SessionToken", Secret: true, Description: "Optional AWS temporary credential token"},
	}
}

func (p *route53Provider) Configure(config map[string]string) error {
	p.accessKeyID = strings.TrimSpace(config["AccessKeyId"])
	p.secretAccessKey = strings.TrimSpace(config["SecretAccessKey"])
	p.sessionToken = strings.TrimSpace(config["SessionToken"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], route53DefaultBaseURL, false)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	if p.now == nil {
		p.now = func() time.Time { return time.Now().UTC() }
	}
	return nil
}

func (p *route53Provider) Check(ctx context.Context) error {
	_, err := p.ListZones(ctx)
	return err
}

func (p *route53Provider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	marker := ""
	for {
		query := map[string]string{"maxitems": "100"}
		if marker != "" {
			query["marker"] = marker
		}
		var payload route53HostedZonesResponse
		if err := p.doXML(ctx, http.MethodGet, "/2013-04-01/hostedzone", query, "", &payload, "list_zones"); err != nil {
			return nil, err
		}
		for _, zone := range payload.HostedZones {
			if strings.EqualFold(strings.TrimSpace(zone.PrivateZone.Value), "true") {
				continue
			}
			id := strings.TrimPrefix(strings.TrimSpace(zone.ID), "/hostedzone/")
			domain := strings.TrimSuffix(strings.TrimSpace(zone.Name), ".")
			if id == "" || domain == "" {
				continue
			}
			zones = append(zones, dns.Zone{ID: id, Domain: domain})
		}
		if !route53XMLBool(payload.IsTruncated) || strings.TrimSpace(payload.NextMarker) == "" {
			break
		}
		marker = strings.TrimSpace(payload.NextMarker)
	}
	return zones, nil
}

func (p *route53Provider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{{ID: "default", Name: "默认"}}, nil
}

func (p *route53Provider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	record := route53RecordFromInput(zone, input)
	if err := p.changeRecordSet(ctx, zone.ID, "CREATE", record, "create_record"); err != nil {
		return dns.Record{}, err
	}
	return route53RecordSetToDomain(record, zone.Domain), nil
}

func (p *route53Provider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	record := route53RecordFromInput(zone, input)
	current, err := p.findRecordSet(ctx, zone, remoteID)
	if err != nil {
		return dns.Record{}, err
	}
	changes := []route53RecordSetChange{{Action: "UPSERT", Record: record}}
	if !route53SameRecordSetKey(current, record) {
		changes = []route53RecordSetChange{
			{Action: "DELETE", Record: current},
			{Action: "UPSERT", Record: record},
		}
	}
	if err := p.changeRecordSets(ctx, zone.ID, changes, "update_record"); err != nil {
		return dns.Record{}, err
	}
	return route53RecordSetToDomain(record, zone.Domain), nil
}

func (p *route53Provider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	record, err := p.findRecordSet(ctx, zone, remoteID)
	if err != nil {
		return err
	}
	return p.changeRecordSet(ctx, zone.ID, "DELETE", record, "delete_record")
}

func (p *route53Provider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	record, err := p.findRecordSet(ctx, zone, remoteID)
	if err != nil {
		return dns.Record{}, err
	}
	return route53RecordSetToDomain(record, zone.Domain), nil
}

func (p *route53Provider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	sets, err := p.listRecordSets(ctx, zone)
	if err != nil {
		return nil, err
	}
	records := make([]dns.Record, 0, len(sets))
	for _, set := range sets {
		records = append(records, route53RecordSetToDomain(set, zone.Domain))
	}
	return records, nil
}

func (p *route53Provider) listRecordSets(ctx context.Context, zone dns.Zone) ([]route53RecordSet, error) {
	var records []route53RecordSet
	name := ""
	recordType := ""
	identifier := ""
	for {
		query := map[string]string{"maxitems": "100"}
		if name != "" {
			query["name"] = name
		}
		if recordType != "" {
			query["type"] = recordType
		}
		if identifier != "" {
			query["identifier"] = identifier
		}
		var payload route53RecordSetsResponse
		if err := p.doXML(ctx, http.MethodGet, "/2013-04-01/hostedzone/"+url.PathEscape(zone.ID)+"/rrset", query, "", &payload, "list_records"); err != nil {
			return nil, err
		}
		records = append(records, payload.RecordSets...)
		if !route53XMLBool(payload.IsTruncated) || strings.TrimSpace(payload.NextRecordName) == "" {
			break
		}
		name = strings.TrimSpace(payload.NextRecordName)
		recordType = strings.TrimSpace(payload.NextRecordType)
		identifier = strings.TrimSpace(payload.NextRecordIdentifier)
	}
	return records, nil
}

func (p *route53Provider) findRecordSet(ctx context.Context, zone dns.Zone, remoteID string) (route53RecordSet, error) {
	records, err := p.listRecordSets(ctx, zone)
	if err != nil {
		return route53RecordSet{}, err
	}
	for _, record := range records {
		if route53RecordID(route53ExtractHost(record.Name, zone.Domain), record.Type, record.SetIdentifier) == strings.TrimSpace(remoteID) {
			return record, nil
		}
	}
	return route53RecordSet{}, &dns.ProviderError{Provider: p.Key(), Operation: "get_record", Message: "record not found"}
}

func (p *route53Provider) changeRecordSet(ctx context.Context, zoneID string, action string, record route53RecordSet, operation string) error {
	return p.changeRecordSets(ctx, zoneID, []route53RecordSetChange{{Action: action, Record: record}}, operation)
}

func (p *route53Provider) changeRecordSets(ctx context.Context, zoneID string, changes []route53RecordSetChange, operation string) error {
	return p.doXML(ctx, http.MethodPost, "/2013-04-01/hostedzone/"+url.PathEscape(zoneID)+"/rrset", nil, route53ChangeBatchXML(changes), nil, operation)
}

func (p *route53Provider) doXML(ctx context.Context, method string, path string, query map[string]string, body string, out any, operation string) error {
	if err := p.validateAuth(operation); err != nil {
		return err
	}
	u, err := url.Parse(p.baseURL + path)
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "build request failed", Cause: err}
	}
	queryString := route53CanonicalQuery(query)
	u.RawQuery = queryString
	req, err := http.NewRequestWithContext(ctx, method, u.String(), strings.NewReader(body))
	if err != nil {
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	timestamp := p.now().UTC().Format("20060102T150405Z")
	date := p.now().UTC().Format("20060102")
	payloadHash := sha256Hex(body)
	headers := route53SignedHeaders(u.Host, timestamp, payloadHash, p.sessionToken)
	auth := route53Authorization(method, path, queryString, body, u.Host, timestamp, date, p.accessKeyID, p.secretAccessKey, p.sessionToken)
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
		var errorBody route53ErrorResponse
		_ = xml.Unmarshal(data, &errorBody)
		return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: route53ErrorMessage(resp.StatusCode, errorBody, data)}
	}
	if out != nil && len(bytes.TrimSpace(data)) > 0 {
		if err := xml.Unmarshal(data, out); err != nil {
			return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed", Cause: err}
		}
	}
	return nil
}

func (p *route53Provider) validateAuth(operation string) error {
	if p.accessKeyID != "" && p.secretAccessKey != "" {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing AccessKeyId or SecretAccessKey"}
}

func route53Authorization(method string, path string, queryString string, payload string, host string, timestamp string, date string, accessKey string, secretKey string, sessionToken string) string {
	payloadHash := sha256Hex(payload)
	headers := route53SignedHeaders(host, timestamp, payloadHash, sessionToken)
	canonicalHeaders, signedHeaders := route53CanonicalHeaders(headers)
	canonicalRequest := strings.ToUpper(method) + "\n" +
		route53NormalizePath(path) + "\n" +
		queryString + "\n" +
		canonicalHeaders + "\n" +
		signedHeaders + "\n" +
		payloadHash
	credentialScope := date + "/us-east-1/route53/aws4_request"
	stringToSign := "AWS4-HMAC-SHA256\n" + timestamp + "\n" + credentialScope + "\n" + sha256Hex(canonicalRequest)
	signature := hmacSHA256HexBytes(stringToSign, route53SigningKey(date, secretKey))
	return "AWS4-HMAC-SHA256 Credential=" + accessKey + "/" + credentialScope + ", SignedHeaders=" + signedHeaders + ", Signature=" + signature
}

func route53SignedHeaders(host string, timestamp string, payloadHash string, sessionToken string) map[string]string {
	headers := map[string]string{
		"content-type":         "application/xml",
		"host":                 host,
		"x-amz-content-sha256": payloadHash,
		"x-amz-date":           timestamp,
	}
	if strings.TrimSpace(sessionToken) != "" {
		headers["x-amz-security-token"] = strings.TrimSpace(sessionToken)
	}
	return headers
}

func route53CanonicalHeaders(headers map[string]string) (string, string) {
	keys := make([]string, 0, len(headers))
	for key := range headers {
		keys = append(keys, strings.ToLower(key))
	}
	sort.Strings(keys)
	lines := make([]string, 0, len(keys))
	for _, key := range keys {
		lines = append(lines, key+":"+strings.TrimSpace(headers[key]))
	}
	return strings.Join(lines, "\n") + "\n", strings.Join(keys, ";")
}

func route53SigningKey(date string, secretKey string) []byte {
	kDate := hmacSHA256Bytes(date, []byte("AWS4"+secretKey))
	kRegion := hmacSHA256Bytes("us-east-1", kDate)
	kService := hmacSHA256Bytes("route53", kRegion)
	return hmacSHA256Bytes("aws4_request", kService)
}

func route53CanonicalQuery(query map[string]string) string {
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

func route53NormalizePath(path string) string {
	parts := strings.Split(path, "/")
	for i, part := range parts {
		decoded, err := url.PathUnescape(part)
		if err == nil {
			part = decoded
		}
		parts[i] = url.PathEscape(part)
	}
	return strings.Join(parts, "/")
}

func route53RecordFromInput(zone dns.Zone, input dns.RecordInput) route53RecordSet {
	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	return route53RecordSet{
		Name:            route53RecordName(input.Name, zone.Domain),
		Type:            recordType,
		TTL:             300,
		ResourceRecords: []route53ResourceRecord{{Value: route53FormatValue(recordType, input.Value)}},
	}
}

func route53RecordSetToDomain(record route53RecordSet, domain string) dns.Record {
	host := route53ExtractHost(record.Name, domain)
	recordType := strings.ToUpper(strings.TrimSpace(record.Type))
	return dns.Record{
		RemoteID: route53RecordID(host, recordType, record.SetIdentifier),
		Name:     host,
		Type:     recordType,
		Value:    route53DisplayValue(recordType, record),
		LineID:   "default",
		Line:     "默认",
	}
}

type route53RecordSetChange struct {
	Action string
	Record route53RecordSet
}

func route53ChangeXML(action string, record route53RecordSet) string {
	return route53ChangeBatchXML([]route53RecordSetChange{{Action: action, Record: record}})
}

func route53ChangeBatchXML(changes []route53RecordSetChange) string {
	var b strings.Builder
	b.WriteString(`<ChangeResourceRecordSetsRequest xmlns="https://route53.amazonaws.com/doc/2013-04-01/"><ChangeBatch><Changes>`)
	for _, change := range changes {
		b.WriteString(route53ChangeXMLFragment(change.Action, change.Record))
	}
	b.WriteString(`</Changes></ChangeBatch></ChangeResourceRecordSetsRequest>`)
	return b.String()
}

func route53ChangeXMLFragment(action string, record route53RecordSet) string {
	var b strings.Builder
	b.WriteString(`<Change>`)
	b.WriteString("<Action>" + route53XMLEscape(strings.ToUpper(action)) + "</Action><ResourceRecordSet>")
	b.WriteString("<Name>" + route53XMLEscape(record.Name) + "</Name>")
	b.WriteString("<Type>" + route53XMLEscape(strings.ToUpper(record.Type)) + "</Type>")
	b.WriteString(fmt.Sprintf("<TTL>%d</TTL><ResourceRecords>", route53TTL(record.TTL)))
	for _, rr := range record.ResourceRecords {
		b.WriteString("<ResourceRecord><Value>" + route53XMLEscape(rr.Value) + "</Value></ResourceRecord>")
	}
	b.WriteString("</ResourceRecords></ResourceRecordSet></Change>")
	return b.String()
}

func route53SameRecordSetKey(a route53RecordSet, b route53RecordSet) bool {
	return strings.EqualFold(strings.TrimSpace(a.Name), strings.TrimSpace(b.Name)) &&
		strings.EqualFold(strings.TrimSpace(a.Type), strings.TrimSpace(b.Type)) &&
		strings.TrimSpace(a.SetIdentifier) == strings.TrimSpace(b.SetIdentifier)
}

func route53RecordName(name string, domain string) string {
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

func route53ExtractHost(fqdn string, domain string) string {
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

func route53FormatValue(recordType string, value string) string {
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

func route53DisplayValue(recordType string, record route53RecordSet) string {
	value := ""
	if len(record.ResourceRecords) > 0 {
		value = strings.TrimSpace(record.ResourceRecords[0].Value)
	} else if strings.TrimSpace(record.AliasTarget.DNSName) != "" {
		value = strings.TrimSpace(record.AliasTarget.DNSName)
	}
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

func route53RecordID(name string, recordType string, identifier string) string {
	sum := sha1.Sum([]byte(strings.ToLower(strings.TrimSpace(name)) + "|" + strings.ToUpper(strings.TrimSpace(recordType)) + "|" + strings.ToLower(strings.TrimSpace(identifier))))
	return hex.EncodeToString(sum[:])
}

func route53TTL(ttl int) int {
	if ttl <= 0 {
		return 300
	}
	return ttl
}

func route53XMLBool(value string) bool {
	return strings.EqualFold(strings.TrimSpace(value), "true")
}

func route53XMLEscape(value string) string {
	var b bytes.Buffer
	_ = xml.EscapeText(&b, []byte(value))
	return b.String()
}

func route53ErrorMessage(status int, body route53ErrorResponse, data []byte) string {
	if strings.TrimSpace(body.Message) != "" {
		return strings.TrimSpace(body.Message)
	}
	if strings.TrimSpace(body.Flat) != "" {
		return strings.TrimSpace(body.Flat)
	}
	text := strings.TrimSpace(string(data))
	if text != "" {
		return strings.TrimSpace(stripXMLTags(text))
	}
	return fmt.Sprintf("Route53 API returned HTTP %d", status)
}

func stripXMLTags(value string) string {
	var out strings.Builder
	inTag := false
	for _, r := range value {
		switch r {
		case '<':
			inTag = true
		case '>':
			inTag = false
		default:
			if !inTag {
				out.WriteRune(r)
			}
		}
	}
	return out.String()
}

func hmacSHA256Bytes(value string, key []byte) []byte {
	mac := hmac.New(sha256.New, key)
	_, _ = mac.Write([]byte(value))
	return mac.Sum(nil)
}

func hmacSHA256HexBytes(value string, key []byte) string {
	return hex.EncodeToString(hmacSHA256Bytes(value, key))
}
