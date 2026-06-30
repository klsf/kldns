package providers

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"

	"kldns/pkg/dns"
)

func TestDNSPodProviderRecordLifecycle(t *testing.T) {
	var actions []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("method = %s, want POST", r.Method)
		}
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		if got := r.Form.Get("login_token"); got != "123,secret-token" {
			t.Fatalf("login_token = %q", got)
		}
		action := strings.TrimPrefix(r.URL.Path, "/")
		actions = append(actions, action)
		w.Header().Set("Content-Type", "application/json")
		switch action {
		case "Info.Version":
			writeDNSPodOK(w, map[string]any{"version": "1.0"})
		case "Domain.List":
			writeDNSPodOK(w, map[string]any{"domains": []map[string]any{{"id": 100, "name": "example.com"}}})
		case "Record.List":
			requireForm(t, r.Form, "domain_id", "100")
			writeDNSPodOK(w, map[string]any{
				"domain":  map[string]any{"id": 100, "name": "example.com"},
				"records": []map[string]any{{"id": 200, "name": "www", "type": "A", "value": "1.1.1.1", "record_line_id": "0"}},
			})
		case "Record.Create":
			requireForm(t, r.Form, "sub_domain", "api")
			requireForm(t, r.Form, "record_type", "A")
			requireForm(t, r.Form, "value", "2.2.2.2")
			requireForm(t, r.Form, "record_line_id", "10=1")
			writeDNSPodOK(w, map[string]any{"record": map[string]any{"id": 201, "name": "api"}})
		case "Record.Modify":
			requireForm(t, r.Form, "record_id", "201")
			requireForm(t, r.Form, "sub_domain", "cdn")
			writeDNSPodOK(w, map[string]any{"record": map[string]any{"id": 201, "name": "cdn", "record_type": "CNAME", "value": "target.example.com", "record_line_id": "7=0"}})
		case "Record.Remove":
			requireForm(t, r.Form, "record_id", "201")
			writeDNSPodOK(w, map[string]any{})
		default:
			t.Fatalf("unexpected action %s", action)
		}
	}))
	defer server.Close()

	provider := &dnspodProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ID": "123", "Token": "secret-token", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "100" || zones[0].Domain != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) < 2 || lines[0].ID != "0" {
		t.Fatalf("unexpected record lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "200" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "10=1"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "201" || created.Name != "api" || created.LineID != "10=1" || created.Line != "联通" {
		t.Fatalf("unexpected created record: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "201", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "7=0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.Name != "cdn" || updated.Type != "CNAME" || updated.Line != "国内" {
		t.Fatalf("unexpected updated record: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "201"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(actions, ",") != "Info.Version,Domain.List,Record.List,Record.Create,Record.Modify,Record.Remove" {
		t.Fatalf("unexpected actions: %#v", actions)
	}
}

func TestDNSPodProviderErrorDoesNotLeakToken(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"status": map[string]any{"code": "10", "message": "authentication failed"},
		})
	}))
	defer server.Close()

	provider := &dnspodProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"ID": "123", "Token": "secret-token", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected DNSPod error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "secret-token") || strings.Contains(message, "login_token") {
		t.Fatalf("error leaked token: %s", message)
	}
}

func TestDNSPodProviderRequiresAuth(t *testing.T) {
	provider := &dnspodProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func writeDNSPodOK(w http.ResponseWriter, payload map[string]any) {
	payload["status"] = map[string]any{"code": "1", "message": "Action completed successful"}
	_ = json.NewEncoder(w).Encode(payload)
}

func requireForm(t *testing.T, values url.Values, key string, want string) {
	t.Helper()
	if got := values.Get(key); got != want {
		t.Fatalf("form %s = %q, want %q", key, got, want)
	}
}
