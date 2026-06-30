package providers

import (
	"context"
	"testing"

	"kldns/pkg/dns"
)

func TestAllLegacyProvidersAreRegistered(t *testing.T) {
	want := []string{
		"Dnspod", "Aliyun", "DnsCom", "DnsLa", "DnsDun", "West",
		"HuaweiCloud", "BaiduCloud", "Route53", "GoogleCloudDns", "Cloudflare",
	}
	for _, key := range want {
		t.Run(key, func(t *testing.T) {
			provider, ok := dns.New(key)
			if !ok {
				t.Fatalf("provider %s is not registered", key)
			}
			if provider.Label() == "" || len(provider.ConfigFields()) == 0 {
				t.Fatalf("provider %s has incomplete metadata", key)
			}
		})
	}
}

func TestProviderCheckRequiresConfiguredSecrets(t *testing.T) {
	provider, ok := dns.New("GoogleCloudDns")
	if !ok {
		t.Fatal("GoogleCloudDns provider not registered")
	}
	if err := provider.Check(context.Background()); err == nil {
		t.Fatal("expected missing config error")
	}
}
