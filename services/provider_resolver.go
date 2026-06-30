package services

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"kldns/models"
	"kldns/pkg/dns"
	"kldns/pkg/secrets"
)

type DBProviderResolver struct {
	SecretKey string
}

func (r DBProviderResolver) Resolve(ctx context.Context, domain models.Domain) (dns.Provider, error) {
	provider, ok := dns.New(domain.ProviderKey)
	if !ok {
		return nil, fmt.Errorf("unsupported provider %s", domain.ProviderKey)
	}
	rawConfig := strings.TrimSpace(domain.ProviderConfigCiphertext)
	if rawConfig == "" {
		return nil, fmt.Errorf("domain %s has no provider config", domain.Domain)
	}
	secret := strings.TrimSpace(r.SecretKey)
	if secret == "" {
		secret = "change-me-before-production-kldns-secret"
	}
	rawConfig, err := secrets.Decrypt(secret, rawConfig)
	if err != nil {
		return nil, fmt.Errorf("decrypt provider config: %w", err)
	}
	var config map[string]string
	if err := json.Unmarshal([]byte(rawConfig), &config); err != nil {
		return nil, fmt.Errorf("decode provider config: %w", err)
	}
	if err := provider.Configure(config); err != nil {
		return nil, err
	}
	return provider, nil
}
