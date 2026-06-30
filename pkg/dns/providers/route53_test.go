package providers

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"kldns/pkg/dns"
)

func TestRoute53ProviderRecordLifecycle(t *testing.T) {
	var requests []string
	var changeBodies []string
	fixedTime := time.Date(2026, 6, 16, 10, 20, 30, 0, time.UTC)
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		bodyBytes, _ := io.ReadAll(r.Body)
		body := string(bodyBytes)
		query := map[string]string{}
		for key := range r.URL.Query() {
			query[key] = r.URL.Query().Get(key)
		}
		expectedAuth := route53Authorization(r.Method, r.URL.Path, route53CanonicalQuery(query), body, r.Host, "20260616T102030Z", "20260616", "akid", "secret", "session-token")
		if got := r.Header.Get("Authorization"); got != expectedAuth {
			t.Fatalf("authorization mismatch\n got: %s\nwant: %s", got, expectedAuth)
		}
		if got := r.Header.Get("X-Amz-Security-Token"); got != "session-token" {
			t.Fatalf("session token = %q", got)
		}
		requests = append(requests, r.Method+" "+r.URL.Path)
		w.Header().Set("Content-Type", "application/xml")
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/2013-04-01/hostedzone":
			if r.URL.Query().Get("maxitems") != "100" {
				t.Fatalf("unexpected zone query: %s", r.URL.RawQuery)
			}
			_, _ = w.Write([]byte(`<ListHostedZonesResponse><HostedZones><HostedZone><Id>/hostedzone/Z1</Id><Name>example.com.</Name><Config><PrivateZone>false</PrivateZone></Config></HostedZone></HostedZones><IsTruncated>false</IsTruncated></ListHostedZonesResponse>`))
		case r.Method == http.MethodGet && r.URL.Path == "/2013-04-01/hostedzone/Z1/rrset":
			_, _ = w.Write([]byte(`<ListResourceRecordSetsResponse><ResourceRecordSets><ResourceRecordSet><Name>www.example.com.</Name><Type>A</Type><TTL>300</TTL><ResourceRecords><ResourceRecord><Value>1.1.1.1</Value></ResourceRecord></ResourceRecords></ResourceRecordSet><ResourceRecordSet><Name>api.example.com.</Name><Type>A</Type><TTL>300</TTL><ResourceRecords><ResourceRecord><Value>2.2.2.2</Value></ResourceRecord></ResourceRecords></ResourceRecordSet></ResourceRecordSets><IsTruncated>false</IsTruncated></ListResourceRecordSetsResponse>`))
		case r.Method == http.MethodPost && r.URL.Path == "/2013-04-01/hostedzone/Z1/rrset":
			changeBodies = append(changeBodies, body)
			if !strings.Contains(body, "<Action>CREATE</Action>") && !strings.Contains(body, "<Action>UPSERT</Action>") && !strings.Contains(body, "<Action>DELETE</Action>") {
				t.Fatalf("unexpected change body: %s", body)
			}
			if strings.Contains(body, "<Action>CREATE</Action>") && !strings.Contains(body, "<Name>api.example.com.</Name>") {
				t.Fatalf("unexpected create body: %s", body)
			}
			_, _ = w.Write([]byte(`<ChangeResourceRecordSetsResponse><ChangeInfo><Id>/change/C1</Id><Status>PENDING</Status></ChangeInfo></ChangeResourceRecordSetsResponse>`))
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	provider := &route53Provider{client: server.Client(), now: func() time.Time { return fixedTime }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "secret", "SessionToken": "session-token", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "Z1" || zones[0].Domain != "example.com" {
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
	if len(records) != 2 || records[0].Name != "www" || records[0].Value != "1.1.1.1" {
		t.Fatalf("unexpected records: %#v", records)
	}
	created, err := provider.CreateRecord(context.Background(), zones[0], dns.RecordInput{Name: "api", Type: "A", Value: "2.2.2.2"})
	if err != nil {
		t.Fatal(err)
	}
	if created.RemoteID == "" || created.Name != "api" {
		t.Fatalf("unexpected created: %#v", created)
	}
	updated, err := provider.UpdateRecord(context.Background(), zones[0], created.RemoteID, dns.RecordInput{Name: "mail", Type: "MX", Value: "mail.example.com"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.Type != "MX" || updated.Value != "mail.example.com" {
		t.Fatalf("unexpected updated: %#v", updated)
	}
	if len(changeBodies) < 2 {
		t.Fatalf("expected create and update change bodies, got %d", len(changeBodies))
	}
	updateBody := changeBodies[1]
	for _, want := range []string{
		"<Action>DELETE</Action>",
		"<Name>api.example.com.</Name>",
		"<Type>A</Type>",
		"<Action>UPSERT</Action>",
		"<Name>mail.example.com.</Name>",
		"<Type>MX</Type>",
	} {
		if !strings.Contains(updateBody, want) {
			t.Fatalf("update body missing %s: %s", want, updateBody)
		}
	}
	got, err := provider.GetRecord(context.Background(), zones[0], records[1].RemoteID)
	if err != nil {
		t.Fatal(err)
	}
	if got.Name != "api" || got.Type != "A" {
		t.Fatalf("unexpected record info: %#v", got)
	}
	if err := provider.DeleteRecord(context.Background(), zones[0], records[1].RemoteID); err != nil {
		t.Fatal(err)
	}
	if strings.Join(requests, ",") != "GET /2013-04-01/hostedzone,GET /2013-04-01/hostedzone,GET /2013-04-01/hostedzone/Z1/rrset,POST /2013-04-01/hostedzone/Z1/rrset,GET /2013-04-01/hostedzone/Z1/rrset,POST /2013-04-01/hostedzone/Z1/rrset,GET /2013-04-01/hostedzone/Z1/rrset,GET /2013-04-01/hostedzone/Z1/rrset,POST /2013-04-01/hostedzone/Z1/rrset" {
		t.Fatalf("unexpected requests: %#v", requests)
	}
}

func TestRoute53ProviderErrorDoesNotLeakSecret(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/xml")
		w.WriteHeader(http.StatusForbidden)
		_, _ = w.Write([]byte(`<ErrorResponse><Error><Message>signature mismatch</Message></Error></ErrorResponse>`))
	}))
	defer server.Close()

	provider := &route53Provider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }}
	if err := provider.Configure(map[string]string{"AccessKeyId": "akid", "SecretAccessKey": "very-secret", "SessionToken": "session-secret", "BaseURL": server.URL}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected Route53 error")
	}
	message := err.Error()
	if !strings.Contains(message, "signature mismatch") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "very-secret") || strings.Contains(message, "session-secret") || strings.Contains(message, "Authorization") || strings.Contains(message, "Signature") {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestRoute53ProviderRequiresAuth(t *testing.T) {
	provider := &route53Provider{}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing auth error")
	}
}
