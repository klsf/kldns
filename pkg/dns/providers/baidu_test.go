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

func TestBaiduProviderRecordLifecycle(t *testing.T) {
	var requests []string
	fixedTime := time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC)
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		bodyBytes, _ := io.ReadAll(r.Body)
		payload := string(bodyBytes)
		query := map[string]string{}
		for key := range r.URL.Query() {
			query[key] = r.URL.Query().Get(key)
		}
		expectedAuth := baiduAuthorization(r.Method, r.URL.Path, query, payload, r.Host, "2026-06-16T10:20:30Z", "akid", "secret")
		if got := r.Header.Get("Authorization"); got != expectedAuth {
			t.Fatalf("authorization mismatch\n got: %s\nwant: %s", got, expectedAuth)
		}
		if got := r.Header.Get("x-bce-date"); got != "2026-06-16T10:20:30Z" {
			t.Fatalf("x-bce-date = %q", got)
		}
		requests = append(requests, r.Method+" "+r.URL.Path)
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/v1/dns/zone":
			if r.URL.Query().Get("maxKeys") != "1000" {
				t.Fatalf("unexpected zone query: %s", r.URL.RawQuery)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"zones": []map[string]any{{"id": "zone1", "name": "example.com"}}})
		case r.Method == http.MethodGet && r.URL.Path == "/v1/dns/customline":
			_ = json.NewEncoder(w).Encode(map[string]any{"lineList": []map[string]any{{"name": "华东"}}})
		case r.Method == http.MethodGet && r.URL.Path == "/v1/dns/zone/example.com/record" && r.URL.Query().Get("maxKeys") == "1000":
			_ = json.NewEncoder(w).Encode(map[string]any{"records": []map[string]any{
				{"id": "record1", "rr": "www", "type": "A", "value": "1.1.1.1", "line": "default"},
				{"id": "record2", "rr": "api", "type": "A", "value": "2.2.2.2", "line": "ct"},
			}})
		case r.Method == http.MethodPost && r.URL.Path == "/v1/dns/zone/example.com/record":
			if r.URL.Query().Get("clientToken") != "fixed-token" {
				t.Fatalf("unexpected client token: %s", r.URL.RawQuery)
			}
			var payload map[string]any
			_ = json.Unmarshal(bodyBytes, &payload)
			if payload["rr"] != "api" || payload["type"] != "A" || payload["line"] != "ct" {
				t.Fatalf("unexpected create payload: %#v", payload)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{})
		case r.Method == http.MethodPut && r.URL.Path == "/v1/dns/zone/example.com/record/record2":
			var payload map[string]any
			_ = json.Unmarshal(bodyBytes, &payload)
			if payload["rr"] != "mail" || payload["type"] != "MX" || payload["priority"] != float64(10) {
				t.Fatalf("unexpected update payload: %#v", payload)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{})
		case r.Method == http.MethodGet && r.URL.Path == "/v1/dns/zone/example.com/record" && r.URL.Query().Get("id") == "record2":
			_ = json.NewEncoder(w).Encode(map[string]any{"records": []map[string]any{{"id": "record2", "rr": "mail", "type": "MX", "value": "mail.example.com", "line": "default"}}})
		case r.Method == http.MethodDelete && r.URL.Path == "/v1/dns/zone/example.com/record/record2":
			_ = json.NewEncoder(w).Encode(map[string]any{})
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	provider := &baiduProvider{client: server.Client(), now: func() time.Time { return fixedTime }, token: func() string { return "fixed-token" }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "secret", "BaseURL": server.URL}); err != nil {
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
	if len(lines) != 7 || lines[6].ID != "华东" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 2 || records[0].RemoteID != "record1" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "ct"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "record2" || created.Name != "api" || created.LineID != "ct" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "record2", dns.RecordInput{Name: "mail", Type: "MX", Value: "mail.example.com"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "record2" || updated.Name != "mail" || updated.Type != "MX" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	got, err := provider.GetRecord(context.Background(), zones[0], "record2")
	if err != nil {
		t.Fatal(err)
	}
	if got.RemoteID != "record2" || got.Type != "MX" {
		t.Fatalf("unexpected record info: %#v", got)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "record2"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(requests, ",") != "GET /v1/dns/zone,GET /v1/dns/zone,GET /v1/dns/customline,GET /v1/dns/zone/example.com/record,POST /v1/dns/zone/example.com/record,GET /v1/dns/zone/example.com/record,PUT /v1/dns/zone/example.com/record/record2,GET /v1/dns/zone/example.com/record,DELETE /v1/dns/zone/example.com/record/record2" {
		t.Fatalf("unexpected requests: %#v", requests)
	}
}

func TestBaiduProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusUnauthorized)
		_ = json.NewEncoder(w).Encode(map[string]any{"message": "authentication failed"})
	}))
	defer server.Close()

	provider := &baiduProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }, token: func() string { return "fixed-token" }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "very-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected BaiduCloud error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "very-secret") || strings.Contains(message, "Authorization") || strings.Contains(message, "Signature") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestBaiduProviderRequiresAuth(t *testing.T) {
	provider := &baiduProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}
