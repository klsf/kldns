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

func TestAliyunProviderRecordLifecycle(t *testing.T) {
	var actions []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("method = %s, want POST", r.Method)
		}
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		requireForm(t, r.Form, "AccessKeyId", "akid")
		requireForm(t, r.Form, "SignatureMethod", "HMAC-SHA1")
		requireForm(t, r.Form, "SignatureVersion", "1.0")
		requireForm(t, r.Form, "Timestamp", "2026-06-16T10:20:30Z")
		requireForm(t, r.Form, "SignatureNonce", "fixed-nonce")
		if got := r.Form.Get("Signature"); got == "" || got != aliyunSignature("POST", formToMap(r.Form), "secret") {
			t.Fatalf("invalid signature: %q", got)
		}
		action := r.Form.Get("Action")
		actions = append(actions, action)
		w.Header().Set("Content-Type", "application/json")
		switch action {
		case "DescribeDomains":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"Domains": map[string]any{"Domain": []map[string]any{{"DomainId": "domain-id", "DomainName": "example.com"}}},
			})
		case "DescribeDomainRecords":
			requireForm(t, r.Form, "DomainName", "example.com")
			_ = json.NewEncoder(w).Encode(map[string]any{
				"DomainRecords": map[string]any{"Record": []map[string]any{{"RecordId": "record1", "RR": "www", "Type": "A", "Value": "1.1.1.1", "Line": "default"}}},
			})
		case "AddDomainRecord":
			requireForm(t, r.Form, "DomainName", "example.com")
			requireForm(t, r.Form, "RR", "api")
			requireForm(t, r.Form, "Type", "A")
			requireForm(t, r.Form, "Value", "2.2.2.2")
			requireForm(t, r.Form, "Line", "unicom")
			_ = json.NewEncoder(w).Encode(map[string]any{"RecordId": "record2"})
		case "UpdateDomainRecord":
			requireForm(t, r.Form, "RecordId", "record2")
			requireForm(t, r.Form, "RR", "cdn")
			_ = json.NewEncoder(w).Encode(map[string]any{"RecordId": "record2"})
		case "DeleteDomainRecord":
			requireForm(t, r.Form, "RecordId", "record2")
			_ = json.NewEncoder(w).Encode(map[string]any{"RecordId": "record2"})
		default:
			t.Fatalf("unexpected action %s", action)
		}
	}))
	defer server.Close()

	provider := &aliyunProvider{
		client: server.Client(),
		now:    func() time.Time { return time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC) },
		nonce:  func() string { return "fixed-nonce" },
	}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "AccessKeySecret": "secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "domain-id" || zones[0].Domain != "example.com" {
		t.Fatalf("unexpected zones: %#v", zones)
	}
	lines, err := provider.ListRecordLines(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(lines) < 4 || lines[0].ID != "default" {
		t.Fatalf("unexpected lines: %#v", lines)
	}
	records, err := provider.ListRecords(context.Background(), zones[0])
	if err != nil {
		t.Fatal(err)
	}
	if len(records) != 1 || records[0].RemoteID != "record1" || records[0].Name != "www" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2", LineID: "unicom"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID != "record2" || created.LineID != "unicom" || created.Line != "联通" {
		t.Fatalf("unexpected created record: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], "record2", dns.RecordInput{Name: "cdn", Type: "CNAME", Value: "target.example.com", LineID: "default"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.RemoteID != "record2" || updated.Name != "cdn" || updated.Line != "默认" {
		t.Fatalf("unexpected updated record: %#v", updated)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], "record2"); err != nil {
		t.Fatal(err)
	}
	if strings.Join(actions, ",") != "DescribeDomains,DescribeDomains,DescribeDomainRecords,AddDomainRecord,UpdateDomainRecord,DeleteDomainRecord" {
		t.Fatalf("unexpected actions: %#v", actions)
	}
}

