package auth

import "testing"

func TestPasswordHashAndCheck(t *testing.T) {
	hash, err := HashPassword("correct horse battery staple")
	if err != nil {
		t.Fatal(err)
	}
	if hash == "correct horse battery staple" {
		t.Fatal("password hash must not equal plaintext")
	}
	if !CheckPassword("correct horse battery staple", hash) {
		t.Fatal("expected password to verify")
	}
	if CheckPassword("wrong", hash) {
		t.Fatal("wrong password verified")
	}
}
