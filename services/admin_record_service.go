package services

import (
	"context"
	"fmt"
	"slices"
	"strings"

	"kldns/models"
	apperrors "kldns/pkg/errors"
	"kldns/pkg/validation"
)

type AdminRecordRepository interface {
	GetUser(ctx context.Context, id int64) (models.User, error)
	GetDomain(ctx context.Context, did int64) (models.Domain, error)
	GetRecord(ctx context.Context, id int64) (models.Record, error)
	RecordNameExists(ctx context.Context, did int64, name string, recordType string, ignoreID int64) (bool, error)
	ApplyAdminCreatedRecord(ctx context.Context, record models.Record, log models.OperationLog) error
	ApplyAdminUpdatedRecord(ctx context.Context, recordID int64, record models.Record, log models.OperationLog) error
	ApplyDeletedRecord(ctx context.Context, recordID int64, log models.OperationLog) error
	EnqueueDNSWriteJob(ctx context.Context, job models.DNSWriteJob) error
}

type AdminRecordService struct {
	Repo     AdminRecordRepository
	Resolver ProviderResolver
	Reserved []string
}

type AdminRecordInput struct {
	AdminID int64
	UID     int64
	ID      int64
	DID     int64
	Name    string
	Type    string
	Value   string
	LineID  string
	Source  string
}

func (s *AdminRecordService) Create(ctx context.Context, input AdminRecordInput) (SubmitRecordResult, *apperrors.AppError) {
	source := adminRecordSource(input.Source)
	user, err := s.Repo.GetUser(ctx, input.UID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "用户不存在", err)
	}
	if user.Status == 0 {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "用户已被禁用")
	}
	domain, record, appErr := s.prepareRecord(ctx, input, 0)
	if appErr != nil {
		return SubmitRecordResult{}, appErr
	}
	record.UID = user.ID

	return createRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, record, source, func(created models.Record) error {
		return s.Repo.ApplyAdminCreatedRecord(ctx, created, models.OperationLog{
			UID: user.ID, AdminUID: input.AdminID, Source: source, TargetType: "record", TargetID: created.RecordID,
			Action:  "record.admin_create",
			Message: fmt.Sprintf("后台添加解析记录 [%s.%s]", created.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": created.RecordID}),
		})
	})
}

func (s *AdminRecordService) Update(ctx context.Context, input AdminRecordInput) (SubmitRecordResult, *apperrors.AppError) {
	source := adminRecordSource(input.Source)
	oldRecord, err := s.Repo.GetRecord(ctx, input.ID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "记录不存在", err)
	}
	input.UID = oldRecord.UID
	input.DID = oldRecord.DID
	domain, next, appErr := s.prepareRecord(ctx, input, oldRecord.ID)
	if appErr != nil {
		return SubmitRecordResult{}, appErr
	}
	next.UID = oldRecord.UID
	next.DID = oldRecord.DID

	return updateRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, oldRecord, next, source, func(updated models.Record) error {
		return s.Repo.ApplyAdminUpdatedRecord(ctx, oldRecord.ID, updated, models.OperationLog{
			UID: oldRecord.UID, AdminUID: input.AdminID, Source: source, TargetType: "record", TargetID: fmt.Sprintf("%d", oldRecord.ID),
			Action:  "record.admin_update",
			Message: fmt.Sprintf("后台修改解析记录 [%s.%s]", updated.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": updated.RecordID}),
		})
	})
}

func (s *AdminRecordService) Delete(ctx context.Context, adminID int64, recordID int64, source string) (SubmitRecordResult, *apperrors.AppError) {
	source = adminRecordSource(source)
	record, err := s.Repo.GetRecord(ctx, recordID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "记录不存在", err)
	}
	domain, err := s.Repo.GetDomain(ctx, record.DID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "主域不存在", err)
	}
	return deleteRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, record, source, func() error {
		return s.Repo.ApplyDeletedRecord(ctx, record.ID, models.OperationLog{
			UID: record.UID, AdminUID: adminID, Source: source, TargetType: "record", TargetID: fmt.Sprintf("%d", record.ID),
			Action:  "record.admin_delete",
			Message: fmt.Sprintf("后台删除解析记录 [%s.%s]", record.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": record.RecordID}),
		})
	})
}

func (s *AdminRecordService) prepareRecord(ctx context.Context, input AdminRecordInput, ignoreID int64) (models.Domain, models.Record, *apperrors.AppError) {
	name, message, ok := validation.ValidateRecordPrefix(input.Name, s.Reserved)
	if !ok {
		return models.Domain{}, models.Record{}, apperrors.New(apperrors.CodeInvalidArgument, message)
	}
	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	value := strings.TrimSpace(input.Value)
	if message, ok := validation.ValidateRecordValue(recordType, value); !ok {
		return models.Domain{}, models.Record{}, apperrors.New(apperrors.CodeInvalidArgument, message)
	}
	domain, err := s.Repo.GetDomain(ctx, input.DID)
	if err != nil {
		return models.Domain{}, models.Record{}, apperrors.Wrap(apperrors.CodeNotFound, "主域不存在", err)
	}
	if !slices.Contains(domain.RecordTypes, recordType) {
		return models.Domain{}, models.Record{}, apperrors.New(apperrors.CodeForbidden, "当前主域不支持此解析类型")
	}
	conflict, err := s.Repo.RecordNameExists(ctx, domain.ID, name, recordType, ignoreID)
	if err != nil {
		return models.Domain{}, models.Record{}, apperrors.Wrap(apperrors.CodeInternal, "检查记录冲突失败", err)
	}
	if conflict {
		return models.Domain{}, models.Record{}, apperrors.New(apperrors.CodeConflict, "此主机记录与解析类型已被使用，或 CNAME 记录与其他类型冲突")
	}
	return domain, models.Record{
		UID: input.UID, DID: domain.ID, Name: name, Type: recordType,
		Value: value, LineID: defaultLineID(input.LineID), Line: "默认",
	}, nil
}

func adminRecordSource(source string) string {
	source = strings.TrimSpace(source)
	if source == "" {
		return "admin"
	}
	return source
}
