package providerhttp

import (
	"net/http"
	"strings"
	"time"
)

const DefaultHTTPTimeout = 30 * time.Second

func NewClient() *http.Client {
	return &http.Client{Timeout: DefaultHTTPTimeout}
}

func NormalizeBaseURL(raw string, fallback string, trailingSlash bool) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		raw = fallback
	}
	raw = strings.TrimRight(raw, "/")
	if trailingSlash {
		return raw + "/"
	}
	return raw
}

func JoinBaseURL(baseURL string, path string) string {
	return strings.TrimRight(baseURL, "/") + "/" + strings.TrimLeft(path, "/")
}
