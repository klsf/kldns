package auth

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"strings"
)

func NewAPIToken() (plain string, hash string, hint string, err error) {
	raw := make([]byte, 32)
	if _, err := rand.Read(raw); err != nil {
		return "", "", "", err
	}
	plain = "kldns_" + hex.EncodeToString(raw)
	hash = HashBearerToken(plain)
	hint = TokenHint(plain)
	return plain, hash, hint, nil
}

func HashBearerToken(plain string) string {
	sum := sha256.Sum256([]byte(strings.TrimSpace(plain)))
	return hex.EncodeToString(sum[:])
}

func TokenHint(plain string) string {
	plain = strings.TrimSpace(plain)
	if len(plain) <= 36 {
		return plain
	}
	return plain[:18] + "..." + plain[len(plain)-12:]
}