func TestAliyunProviderNormalizesDefaultLineForCreate(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		requireForm(t, r.Form, "Action", "AddDomainRecord")
		requireForm(t, r.Form, "DomainName", "klsf.cc")
		requireForm(t, r.Form, "RR", "aa.test")
		requireForm(t, r.Form, "Line", "default")
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"RecordId": "record-default"})
	}))
	defer server.Close()

	provider := &aliyunProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }, nonce: func() string { return "n" }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "AccessKeySecret": "secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	record, err := provider.CreateRecord(context.Background(), dns.Zone{Domain: "klsf.cc"}, dns.RecordInput{Name: "aa.test", Type: "A", Value: "1.1.1.1", LineID: "0"})
	if err != nil {
		t.Fatal(err)
	}
	if record.Name != "aa.test" || record.LineID != "default" || record.Line != "默认" {
		t.Fatalf("unexpected record: %#v", record)
	}
}

func TestAliyunProviderCreatesEverySupportedRecordType(t *testing.T) {
	cases := []struct {
		recordType string
		value      string
		priority   string
	}{
		{recordType: "A", value: "192.0.2.10"},
		{recordType: "AAAA", value: "2001:db8::10"},
		{recordType: "CNAME", value: "target.example.com"},
		{recordType: "MX", value: "mail.example.com", priority: "10"},
		{recordType: "TXT", value: "v=spf1 -all"},
		{recordType: "NS", value: "ns1.example.com"},
		{recordType: "SRV", value: "10 5 443 srv.example.com"},
		{recordType: "CAA", value: "0 issue letsencrypt.org"},
	}
	requests := 0
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if err := r.ParseForm(); err != nil {
			t.Fatal(err)
		}
		if requests >= len(cases) {
			t.Fatalf("unexpected extra request: %s", r.Form.Encode())
		}
		item := cases[requests]
		requests++

		requireForm(t, r.Form, "Action", "AddDomainRecord")
		requireForm(t, r.Form, "DomainName", "klsf.cc")
		requireForm(t, r.Form, "RR", "test-"+strings.ToLower(item.recordType))
		requireForm(t, r.Form, "Type", item.recordType)
		requireForm(t, r.Form, "Value", item.value)
		requireForm(t, r.Form, "Line", "default")
		if item.priority != "" {
			requireForm(t, r.Form, "Priority", item.priority)
		} else {
			requireFormMissing(t, r.Form, "Priority")
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"RecordId": "record-" + strings.ToLower(item.recordType)})
	}))
	defer server.Close()

	provider := &aliyunProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }, nonce: func() string { return "n" }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "AccessKeySecret": "secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	for _, item := range cases {
		record, err := provider.CreateRecord(context.Background(), dns.Zone{Domain: "klsf.cc"}, dns.RecordInput{
			Name:   "test-" + strings.ToLower(item.recordType),
			Type:   item.recordType,
			Value:  item.value,
			LineID: "0",
		})
		if err != nil {
			t.Fatalf("%s create failed: %v", item.recordType, err)
		}
		if record.Type != item.recordType || record.LineID != "default" {
			t.Fatalf("%s unexpected created record: %#v", item.recordType, record)
		}
	}
	if requests != len(cases) {
		t.Fatalf("requests = %d, want %d", requests, len(cases))
	}
}

func TestAliyunProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{"Code": "InvalidAccessKeyId.NotFound", "Message": "authentication failed"})
	}))
	defer server.Close()

	provider := &aliyunProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }, nonce: func() string { return "n" }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "AccessKeySecret": "very-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected Aliyun error")
	}
	message := err.Error()
	if !strings.Contains(message, "authentication failed") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "very-secret") || strings.Contains(message, "Signature") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestAliyunProviderRequiresAuth(t *testing.T) {
	provider := &aliyunProvider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}

func requireFormMissing(t *testing.T, values url.Values, key string) {
	t.Helper()
	if _, ok := values[key]; ok {
		t.Fatalf("form %s exists: %q", key, values.Get(key))
	}
}

func formToMap(values url.Values) map[string]string {
	out := make(map[string]string, len(values))
	for key := range values {
		if key == "Signature" {
			continue
		}
		out[key] = values.Get(key)
	}
	return out
}
