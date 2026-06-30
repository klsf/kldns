package services

import (
	"context"
	"fmt"
	"strings"

	"kldns/models"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
	"kldns/repositories"
)

type DomainRecordSyncRepository interface {
	GetDomain(ctx context.Context, did int64) (models.Domain, error)
	SyncDomainRecords(ctx context.Context, domain models.Domain, records []repositories.SyncedRecordInput, log models.OperationLog) (repositories.SyncRecordsResult, error)
}

type AdminDomainSyncService struct {
	Repo     DomainRecordSyncRepository
	Resolver ProviderResolver
}

func (s *AdminDomainSyncService) SyncRecords(ctx context.Context, adminID int64, domainID int64) (repositories.SyncRecordsResult, *apperrors.AppError) {
	domain, err := s.Repo.GetDomain(ctx, domainID)
	if err != nil {
		return repositories.SyncRecordsResult{}, apperrors.Wrap(apperrors.CodeNotFound, "主域不存在", err)
	}
	provider, err := s.Resolver.Resolve(ctx, domain)
	if err != nil {
		return repositories.SyncRecordsResult{}, dnsProviderError("域名配置错误", err)
	}
	remoteRecords, err := provider.ListRecords(ctx, dns.Zone{ID: domain.RemoteZoneID, Domain: domain.Domain})
	if err != nil {
		return repositories.SyncRecordsResult{}, dnsProviderError("获取远端解析记录失败", err)
	}
	records := make([]repositories.SyncedRecordInput, 0, len(remoteRecords))
	for _, record := range remoteRecords {
		input, ok := normalizeSyncedRecord(record)
		if ok {
			records = append(records, input)
		}
	}
	result, err := s.Repo.SyncDomainRecords(ctx, domain, records, models.OperationLog{
		AdminUID: adminID, Source: "admin", TargetType: "domain", TargetID: fmt.Sprintf("%d", domain.ID),
		Action:  "domain.sync_records",
		Message: fmt.Sprintf("后台同步主域解析记录 [%s]", domain.Domain),
		Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "total": len(remoteRecords), "accepted": len(records)}),
	})
	if err != nil {
		return repositories.SyncRecordsResult{}, apperrors.Wrap(apperrors.CodeInternal, "保存同步记录失败", err)
	}
	return result, nil
}

func normalizeSyncedRecord(record dns.Record) (repositories.SyncedRecordInput, bool) {
	recordID := strings.TrimSpace(record.RemoteID)
	name := strings.ToLower(strings.TrimSpace(record.Name))
	recordType := strings.ToUpper(strings.TrimSpace(record.Type))
	value := strings.TrimSpace(record.Value)
	if recordID == "" || name == "" || recordType == "" || value == "" {
		return repositories.SyncedRecordInput{}, false
	}
	lineID := strings.TrimSpace(record.LineID)
	if lineID == "" {
		lineID = "0"
	}
	line := strings.TrimSpace(record.Line)
	if line == "" {
		line = "默认"
	}
	return repositories.SyncedRecordInput{
		RecordID: recordID,
		Name:     name,
		Type:     recordType,
		Value:    value,
		LineID:   lineID,
		Line:     line,
	}, true
}
