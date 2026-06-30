package providers

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
	"time"

	"kldns/pkg/dns"
)

func TestDNSComProviderRecordLifecycle(t *testing.T) {
	var actions []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("method = %s, want POST", r.Method)
		}
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		requireForm(t, r.Form, "apiKey", "api-key")
		requireForm(t, r.Form, "timestamp", "1780000000")
		if got := r.Form.Get("hash"); got == "" || got != dnscomHash(dnscomFormToMap(r.Form), "api-secret") {
			t.Fatalf("invalid hash: %q", got)
		}
		action := strings.TrimPrefix(r.URL.Path, "/")
		actions = append(actions, action)
		w.Header().Set("Content-Type", "application/json")
		switch action {
		case "domain/lists/":
			writeDNSComOK(w, map[string]any{"total": 1, "data": []map[string]any{{"domain_id": 10, "domain": "example.com"}}})
		case "domain/getView/":
			requireForm(t, r.Form, "domain", "10")
			writeDNSComOK(w, []map[string]any{{"id": 0, "view_name": "默认"}, {"id": 2, "view_name": "联通"}})
		case "record/lists/":
			requireForm(t, r.Form, "domain", "10")
			writeDNSComOK(w, map[string]any{"total": 1, "data": []map[string]any{{"record_id": 20, "record": "www", "type": "A", "value": "1.1.1.1", "view_id": 0, "view_name": "默认"}}})
		case "record/create/":
			requireForm(t, r.Form, "record", "api")
			requireForm(t, r.Form, "type", "A")
			requireForm(t, r.Form, "value", "2.2.2.2")
			requireForm(t, r.Form, "view_id", "2")
			writeDNSComOK(w, map[string]any{"record_id": 21, "record": "api", "type": "A", "value": "2.2.2.2", "view_id": 2, "view_name": "联通"})
		case "record/update/":
			requireForm(t, r.Form, "record_id", "21")
			requireForm(t, r.Form, "record", "cdn")
			writeDNSComOK(w, map[string]any{"record_id": 21, "record": "cdn", "type": "CNAME", "value": "target.example.com", "view_id": 0, "view_name": "默认"})
		case "domain/operate/":
			requireForm(t, r.Form, "record_id", "21")
			requireForm(t, r.Form, "status", "delete")
			writeDNSComOK(w, map[string]any{})
		default:
			t.Fatalf("unexpected action %s", action)
		}
	}))
	defer server.Close()

	provider := &dnscomProvider{client: server.Client(), now: func() time.Time { return time.Unix(1780000000, 0) }}
	if err := provider.Configure(map[string]string{"ApiKey": "api-key", "ApiSecret": "api-secret", "BaseURL": server.URL}); err != nil {
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
	if len(records) != 1 || records[0].RemoteID != "20" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "2"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "21" || created.LineID != "2" || created.Line != "联通" {
		t.Fatalf("unexpected created record: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "21", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "21" || updated.Name != "cdn" || updated.Line != "默认" {
		t.Fatalf("unexpected updated record: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "21"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(actions, ",") != "domain/lists/,domain/lists/,domain/getView/,record/lists/,record/create/,record/update/,domain/operate/" {
		t.Fatalf("unexpected actions: %#v", actions)
	}
}

func TestDNSComProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"code": 1001, "message": "authentication failed"})
	}))
	defer server.Close()

	provider := &dnscomProvider{client: server.Client(), now: func() time.Time { return time.Unix(1, 0) }}
	if err := provider.Configure(map[string]string{"ApiKey": "api-key", "ApiSecret": "api-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected DNS.com error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "api-secret") || strings.Contains(message, "hash") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestDNSComProviderRequiresAuth(t *testing.T) {
	provider := &dnscomProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func writeDNSComOK(w http.ResponseWriter, data any) {
	_ = json.NewEncoder(w).Encode(map[string]any{"code": 0, "message": "ok", "data": data})
}

func dnscomFormToMap(values url.Values) map[string]string {
	out := make(map[string]string, len(values))
	for key := range values {
		if key == "hash" || key == "Signature" {
			continue
		}
		out[key] = values.Get(key)
	}
	return out
}
