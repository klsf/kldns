package providers

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"kldns/pkg/dns"
)

func TestHuaweiProviderRecordLifecycle(t *testing.T) {
	var requests []string
	fixedTime := time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC)
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		bodyBytes, _ := io.ReadAll(r.Body)
		payload := string(bodyBytes)
		query := map[string]string{}
		for key := range r.URL.Query() {
			query[key] = r.URL.Query().Get(key)
		}
		expectedAuth := huaweiAuthorization(r.Method, r.URL.Path, query, payload, r.Host, "20260616T102030Z", "akid", "secret")
		if got := r.Header.Get("Authorization"); got != expectedAuth {
			t.Fatalf("authorization mismatch\n got: %s\nwant: %s", got, expectedAuth)
		}
		if got := r.Header.Get("X-Sdk-Date"); got != "20260616T102030Z" {
			t.Fatalf("x-sdk-date = %q", got)
		}
		requests = append(requests, r.Method+" "+r.URL.Path)
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/v2/zones":
			if r.URL.Query().Get("type") != "public" || r.URL.Query().Get("limit") != "500" {
				t.Fatalf("unexpected zone query: %s", r.URL.RawQuery)
			}
			if r.URL.Query().Get("enterprise_project_id") != "all_granted_eps" {
				t.Fatalf("enterprise_project_id = %q", r.URL.Query().Get("enterprise_project_id"))
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"zones": []map[string]any{{"id": "zone1", "name": "example.com.", "zone_type": "public"}}})
		case r.Method == http.MethodGet && r.URL.Path == "/v2/zones/zone1/recordsets":
			_ = json.NewEncoder(w).Encode(map[string]any{"recordsets": []map[string]any{{"id": "record1", "name": "www.example.com.", "type": "A", "records": []string{"1.1.1.1"}}}})
		case r.Method == http.MethodPost && r.URL.Path == "/v2/zones/zone1/recordsets":
			var payload map[string]any
			_ = json.Unmarshal(bodyBytes, &payload)
			if payload["name"] != "api.example.com." || payload["type"] != "A" {
				t.Fatalf("unexpected create payload: %#v", payload)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"id": "record2", "name": "api.example.com.", "type": "A", "records": []string{"2.2.2.2"}})
		case r.Method == http.MethodPut && r.URL.Path == "/v2/zones/zone1/recordsets/record2":
			var payload map[string]any
			_ = json.Unmarshal(bodyBytes, &payload)
			records, _ := payload["records"].([]any)
			if payload["name"] != "cdn.example.com." || payload["type"] != "CNAME" || len(records) != 1 || records[0] != "target.example.com." {
				t.Fatalf("unexpected update payload: %#v", payload)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"id": "record2", "name": "cdn.example.com.", "type": "CNAME", "records": []string{"target.example.com."}})
		case r.Method == http.MethodGet && r.URL.Path == "/v2/zones/zone1/recordsets/record2":
			_ = json.NewEncoder(w).Encode(map[string]any{"id": "record2", "name": "cdn.example.com.", "type": "CNAME", "records": []string{"target.example.com."}})
		case r.Method == http.MethodDelete && r.URL.Path == "/v2/zones/zone1/recordsets/record2":
			w.WriteHeader(http.StatusNoContent)
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	provider := &huaweiProvider{client: server.Client(), now: func() time.Time { return fixedTime }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "secret", "EnterpriseProjectId": "all_granted_eps", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "zone1" || zones[0].Domain != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) != 1 || lines[0].ID != "default" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "record1" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "record2" || created.Name != "api" || created.Value != "2.2.2.2" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "record2", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "record2" || updated.Name != "cdn" || updated.Value != "target.example.com" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	got, err := provider.GetRecord(context.Background(), zones[0], "record2")
	if err != nil {
		t.Fatal(err)
	}
	if got.RemoteID != "record2" || got.Type != "CNAME" {
		t.Fatalf("unexpected record info: %#v", got)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "record2"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(requests, ",") != "GET /v2/zones,GET /v2/zones,GET /v2/zones/zone1/recordsets,POST /v2/zones/zone1/recordsets,PUT /v2/zones/zone1/recordsets/record2,GET /v2/zones/zone1/recordsets/record2,DELETE /v2/zones/zone1/recordsets/record2" {
		t.Fatalf("unexpected requests: %#v", requests)
	}
}

func TestHuaweiProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusUnauthorized)
		_ = json.NewEncoder(w).Encode(map[string]any{"message": "authentication failed"})
	}))
	defer server.Close()

	provider := &huaweiProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "very-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected HuaweiCloud error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "very-secret") || strings.Contains(message, "Signature") || strings.Contains(message, "Authorization") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestHuaweiProviderAutoRegionFallback(t *testing.T) {
	var hosts []string
	provider := &huaweiProvider{
		client: &http.Client{Transport: roundTripFunc(func(req *http.Request) (*http.Response, error) {
			hosts = append(hosts, req.Host)
			if req.Host == "dns.cn-north-4.myhuaweicloud.com" {
				return jsonResponse(http.StatusUnauthorized, map[string]any{"message": "authentication failed for region"}), nil
			}
			if req.Host == "dns.ap-southeast-3.myhuaweicloud.com" {
				return jsonResponse(http.StatusOK, map[string]any{"zones": []map[string]any{{"id": "intl-zone", "name": "example.net.", "zone_type": "public"}}}), nil
			}
			t.Fatalf("unexpected host: %s", req.Host)
			return nil, nil
		})},
		now: func() time.Time { return time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC) },
	}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "secret"}); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "intl-zone" || zones[0].Domain != "example.net" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	if strings.Join(hosts, ",") != "dns.cn-north-4.myhuaweicloud.com,dns.ap-southeast-3.myhuaweicloud.com" {
		t.Fatalf("unexpected hosts: %#v", hosts)
	}
}

func TestHuaweiProviderOmitsEnterpriseProjectWhenBlank(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Query().Has("enterprise_project_id") {
			t.Fatalf("enterprise_project_id should be omitted when blank: %s", r.URL.RawQuery)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"zones": []map[string]any{{"id": "zone1", "name": "example.com.", "zone_type": "public"}}})
	}))
	defer server.Close()

	provider := &huaweiProvider{client: server.Client(), now: func() time.Time { return time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC) }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "secret", "Region": huaweiDefaultRegion, "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if _, err := provider.ListZones(context.Background()); err != nil {
		t.Fatal(err)
	}
}

func TestHuaweiMXValueFormatAddsDefaultPriority(t *testing.T) {
	if got := huaweiFormatValue("MX", "mail.example.com"); got != "10 mail.example.com." {
		t.Fatalf("formatted MX = %q", got)
	}
	if got := huaweiFormatValue("MX", "20 mail.example.com."); got != "20 mail.example.com." {
		t.Fatalf("formatted MX with priority = %q", got)
	}
	if got := huaweiNormalizeValue("MX", "10 mail.example.com."); got != "mail.example.com" {
		t.Fatalf("normalized MX = %q", got)
	}
}

func TestHuaweiNormalizePathAddsTrailingSlash(t *testing.T) {
	if got := huaweiNormalizePath("/v2/zones"); got != "/v2/zones/" {
		t.Fatalf("normalized path = %q", got)
	}
	if got := huaweiNormalizePath("/v2/zones/zone1/recordsets"); got != "/v2/zones/zone1/recordsets/" {
		t.Fatalf("normalized recordsets path = %q", got)
	}
}

func TestHuaweiProviderRequiresAuth(t *testing.T) {
	provider := &huaweiProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

type roundTripFunc func(*http.Request) (*http.Response, error)

func (f roundTripFunc) RoundTrip(req *http.Request) (*http.Response, error) {
	return f(req)
}

func jsonResponse(status int, payload any) *http.Response {
	body, _ := json.Marshal(payload)
	return &http.Response{
		StatusCode: status,
		Header:     http.Header{"Content-Type": []string{"application/json"}},
		Body:       io.NopCloser(strings.NewReader(string(body))),
	}
}
