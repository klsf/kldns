package secrets

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"fmt"
	"io"
	"strings"
)

const prefix = "kldns:v1:"

func Encrypt(secret string, plaintext string) (string, error) {
	block, err := aes.NewCipher(key(secret))
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return "", err
	}
	ciphertext := gcm.Seal(nil, nonce, []byte(plaintext), nil)
	payload := append(nonce, ciphertext...)
	return prefix + base64.StdEncoding.EncodeToString(payload), nil
}

func Decrypt(secret string, ciphertext string) (string, error) {
	if strings.HasPrefix(strings.TrimSpace(ciphertext), "{") {
		return ciphertext, nil
	}
	if !strings.HasPrefix(ciphertext, prefix) {
		return "", fmt.Errorf("unsupported ciphertext format")
	}
	payload, err := base64.StdEncoding.DecodeString(strings.TrimPrefix(ciphertext, prefix))
	if err != nil {
		return "", err
	}
	block, err := aes.NewCipher(key(secret))
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	if len(payload) < gcm.NonceSize() {
		return "", fmt.Errorf("ciphertext too short")
	}
	nonce := payload[:gcm.NonceSize()]
	data := payload[gcm.NonceSize():]
	plaintext, err := gcm.Open(nil, nonce, data, nil)
	if err != nil {
		return "", err
	}
	return string(plaintext), nil
}

func IsEncrypted(value string) bool {
	return strings.HasPrefix(value, prefix)
}

func key(secret string) []byte {
	sum := sha256.Sum256([]byte(secret))
	return sum[:]
}
