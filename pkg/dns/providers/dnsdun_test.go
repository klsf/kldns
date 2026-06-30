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

func TestDnsDunProviderRecordLifecycle(t *testing.T) {
	var actions []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("method = %s, want POST", r.Method)
		}
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		requireForm(t, r.Form, "uid", "uid1")
		requireForm(t, r.Form, "api_key", "api-secret")
		requireForm(t, r.Form, "format", "json")
		action := "c=" + r.URL.Query().Get("c") + "&a=" + r.URL.Query().Get("a")
		actions = append(actions, action)
		w.Header().Set("Content-Type", "application/json")
		switch action {
		case "c=domain&a=getList":
			requireForm(t, r.Form, "length", "100")
			writeDnsDunOK(w, map[string]any{"domains": []map[string]any{{"domain": "example.com"}}})
		case "c=record&a=list":
			requireForm(t, r.Form, "domain", "example.com")
			writeDnsDunOK(w, map[string]any{"records": []map[string]any{{"id": 20, "name": "www", "type": "A", "value": "1.1.1.1", "record_line": "默认"}}})
		case "c=record&a=add":
			requireForm(t, r.Form, "sub_domain", "api")
			requireForm(t, r.Form, "record_type", "A")
			requireForm(t, r.Form, "record_line", "联通")
			writeDnsDunOK(w, map[string]any{"record": map[string]any{"id": 21, "sub_domain": "api", "record_type": "A", "value": "2.2.2.2", "record_line": "联通"}})
		case "c=record&a=modify":
			requireForm(t, r.Form, "record_id", "21")
			requireForm(t, r.Form, "sub_domain", "cdn")
			writeDnsDunOK(w, map[string]any{"record": map[string]any{"id": 21, "sub_domain": "cdn", "record_type": "CNAME", "value": "target.example.com", "record_line": "默认"}})
		case "c=record&a=info":
			requireForm(t, r.Form, "record_id", "21")
			writeDnsDunOK(w, map[string]any{"record": map[string]any{"id": 21, "sub_domain": "cdn", "record_type": "CNAME", "value": "target.example.com", "record_line": "默认"}})
		case "c=record&a=del":
			requireForm(t, r.Form, "record_id", "21")
			writeDnsDunOK(w, map[string]any{})
		default:
			t.Fatalf("unexpected action %s", action)
		}
	}))
	defer server.Close()

	provider := &dnsdunProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"UID": "uid1", "API_KEY": "api-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "example.com" || zones[0].Domain != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) != 6 || lines[3].ID != "联通" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "20" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "联通"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "21" || created.Name != "api" || created.LineID != "联通" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "21", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "21" || updated.Name != "cdn" || updated.LineID != "默认" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	got, err := provider.GetRecord(context.Background(), zones[0], "21")
	if err != nil {
		t.Fatal(err)
	}
	if got.RemoteID != "21" || got.Type != "CNAME" {
		t.Fatalf("unexpected info record: %#v", got)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "21"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(actions, ",") != "c=domain&a=getList,c=domain&a=getList,c=record&a=list,c=record&a=add,c=record&a=modify,c=record&a=info,c=record&a=del" {
		t.Fatalf("unexpected actions: %#v", actions)
	}
}

func TestDnsDunProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"status": map[string]any{"code": "10", "message": "authentication failed"}})
	}))
	defer server.Close()

	provider := &dnsdunProvider{client: server.Client()}
	if err := provider.Configure(map[string]string{"UID": "uid1", "API_KEY": "api-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected DnsDun error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "api-secret") || strings.Contains(message, "api_key") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestDnsDunProviderRequiresAuth(t *testing.T) {
	provider := &dnsdunProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func writeDnsDunOK(w http.ResponseWriter, extra map[string]any) {
	extra["status"] = map[string]any{"code": "1", "message": "ok"}
	_ = json.NewEncoder(w).Encode(extra)
}
