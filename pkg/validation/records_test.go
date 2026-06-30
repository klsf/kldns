package validation

import "testing"

func TestValidateRecordValue(t *testing.T) {
	tests := []struct {
		name       string
		recordType string
		value      string
		wantOK     bool
	}{
		{name: "ipv4 ok", recordType: "A", value: "1.1.1.1", wantOK: true},
		{name: "ipv4 rejects ipv6", recordType: "A", value: "2400:3200::1", wantOK: false},
		{name: "ipv6 ok", recordType: "AAAA", value: "2400:3200::1", wantOK: true},
		{name: "cname ok", recordType: "CNAME", value: "target.example.com", wantOK: true},
		{name: "cname rejects ip", recordType: "CNAME", value: "1.1.1.1", wantOK: false},
		{name: "srv ok", recordType: "SRV", value: "10 5 443 srv.example.com", wantOK: true},
		{name: "srv rejects port", recordType: "SRV", value: "10 5 70000 srv.example.com", wantOK: false},
		{name: "caa ok", recordType: "CAA", value: "0 issue letsencrypt.org", wantOK: true},
		{name: "caa rejects tag", recordType: "CAA", value: "0 nope letsencrypt.org", wantOK: false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			_, gotOK := ValidateRecordValue(tt.recordType, tt.value)
			if gotOK != tt.wantOK {
				t.Fatalf("ValidateRecordValue() ok = %v, want %v", gotOK, tt.wantOK)
			}
		})
	}
}

func TestValidateRecordPrefix(t *testing.T) {
	if _, _, ok := ValidateRecordPrefix("WWW", []string{"www"}); ok {
		t.Fatal("reserved prefix should be rejected case-insensitively")
	}
	if got, _, ok := ValidateRecordPrefix("Demo_1", nil); !ok || got != "demo_1" {
		t.Fatalf("prefix normalization failed: got=%q ok=%v", got, ok)
	}
	if got, _, ok := ValidateRecordPrefix("@", nil); !ok || got != "@" {
		t.Fatalf("root prefix should be accepted: got=%q ok=%v", got, ok)
	}
	if _, _, ok := ValidateRecordPrefix("@", []string{"@"}); ok {
		t.Fatal("reserved root prefix should be rejected")
	}
}

func TestIsValidEmail(t *testing.T) {
	if !IsValidEmail("user@example.com") {
		t.Fatal("valid email rejected")
	}
	if IsValidEmail("not-an-email") {
		t.Fatal("invalid email accepted")
	}
}
