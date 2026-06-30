package services

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"slices"
	"strings"

	"kldns/models"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
	"kldns/pkg/validation"
)

type RecordRepository interface {
	GetUser(ctx context.Context, id int64) (models.User, error)
	GetDomainForGroup(ctx context.Context, did int64, gid int64) (models.Domain, error)
	GetSubdomainForUser(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error)
	GetRecordForUser(ctx context.Context, id int64, uid int64) (models.Record, error)
	RecordNameExists(ctx context.Context, did int64, name string, recordType string, ignoreID int64) (bool, error)
	AllowUnlimitedSubdomainRecords(ctx context.Context) (bool, error)
	ApplyCreatedRecord(ctx context.Context, user models.User, domain models.Domain, record models.Record, log models.OperationLog) error
	ApplyUpdatedRecord(ctx context.Context, recordID int64, record models.Record, log models.OperationLog) error
	ApplyDeletedRecord(ctx context.Context, recordID int64, log models.OperationLog) error
	EnqueueDNSWriteJob(ctx context.Context, job models.DNSWriteJob) error
}

type ProviderResolver interface {
	Resolve(ctx context.Context, domain models.Domain) (dns.Provider, error)
}

type RecordService struct {
	Repo     RecordRepository
	Resolver ProviderResolver
	Reserved []string
}

type SubmitRecordInput struct {
	UserID      int64
	ID          int64
	DID         int64
	SubdomainID int64
	Name        string
	Type        string
	Value       string
	LineID      string
	Source      string
}

type SubmitRecordResult struct {
	Mode string `json:"mode"`
}

func (s *RecordService) Submit(ctx context.Context, input SubmitRecordInput) (SubmitRecordResult, *apperrors.AppError) {
	source := input.Source
	if source == "" {
		source = "web"
	}
	user, err := s.Repo.GetUser(ctx, input.UserID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeUnauthorized, "用户不存在", err)
	}
	if user.Status != 2 {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "账号待审核，暂不能提交解析记录")
	}

	relativeName, message, ok := validation.ValidateRelativeRecordName(input.Name)
	if !ok {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeInvalidArgument, message)
	}

	recordType := strings.ToUpper(strings.TrimSpace(input.Type))
	value := strings.TrimSpace(input.Value)
	if message, ok := validation.ValidateRecordValue(recordType, value); !ok {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeInvalidArgument, message)
	}

	var existing models.Record
	var ignoreID int64
	subdomainID := input.SubdomainID
	if input.ID > 0 {
		var err error
		existing, err = s.Repo.GetRecordForUser(ctx, input.ID, user.ID)
		if err != nil {
			return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "记录不存在", err)
		}
		ignoreID = existing.ID
		if subdomainID == 0 {
			subdomainID = existing.SubdomainID
		}
	}
	subdomain, domain, err := s.Repo.GetSubdomainForUser(ctx, subdomainID, user.ID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在，或无此权限", err)
	}
	if input.ID > 0 && existing.SubdomainID != subdomain.ID {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "不能跨二级域名修改记录")
	}
	if !slices.Contains(domain.RecordTypes, recordType) {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "当前主域不支持此解析类型")
	}
	allowUnlimited, err := s.Repo.AllowUnlimitedSubdomainRecords(ctx)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取解析策略失败", err)
	}
	if !allowUnlimited && relativeName != "@" {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "当前系统只允许解析已注册域名本身，不能添加三级或更深域名")
	}
	name := composeRecordName(subdomain.Name, relativeName)

	conflict, err := s.Repo.RecordNameExists(ctx, domain.ID, name, recordType, ignoreID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeInternal, "检查记录冲突失败", err)
	}
	if conflict {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeConflict, "此主机记录与解析类型已被使用，或 CNAME 记录与其他类型冲突")
	}

	record := models.Record{
		UID: user.ID, DID: domain.ID, SubdomainID: subdomain.ID, Name: name, Type: recordType,
		Value: value, LineID: defaultLineID(input.LineID), Line: "默认",
	}
	if input.ID > 0 {
		return s.applyUpdate(ctx, user, domain, existing, record, source)
	}
	return s.applyCreate(ctx, user, domain, record, source)
}

