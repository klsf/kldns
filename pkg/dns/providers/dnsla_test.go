package providers

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"kldns/pkg/dns"
)

func TestDNSLAProviderRecordLifecycle(t *testing.T) {
	var requests []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		requests = append(requests, r.Method+" "+r.URL.Path)
		wantAuth := "Basic " + base64.StdEncoding.EncodeToString([]byte("api-id:api-secret"))
		if got := r.Header.Get("Authorization"); got != wantAuth {
			t.Fatalf("authorization = %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/api/domainList":
			if r.URL.Query().Get("pageIndex") != "1" {
				t.Fatalf("pageIndex = %q", r.URL.Query().Get("pageIndex"))
			}
			writeDNSLAOK(w, map[string]any{"total": 1, "results": []map[string]any{{"id": 10, "displayDomain": "example.com."}}})
		case r.Method == http.MethodGet && r.URL.Path == "/api/availableLine":
			if r.URL.Query().Get("domain") != "example.com" {
				t.Fatalf("domain query = %q", r.URL.Query().Get("domain"))
			}
			writeDNSLAOK(w, []map[string]any{{"id": 2, "value": "联通", "name": "unicom"}})
		case r.Method == http.MethodGet && r.URL.Path == "/api/recordList":
			if r.URL.Query().Get("domainId") != "10" {
				t.Fatalf("domainId = %q", r.URL.Query().Get("domainId"))
			}
			writeDNSLAOK(w, map[string]any{"total": 1, "results": []map[string]any{{"id": 20, "displayHost": "www", "type": 1, "displayData": "1.1.1.1", "lineId": 0, "lineName": "默认"}}})
		case r.Method == http.MethodPost && r.URL.Path == "/api/record":
			var payload map[string]any
			_ = json.NewDecoder(r.Body).Decode(&payload)
			if payload["domainId"] != "10" || payload["host"] != "api" || payload["type"] != float64(1) || payload["lineId"] != "2" {
				t.Fatalf("unexpected create payload: %#v", payload)
			}
			writeDNSLAOK(w, map[string]any{"id": 21, "displayHost": "api", "type": 1, "displayData": "2.2.2.2", "lineId": 2, "lineName": "联通"})
		case r.Method == http.MethodPut && r.URL.Path == "/api/record":
			var payload map[string]any
			_ = json.NewDecoder(r.Body).Decode(&payload)
			if payload["id"] != "21" || payload["host"] != "cdn" || payload["type"] != float64(5) {
				t.Fatalf("unexpected update payload: %#v", payload)
			}
			writeDNSLAOK(w, map[string]any{"id": 21, "displayHost": "cdn", "type": 5, "displayData": "target.example.com", "lineId": 0, "lineName": "默认"})
		case r.Method == http.MethodDelete && r.URL.Path == "/api/record":
			if r.URL.Query().Get("id") != "21" {
				t.Fatalf("delete id = %q", r.URL.Query().Get("id"))
			}
			writeDNSLAOK(w, map[string]any{})
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	provider := &dnslaProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ApiId": "api-id", "ApiSecret": "api-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "10" || zones[0].Domain != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) != 2 || lines[1].ID != "2" || lines[1].Name != "联通" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "20" || records[0].Type != "A" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "2"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "21" || created.Type != "A" || created.Line != "联通" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "21", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "21" || updated.Type != "CNAME" || updated.Line != "默认" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "21"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(requests, ",") != "GET /api/domainList,GET /api/domainList,GET /api/availableLine,GET /api/recordList,POST /api/record,PUT /api/record,DELETE /api/record" {
		t.Fatalf("unexpected requests: %#v", requests)
	}
}

func TestDNSLAProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"code": 401, "msg": "authentication failed"})
	}))
	defer server.Close()

	provider := &dnslaProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ApiId": "api-id", "ApiSecret": "api-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected DNSLA error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "api-secret") || strings.Contains(message, "Authorization") || strings.Contains(message, "Basic") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestDNSLAProviderRequiresAuth(t *testing.T) {
	provider := &dnslaProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func writeDNSLAOK(w http.ResponseWriter, data any) {
	_ = json.NewEncoder(w).Encode(map[string]any{"code": 200, "msg": "ok", "data": data})
}
