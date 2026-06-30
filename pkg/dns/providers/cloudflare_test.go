package providers

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"kldns/pkg/dns"
)

func TestCloudflareProviderRecordLifecycle(t *testing.T) {
	var requests []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		requests = append(requests, r.Method+" "+r.URL.String())
		if got := r.Header.Get("Authorization"); got != "Bearer test-token" {
			t.Fatalf("authorization header = %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/zones":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"success":     true,
				"result":      []map[string]any{{"id": "zone1", "name": "example.com"}},
				"result_info": map[string]any{"page": 1, "total_pages": 1},
			})
		case r.Method == http.MethodGet && r.URL.Path == "/zones/zone1/dns_records":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"success":     true,
				"result":      []map[string]any{{"id": "record1", "name": "www.example.com", "type": "A", "content": "1.1.1.1", "proxied": false}},
				"result_info": map[string]any{"page": 1, "total_pages": 1},
			})
		case r.Method == http.MethodPost && r.URL.Path == "/zones/zone1/dns_records":
			var payload map[string]any
			_ = json.NewDecoder(r.Body).Decode(&payload)
			if payload["name"] != "cdn.example.com" || payload["proxied"] != true {
				t.Fatalf("unexpected create payload: %#v", payload)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{
				"success": true,
				"result":  map[string]any{"id": "record2", "name": "cdn.example.com", "type": "CNAME", "content": "target.example.com", "proxied": true},
			})
		case r.Method == http.MethodPatch && r.URL.Path == "/zones/zone1/dns_records/record2":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"success": true,
				"result":  map[string]any{"id": "record2", "name": "api.example.com", "type": "A", "content": "2.2.2.2", "proxied": false},
			})
		case r.Method == http.MethodDelete && r.URL.Path == "/zones/zone1/dns_records/record2":
			_ = json.NewEncoder(w).Encode(map[string]any{"success": true, "result": map[string]any{"id": "record2"}})
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	provider := &cloudflareProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ApiToken": "test-token", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "zone1" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) != 2 || lines[1].ID != "1" || lines[1].Name != "CDN" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].Name != "www" || records[0].LineID != "0" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "1"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "record2" || created.Name != "cdn" || created.LineID != "1" || created.Line != "CDN" {
		t.Fatalf("unexpected created record: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "record2", dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.Name != "api" || updated.LineID != "0" {
		t.Fatalf("unexpected updated record: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "record2"); err != nil {
		t.Fatal(err)
	}
	if len(requests) < 6 {
		t.Fatalf("expected lifecycle requests, got %#v", requests)
	}
}

func TestCloudflareProviderErrorDoesNotLeakToken(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusForbidden)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"errors":  []map[string]any{{"code": 10000, "message": "authentication failed"}},
		})
	}))
	defer server.Close()

	provider := &cloudflareProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ApiToken": "secret-token", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected cloudflare error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "secret-token") || strings.Contains(message, "Authorization") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestCloudflareProviderRequiresAuth(t *testing.T) {
	provider := &cloudflareProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}
