package secrets

import "testing"

func TestEncryptDecrypt(t *testing.T) {
	ciphertext, err := Encrypt("secret", `{"ApiToken":"hidden"}`)
	if err != nil {
		t.Fatal(err)
	}
	if !IsEncrypted(ciphertext) {
		t.Fatalf("ciphertext is not marked encrypted: %s", ciphertext)
	}
	if ciphertext == `{"ApiToken":"hidden"}` {
		t.Fatal("ciphertext must not equal plaintext")
	}
	plaintext, err := Decrypt("secret", ciphertext)
	if err != nil {
		t.Fatal(err)
	}
	if plaintext != `{"ApiToken":"hidden"}` {
		t.Fatalf("plaintext = %s", plaintext)
	}
}