func (s *RecordService) Delete(ctx context.Context, userID int64, recordID int64, source string) (SubmitRecordResult, *apperrors.AppError) {
	if source == "" {
		source = "web"
	}
	user, err := s.Repo.GetUser(ctx, userID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeUnauthorized, "用户不存在", err)
	}
	if user.Status != 2 {
		return SubmitRecordResult{}, apperrors.New(apperrors.CodeForbidden, "账号待审核，暂不能删除解析记录")
	}
	record, err := s.Repo.GetRecordForUser(ctx, recordID, user.ID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "记录不存在", err)
	}
	_, domain, err := s.Repo.GetSubdomainForUser(ctx, record.SubdomainID, user.ID)
	if err != nil {
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在，或无此权限", err)
	}
	return s.applyDelete(ctx, user, domain, record, source)
}

func (s *RecordService) applyCreate(ctx context.Context, user models.User, domain models.Domain, record models.Record, source string) (SubmitRecordResult, *apperrors.AppError) {
	return createRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, record, source, func(created models.Record) error {
		return s.Repo.ApplyCreatedRecord(ctx, user, domain, created, models.OperationLog{
			UID: user.ID, Source: source, TargetType: "record", TargetID: created.RecordID,
			Action:  "record.create",
			Message: fmt.Sprintf("添加解析记录 [%s.%s]", created.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": created.RecordID}),
		})
	})
}

func (s *RecordService) applyUpdate(ctx context.Context, user models.User, domain models.Domain, oldRecord models.Record, next models.Record, source string) (SubmitRecordResult, *apperrors.AppError) {
	return updateRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, oldRecord, next, source, func(updated models.Record) error {
		return s.Repo.ApplyUpdatedRecord(ctx, oldRecord.ID, updated, models.OperationLog{
			UID: user.ID, Source: source, TargetType: "record", TargetID: fmt.Sprintf("%d", oldRecord.ID),
			Action:  "record.update",
			Message: fmt.Sprintf("修改解析记录 [%s.%s]", updated.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": updated.RecordID}),
		})
	})
}

func (s *RecordService) applyDelete(ctx context.Context, user models.User, domain models.Domain, record models.Record, source string) (SubmitRecordResult, *apperrors.AppError) {
	return deleteRemoteRecordThenLocal(ctx, s.Resolver, s.Repo.EnqueueDNSWriteJob, domain, record, source, func() error {
		return s.Repo.ApplyDeletedRecord(ctx, record.ID, models.OperationLog{
			UID: user.ID, Source: source, TargetType: "record", TargetID: fmt.Sprintf("%d", record.ID),
			Action:  "record.delete",
			Message: fmt.Sprintf("删除解析记录 [%s.%s]", record.Name, domain.Domain),
			Extra:   mustJSON(map[string]any{"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": record.RecordID}),
		})
	})
}

func defaultLineID(lineID string) string {
	lineID = strings.TrimSpace(lineID)
	if lineID == "" {
		return "0"
	}
	return lineID
}

func composeRecordName(subdomain string, relativeName string) string {
	subdomain = strings.ToLower(strings.TrimSpace(subdomain))
	relativeName = strings.ToLower(strings.TrimSpace(relativeName))
	if relativeName == "" || relativeName == "@" {
		return subdomain
	}
	return relativeName + "." + subdomain
}

func digest(value string) string {
	sum := sha256.Sum256([]byte(value))
	return hex.EncodeToString(sum[:])
}

func mustJSON(v any) string {
	data, err := json.Marshal(v)
	if err != nil {
		return "{}"
	}
	return string(data)
}
