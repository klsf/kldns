package providerhttp

import (
	"net/http"
	"testing"
)

func TestNormalizeBaseURL(t *testing.T) {
	tests := []struct {
		name          string
		raw           string
		fallback      string
		trailingSlash bool
		want          string
	}{
		{name: "fallback without slash", fallback: "https://api.example.com/", want: "https://api.example.com"},
		{name: "fallback with slash", fallback: "https://api.example.com", trailingSlash: true, want: "https://api.example.com/"},
		{name: "raw trims spaces", raw: " https://mock.example.com/// ", fallback: "https://api.example.com", want: "https://mock.example.com"},
		{name: "raw with trailing slash", raw: "https://mock.example.com", fallback: "https://api.example.com", trailingSlash: true, want: "https://mock.example.com/"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := NormalizeBaseURL(tt.raw, tt.fallback, tt.trailingSlash)
			if got != tt.want {
				t.Fatalf("NormalizeBaseURL() = %q, want %q", got, tt.want)
			}
		})
	}
}

func TestNewClientUsesDefaultTimeout(t *testing.T) {
	client := NewClient()
	if client == nil {
		t.Fatal("NewClient returned nil")
	}
	if client.Timeout != DefaultHTTPTimeout {
		t.Fatalf("Timeout = %s, want %s", client.Timeout, DefaultHTTPTimeout)
	}
	if _, ok := any(client).(*http.Client); !ok {
		t.Fatal("NewClient should return *http.Client")
	}
}
