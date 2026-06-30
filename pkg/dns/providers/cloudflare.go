package providers

import (
	"bytes"
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

const cloudflareDefaultBaseURL = "https://api.cloudflare.com/client/v4/"

func init() {
	dns.Register("Cloudflare", func() dns.Provider {
		return &cloudflareProvider{
			baseURL: cloudflareDefaultBaseURL,
			client:  providerhttp.NewClient(),
		}
	})
}

type cloudflareProvider struct {
	apiToken string
	email    string
	apiKey   string
	baseURL  string
	client   *http.Client
}

type cloudflareResponse struct {
	Success    bool                 `json:"success"`
	Result     json.RawMessage      `json:"result"`
	Errors     []cloudflareAPIError `json:"errors"`
	ResultInfo cloudflareResultInfo `json:"result_info"`
}

type cloudflareAPIError struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
}

type cloudflareResultInfo struct {
	Page       int `json:"page"`
	TotalPages int `json:"total_pages"`
}

type cloudflareZone struct {
	ID   string `json:"id"`
	Name string `json:"name"`
}

type cloudflareRecord struct {
	ID      string `json:"id"`
	Name    string `json:"name"`
	Type    string `json:"type"`
	Content string `json:"content"`
	Proxied bool   `json:"proxied"`
}

func (p *cloudflareProvider) Key() string {
	return "Cloudflare"
}

func (p *cloudflareProvider) Label() string {
	return "Cloudflare"
}

func (p *cloudflareProvider) ConfigFields() []dns.ConfigField {
	return []dns.ConfigField{
		{Name: "ApiToken", Label: "ApiToken", Required: true, Secret: true, Description: "Cloudflare API Token"},
	}
}

func (p *cloudflareProvider) Configure(config map[string]string) error {
	p.apiToken = strings.TrimSpace(config["ApiToken"])
	p.email = strings.TrimSpace(config["Email"])
	p.apiKey = strings.TrimSpace(config["ApiKey"])
	p.baseURL = providerhttp.NormalizeBaseURL(config["BaseURL"], cloudflareDefaultBaseURL, true)
	if p.client == nil {
		p.client = providerhttp.NewClient()
	}
	return nil
}

func (p *cloudflareProvider) Check(ctx context.Context) error {
	if err := p.validateAuth("check"); err != nil {
		return err
	}
	_, err := p.ListZones(ctx)
	return err
}

func (p *cloudflareProvider) ListZones(ctx context.Context) ([]dns.Zone, error) {
	var zones []dns.Zone
	page := 1
	for {
		var cfZones []cloudflareZone
		info, err := p.doJSON(ctx, http.MethodGet, "zones?page="+strconv.Itoa(page)+"&per_page=100", nil, &cfZones, "list_zones")
		if err != nil {
			return nil, err
		}
		for _, zone := range cfZones {
			zones = append(zones, dns.Zone{ID: zone.ID, Domain: zone.Name})
		}
		if info.TotalPages <= page || info.TotalPages == 0 {
			break
		}
		page++
	}
	return zones, nil
}

func (p *cloudflareProvider) ListRecordLines(context.Context, dns.Zone) ([]dns.RecordLine, error) {
	return []dns.RecordLine{{ID: "0", Name: "默认"}, {ID: "1", Name: "CDN"}}, nil
}

func (p *cloudflareProvider) CreateRecord(ctx context.Context, zone dns.Zone, input dns.RecordInput) (dns.Record, error) {
	payload := map[string]any{
		"name":    buildCloudflareRecordName(input.Name, zone.Domain),
		"type":    strings.ToUpper(strings.TrimSpace(input.Type)),
		"content": strings.TrimSpace(input.Value),
		"proxied": cloudflareProxied(input.LineID),
	}
	var record cloudflareRecord
	_, err := p.doJSON(ctx, http.MethodPost, "zones/"+url.PathEscape(zone.ID)+"/dns_records", payload, &record, "create_record")
	if err != nil {
		return dns.Record{}, err
	}
	return cloudflareRecordToDomain(record, zone.Domain), nil
}

func (p *cloudflareProvider) UpdateRecord(ctx context.Context, zone dns.Zone, remoteID string, input dns.RecordInput) (dns.Record, error) {
	payload := map[string]any{
		"name":    buildCloudflareRecordName(input.Name, zone.Domain),
		"type":    strings.ToUpper(strings.TrimSpace(input.Type)),
		"content": strings.TrimSpace(input.Value),
		"proxied": cloudflareProxied(input.LineID),
	}
	var record cloudflareRecord
	_, err := p.doJSON(ctx, http.MethodPatch, "zones/"+url.PathEscape(zone.ID)+"/dns_records/"+url.PathEscape(remoteID), payload, &record, "update_record")
	if err != nil {
		return dns.Record{}, err
	}
	return cloudflareRecordToDomain(record, zone.Domain), nil
}

func (p *cloudflareProvider) DeleteRecord(ctx context.Context, zone dns.Zone, remoteID string) error {
	_, err := p.doJSON(ctx, http.MethodDelete, "zones/"+url.PathEscape(zone.ID)+"/dns_records/"+url.PathEscape(remoteID), nil, nil, "delete_record")
	return err
}

