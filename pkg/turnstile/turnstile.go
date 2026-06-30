package turnstile

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const siteVerifyURL = "https://challenges.cloudflare.com/turnstile/v0/siteverify"

type Client struct {
	HTTPClient *http.Client
}

type verifyResponse struct {
	Success    bool     `json:"success"`
	ErrorCodes []string `json:"error-codes"`
}

func (c Client) Verify(ctx context.Context, secret string, token string, remoteIP string) error {
	secret = strings.TrimSpace(secret)
	token = strings.TrimSpace(token)
	if secret == "" {
		return fmt.Errorf("turnstile secret is not configured")
	}
	if token == "" {
		return fmt.Errorf("turnstile token is required")
	}
	form := url.Values{}
	form.Set("secret", secret)
	form.Set("response", token)
	if strings.TrimSpace(remoteIP) != "" {
		form.Set("remoteip", strings.TrimSpace(remoteIP))
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, siteVerifyURL, strings.NewReader(form.Encode()))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	client := c.HTTPClient
	if client == nil {
		client = &http.Client{Timeout: 8 * time.Second}
	}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("turnstile siteverify status %d", resp.StatusCode)
	}
	var payload verifyResponse
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		return err
	}
	if !payload.Success {
		if len(payload.ErrorCodes) > 0 {
			return fmt.Errorf("turnstile validation failed: %s", strings.Join(payload.ErrorCodes, ","))
		}
		return fmt.Errorf("turnstile validation failed")
	}
	return nil
}
