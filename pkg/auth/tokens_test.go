package auth

import "testing"

func TestNewAPITokenStoresOnlyHashAndHint(t *testing.T) {
	plain, hash, hint, err := NewAPIToken()
	if err != nil {
		t.Fatal(err)
	}
	if plain == "" || hash == "" || hint == "" {
		t.Fatalf("empty token fields: plain=%q hash=%q hint=%q", plain, hash, hint)
	}
	if plain == hash || plain == hint {
		t.Fatal("hash and hint must not expose the full plain token")
	}
	if HashBearerToken(plain) != hash {
		t.Fatal("token hash is not stable")
	}
	if len(hint) < 30 {
		t.Fatalf("token hint too short: %q", hint)
	}
}
