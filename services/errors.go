package services

import (
	"strings"

	apperrors "kldns/pkg/errors"
)

func dnsProviderError(message string, err error) *apperrors.AppError {
	detail := strings.TrimSpace(err.Error())
	if detail == "" {
		return apperrors.Wrap(apperrors.CodeDNSProviderFailed, message, err)
	}
	if len(detail) > 240 {
		detail = detail[:240] + "..."
	}
	return apperrors.Wrap(apperrors.CodeDNSProviderFailed, message+"："+detail, err)
}
