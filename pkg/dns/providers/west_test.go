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

func TestWestProviderRecordLifecycle(t *testing.T) {
	var actions []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var values map[string]string
		if r.Method == http.MethodGet {
			values = valuesFromQuery(r)
		} else {
			if err := r.ParseForm(); err != nil {
				t.Fatal(err)
			}
			values = valuesFromForm(r)
		}
		if values["username"] != "west-user" {
			t.Fatalf("username = %q", values["username"])
		}
		if values["time"] != "1780000000123" {
			t.Fatalf("time = %q", values["time"])
		}
		if values["token"] != westToken("west-user", "api-password", "1780000000123") {
			t.Fatalf("invalid token: %q", values["token"])
		}
		action := values["act"]
		actions = append(actions, r.Method+" "+action)
		w.Header().Set("Content-Type", "application/json")
		switch action {
		case "getdomains":
			if r.Method != http.MethodGet {
				t.Fatalf("getdomains method = %s", r.Method)
			}
			writeWestOK(w, map[string]any{"items": []map[string]any{{"domain": "example.com"}}})
		case "getdnsrecord":
			requireValue(t, values, "domain", "example.com")
			writeWestOK(w, map[string]any{"items": []map[string]any{{"id": 20, "item": "www", "type": "A", "value": "1.1.1.1", "line": ""}}})
		case "adddnsrecord":
			requireValue(t, values, "domain", "example.com")
			requireValue(t, values, "host", "api")
			requireValue(t, values, "type", "A")
			requireValue(t, values, "line", "LCNC")
			writeWestOK(w, map[string]any{"id": 21})
		case "moddnsrecord":
			requireValue(t, values, "id", "21")
			requireValue(t, values, "host", "cdn")
			requireValue(t, values, "line", "")
			writeWestOK(w, map[string]any{})
		case "deldnsrecord":
			requireValue(t, values, "id", "21")
			writeWestOK(w, map[string]any{})
		default:
			t.Fatalf("unexpected action %s", action)
		}
	}))
	defer server.Close()

	provider := &westProvider{client: server.Client(), nowMS: func() int64 { return 1780000000123 }}
	if err := provider.Configure(map[string]string{"Username": "west-user", "ApiPassword": "api-password", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) != 6 || lines[2].ID != "LCNC" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "20" || records[0].Line != "默认" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "LCNC"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "21" || created.Name != "api" || created.Line != "联通" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "21", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "21" || updated.Name != "cdn" || updated.Line != "默认" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "21"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(actions, ",") != "GET getdomains,GET getdomains,POST getdnsrecord,POST adddnsrecord,POST moddnsrecord,POST deldnsrecord" {
		t.Fatalf("unexpected actions: %#v", actions)
	}
}

func TestWestProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"result": 401, "msg": "authentication failed"})
	}))
	defer server.Close()

	provider := &westProvider{client: server.Client(), nowMS: func() int64 { return 1 }}
	if err := provider.Configure(map[string]string{"Username": "west-user", "ApiPassword": "api-password", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected West error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "api-password") || strings.Contains(message, "token") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestWestProviderRequiresAuth(t *testing.T) {
	provider := &westProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func writeWestOK(w http.ResponseWriter, data map[string]any) {
	_ = json.NewEncoder(w).Encode(map[string]any{"result": 200, "msg": "ok", "data": data})
}

func valuesFromQuery(r *http.Request) map[string]string {
	out := map[string]string{}
	for key := range r.URL.Query() {
		out[key] = r.URL.Query().Get(key)
	}
	return out
}

func valuesFromForm(r *http.Request) map[string]string {
	out := map[string]string{}
	for key := range r.Form {
		out[key] = r.Form.Get(key)
	}
	return out
}

func requireValue(t *testing.T, values map[string]string, key string, want string) {
	t.Helper()
	if got := values[key]; got != want {
		t.Fatalf("%s = %q, want %q", key, got, want)
	}
}
