package errors

import "fmt"

type Code string

const (
	CodeOK                 Code = "OK"
	CodeInvalidArgument    Code = "INVALID_ARGUMENT"
	CodeUnauthorized       Code = "UNAUTHORIZED"
	CodeForbidden          Code = "FORBIDDEN"
	CodeNotFound           Code = "NOT_FOUND"
	CodeConflict           Code = "CONFLICT"
	CodeInsufficientPoints Code = "INSUFFICIENT_POINTS"
	CodeDNSProviderFailed  Code = "DNS_PROVIDER_FAILED"
	CodeDatabaseConflict   Code = "DATABASE_CONFLICT"
	CodeDatabaseTimeout    Code = "DATABASE_TIMEOUT"
	CodeInternal           Code = "INTERNAL"
)

type AppError struct {
	Code    Code
	Message string
	Cause   error
}

func (e *AppError) Error() string {
	if e == nil {
		return ""
	}
	if e.Cause == nil {
		return fmt.Sprintf("%s: %s", e.Code, e.Message)
	}
	return fmt.Sprintf("%s: %s: %v", e.Code, e.Message, e.Cause)
}

func New(code Code, message string) *AppError {
	return &AppError{Code: code, Message: message}
}

func Wrap(code Code, message string, cause error) *AppError {
	return &AppError{Code: code, Message: message, Cause: cause}
}
