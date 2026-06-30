package providers

import (
	"context"
	"crypto/rand"
	"crypto/rsa"
	"crypto/x509"
	"encoding/json"
	"encoding/pem"
	"io"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
	"time"

	"kldns/pkg/dns"
)

func TestGoogleProviderRecordLifecycle(t *testing.T) {
	privateKey := testGooglePrivateKey(t)
	tokenRequests := 0
	var requests []string
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		bodyBytes, _ := io.ReadAll(r.Body)
		requests = append(requests, r.Method+" "+r.URL.Path)
		w.Header().Set("Content-Type", "application/json")
		if r.URL.Path == "/token" {
			tokenRequests++
			values, _ := url.ParseQuery(string(bodyBytes))
			if values.Get("grant_type") != "urn:ietf:params:oauth:grant-type:jwt-bearer" || values.Get("assertion") == "" {
				t.Fatalf("unexpected token form: %s", string(bodyBytes))
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"access_token": "access-token", "expires_in": 3600})
			return
		}
		if got := r.Header.Get("Authorization"); got != "Bearer access-token" {
			t.Fatalf("authorization = %q", got)
		}
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/dns/v1/projects/project-1/managedZones":
			_ = json.NewEncoder(w).Encode(map[string]any{"managedZones": []map[string]any{{"name": "zone-1", "dnsName": "example.com.", "visibility": "public"}}})
		case r.Method == http.MethodGet && r.URL.Path == "/dns/v1/projects/project-1/managedZones/zone-1/rrsets":
			_ = json.NewEncoder(w).Encode(map[string]any{"rrsets": []map[string]any{
				{"name": "www.example.com.", "type": "A", "ttl": 300, "rrdatas": []string{"1.1.1.1"}},
				{"name": "api.example.com.", "type": "A", "ttl": 300, "rrdatas": []string{"2.2.2.2"}},
			}})
		case r.Method == http.MethodPost && r.URL.Path == "/dns/v1/projects/project-1/managedZones/zone-1/changes":
			var payload map[string]any
			_ = json.Unmarshal(bodyBytes, &payload)
			if len(payload) == 0 {
				t.Fatalf("empty change payload")
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"id": "change-1", "status": "pending"})
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.String())
		}
	}))
	defer server.Close()

	serviceAccount := map[string]string{
		"project_id":     "project-1",
		"client_email":   "test@example.iam.gserviceaccount.com",
		"private_key":    privateKey,
		"private_key_id": "key-id",
		"token_uri":      server.URL + "/token",
	}
	serviceAccountJSON, _ := json.Marshal(serviceAccount)
	provider := &googleProvider{client: server.Client(), now: func() time.Time { return time.Unix(1781610000, 0).UTC() }}
	if err := provider.Configure(map[string]string{"ServiceAccountJson": string(serviceAccountJSON), "BaseURL": server.URL + "/dns/v1"}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err != nil {
		t.Fatal(err)
	}
	zones, err := provider.ListZones(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(zones) != 1 || zones[0].ID != "zone-1" || zones[0].Domain != "example.com" {
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
	updated, err := provider.UpdateRecord(context.Background(), zones[0], records[1].RemoteID, dns.RecordInput{Name: "mail", Type: "MX", Value: "mail.example.com"})
	if err != nil {
		t.Fatal(err)
	}
	if updated.Type != "MX" || updated.Value != "mail.example.com" {
		t.Fatalf("unexpected updated: %#v", updated)
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
	if tokenRequests != 1 {
		t.Fatalf("expected cached access token, got %d token requests", tokenRequests)
	}
	if strings.Join(requests, ",") != "POST /token,GET /dns/v1/projects/project-1/managedZones,GET /dns/v1/projects/project-1/managedZones,GET /dns/v1/projects/project-1/managedZones/zone-1/rrsets,POST /dns/v1/projects/project-1/managedZones/zone-1/changes,GET /dns/v1/projects/project-1/managedZones/zone-1/rrsets,POST /dns/v1/projects/project-1/managedZones/zone-1/changes,GET /dns/v1/projects/project-1/managedZones/zone-1/rrsets,GET /dns/v1/projects/project-1/managedZones/zone-1/rrsets,POST /dns/v1/projects/project-1/managedZones/zone-1/changes" {
		t.Fatalf("unexpected requests: %#v", requests)
	}
}

func TestGoogleProviderErrorDoesNotLeakSecret(t *testing.T) {
	privateKey := testGooglePrivateKey(t)
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]any{"error": "invalid_grant", "error_description": "jwt rejected"})
	}))
	defer server.Close()

	serviceAccount := map[string]string{
		"project_id":   "project-1",
		"client_email": "test@example.iam.gserviceaccount.com",
		"private_key":  privateKey,
		"token_uri":    server.URL,
	}
	serviceAccountJSON, _ := json.Marshal(serviceAccount)
	provider := &googleProvider{client: server.Client(), now: func() time.Time { return time.Unix(0, 0).UTC() }}
	if err := provider.Configure(map[string]string{"ServiceAccountJson": string(serviceAccountJSON), "BaseURL": server.URL + "/dns/v1"}); err != nil {
		t.Fatal(err)
	}
	_, err := provider.ListZones(context.Background())
	if err == nil {
		t.Fatal("expected GoogleCloudDns error")
	}
	message := err.Error()
	if !strings.Contains(message, "jwt rejected") {
		t.Fatalf("unexpected error: %s", message)
	}
	if strings.Contains(message, "PRIVATE KEY") || strings.Contains(message, "assertion") || strings.Contains(message, "access-token") || strings.Contains(message, privateKey) {
		t.Fatalf("error leaked secret material: %s", message)
	}
}

func TestGoogleProviderRequiresServiceAccount(t *testing.T) {
	provider := &googleProvider{}
	if err := provider.Configure(map[string]string{}); err != nil {
		t.Fatal(err)
	}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing service account error")
	}
}

func testGooglePrivateKey(t *testing.T) string {
	t.Helper()
	key, err := rsa.GenerateKey(rand.Reader, 1024)
	if err != nil {
		t.Fatal(err)
	}
	block := &pem.Block{Type: "PRIVATE KEY", Bytes: x509.MarshalPKCS1PrivateKey(key)}
	return string(pem.EncodeToMemory(block))
}