func (p *cloudflareProvider) GetRecord(ctx context.Context, zone dns.Zone, remoteID string) (dns.Record, error) {
	var record cloudflareRecord
	_, err := p.doJSON(ctx, http.MethodGet, "zones/"+url.PathEscape(zone.ID)+"/dns_records/"+url.PathEscape(remoteID), nil, &record, "get_record")
	if err != nil {
		return dns.Record{}, err
	}
	return cloudflareRecordToDomain(record, zone.Domain), nil
}

func (p *cloudflareProvider) ListRecords(ctx context.Context, zone dns.Zone) ([]dns.Record, error) {
	var records []dns.Record
	page := 1
	for {
		var cfRecords []cloudflareRecord
		info, err := p.doJSON(ctx, http.MethodGet, "zones/"+url.PathEscape(zone.ID)+"/dns_records?page="+strconv.Itoa(page)+"&per_page=100", nil, &cfRecords, "list_records")
		if err != nil {
			return nil, err
		}
		for _, record := range cfRecords {
			records = append(records, cloudflareRecordToDomain(record, zone.Domain))
		}
		if info.TotalPages <= page || info.TotalPages == 0 {
			break
		}
		page++
	}
	return records, nil
}

func (p *cloudflareProvider) doJSON(ctx context.Context, method string, path string, payload any, out any, operation string) (cloudflareResultInfo, error) {
	if err := p.validateAuth(operation); err != nil {
		return cloudflareResultInfo{}, err
	}
	var body io.Reader
	if payload != nil {
		data, err := json.Marshal(payload)
		if err != nil {
			return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "encode request failed", Cause: err}
		}
		body = bytes.NewReader(data)
	}
	req, err := http.NewRequestWithContext(ctx, method, p.endpoint(path), body)
	if err != nil {
		return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "create request failed", Cause: err}
	}
	req.Header.Set("Content-Type", "application/json")
	if p.apiToken != "" {
		req.Header.Set("Authorization", "Bearer "+p.apiToken)
	} else {
		req.Header.Set("X-Auth-Email", p.email)
		req.Header.Set("X-Auth-Key", p.apiKey)
	}
	resp, err := p.client.Do(req)
	if err != nil {
		return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "request failed", Cause: err}
	}
	defer resp.Body.Close()
	data, err := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if err != nil {
		return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "read response failed", Cause: err}
	}
	var wrapper cloudflareResponse
	if err := json.Unmarshal(data, &wrapper); err != nil {
		return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode response failed"}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 || !wrapper.Success {
		return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: cloudflareErrorMessage(resp.StatusCode, wrapper.Errors)}
	}
	if out != nil && wrapper.Result != nil {
		if err := json.Unmarshal(wrapper.Result, out); err != nil {
			return cloudflareResultInfo{}, &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "decode result failed", Cause: err}
		}
	}
	return wrapper.ResultInfo, nil
}

func (p *cloudflareProvider) endpoint(path string) string {
	if strings.HasPrefix(path, "http://") || strings.HasPrefix(path, "https://") {
		return path
	}
	return strings.TrimRight(p.baseURL, "/") + "/" + strings.TrimLeft(path, "/")
}

func (p *cloudflareProvider) validateAuth(operation string) error {
	if p.apiToken != "" || (p.email != "" && p.apiKey != "") {
		return nil
	}
	return &dns.ProviderError{Provider: p.Key(), Operation: operation, Message: "missing ApiToken"}
}

func cloudflareErrorMessage(status int, errors []cloudflareAPIError) string {
	if len(errors) > 0 && strings.TrimSpace(errors[0].Message) != "" {
		return errors[0].Message
	}
	if status > 0 {
		return fmt.Sprintf("Cloudflare API returned HTTP %d", status)
	}
	return "Cloudflare API request failed"
}

func buildCloudflareRecordName(name string, domain string) string {
	name = strings.Trim(strings.TrimSpace(name), ".")
	domain = strings.Trim(strings.TrimSpace(domain), ".")
	if name == "" || name == "@" || name == domain {
		return domain
	}
	if strings.HasSuffix(name, "."+domain) {
		return name
	}
	return name + "." + domain
}

func extractCloudflareHost(fqdn string, domain string) string {
	fqdn = strings.Trim(strings.TrimSpace(fqdn), ".")
	domain = strings.Trim(strings.TrimSpace(domain), ".")
	if fqdn == "" || fqdn == domain {
		return "@"
	}
	suffix := "." + domain
	if domain != "" && strings.HasSuffix(fqdn, suffix) {
		return strings.TrimSuffix(fqdn, suffix)
	}
	return fqdn
}

func cloudflareProxied(lineID string) bool {
	lineID = strings.TrimSpace(lineID)
	return lineID != "" && lineID != "0"
}

func cloudflareLineID(proxied bool) string {
	if proxied {
		return "1"
	}
	return "0"
}

func cloudflareLine(proxied bool) string {
	if proxied {
		return "CDN"
	}
	return "默认"
}

func cloudflareRecordToDomain(record cloudflareRecord, domain string) dns.Record {
	return dns.Record{
		RemoteID: record.ID,
		Name:     extractCloudflareHost(record.Name, domain),
		Type:     record.Type,
		Value:    record.Content,
		LineID:   cloudflareLineID(record.Proxied),
		Line:     cloudflareLine(record.Proxied),
	}
}
