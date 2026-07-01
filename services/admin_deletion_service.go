package services

import (
	"context"
	"fmt"
	"strings"

	"kldns/models"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
)

const (
	DomainDeleteModeLocalOnly       = "local_subdomains"
	DomainDeleteModePlatformRecords = "platform_records"
)

type AdminDeletionRepository interface {
	GetUser(ctx context.Context, id int64) (models.User, error)
	GetDomain(ctx context.Context, did int64) (models.Domain, error)
	GetSubdomain(ctx context.Context, id int64) (models.Subdomain, models.Domain, error)
	ListRecordsForSubdomain(ctx context.Context, subdomainID int64) ([]models.Record, error)
	ListRecordsForUser(ctx context.Context, uid int64) ([]models.Record, error)
	ListRecordsForUserCascade(ctx context.Context, uid int64) ([]models.Record, error)
	ListRecordsForDomain(ctx context.Context, did int64) ([]models.Record, error)
	ListSubdomainsForUser(ctx context.Context, uid int64) ([]models.Subdomain, error)
	ListSubdomainsForDomain(ctx context.Context, did int64) ([]models.Subdomain, error)
	ApplyDeletedRecord(ctx context.Context, recordID int64, log models.OperationLog) error
	DeleteAdminSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error
	DeleteAdminUser(ctx context.Context, user models.User, log models.OperationLog) error
	DeleteAdminDomain(ctx context.Context, domain models.Domain, log models.OperationLog) error
	EnqueueDNSWriteJob(ctx context.Context, job models.DNSWriteJob) error
}

type AdminDeletionService struct {
	Repo     AdminDeletionRepository
	Resolver ProviderResolver
}

type AdminDeleteResult struct {
	Deleted           bool   `json:"deleted"`
	Mode              string `json:"mode,omitempty"`
	RecordsDeleted    int    `json:"records_deleted"`
	SubdomainsDeleted int    `json:"subdomains_deleted"`
}

func (s *AdminDeletionService) DeleteSubdomain(ctx context.Context, adminID int64, subdomainID int64, source string) (AdminDeleteResult, *apperrors.AppError) {
	if subdomainID <= 0 {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeInvalidArgument, "二级域名 ID 不正确")
	}
	source = adminRecordSource(source)
	subdomain, domain, err := s.Repo.GetSubdomain(ctx, subdomainID)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在", err)
	}
	if subdomain.Status == models.SubdomainStatusPending {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeConflict, "待审核申请请使用审核驳回处理")
	}
	records, err := s.Repo.ListRecordsForSubdomain(ctx, subdomain.ID)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取二级域名解析记录失败", err)
	}
	deleted, appErr := s.deleteRecords(ctx, adminID, domain, records, source, "record.admin_delete_by_subdomain")
	if appErr != nil {
		return AdminDeleteResult{}, appErr
	}
	if err := s.deleteSubdomainLocal(ctx, adminID, subdomain, source, "subdomain.admin_delete"); err != nil {
		return AdminDeleteResult{}, err
	}
	return AdminDeleteResult{Deleted: true, RecordsDeleted: deleted, SubdomainsDeleted: 1}, nil
}

func (s *AdminDeletionService) DeleteUser(ctx context.Context, adminID int64, uid int64, confirmUsername string, source string) (AdminDeleteResult, *apperrors.AppError) {
	if uid <= 0 {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeInvalidArgument, "用户 ID 不正确")
	}
	if uid == 1 {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeForbidden, "初始管理员账号不能删除")
	}
	if uid == adminID {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeForbidden, "不能删除当前登录账号")
	}
	source = adminRecordSource(source)
	user, err := s.Repo.GetUser(ctx, uid)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeNotFound, "用户不存在", err)
	}
	if !sameConfirmation(confirmUsername, user.Username) {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeInvalidArgument, "请输入完整用户名确认删除")
	}
	subdomains, err := s.Repo.ListSubdomainsForUser(ctx, user.ID)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取用户二级域名失败", err)
	}
	records, err := s.Repo.ListRecordsForUserCascade(ctx, user.ID)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取用户解析记录失败", err)
	}
	recordsDeleted, appErr := s.deleteRecordsByDomain(ctx, adminID, records, source, "record.admin_delete_by_user")
	if appErr != nil {
		return AdminDeleteResult{}, appErr
	}
	for _, subdomain := range subdomains {
		if err := s.deleteSubdomainLocal(ctx, adminID, subdomain, source, "subdomain.admin_delete_by_user"); err != nil {
			return AdminDeleteResult{}, err
		}
	}
	if err := s.Repo.DeleteAdminUser(ctx, user, models.OperationLog{
		UID: user.ID, AdminUID: adminID, Source: source, TargetType: "user", TargetID: fmt.Sprintf("%d", user.ID),
		Action:  "user.admin_delete",
		Message: fmt.Sprintf("后台删除用户 [%s]", user.Username),
		Extra:   mustJSON(map[string]any{"username": user.Username, "records_deleted": recordsDeleted, "subdomains_deleted": len(subdomains)}),
	}); err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "删除用户失败", err)
	}
	return AdminDeleteResult{Deleted: true, RecordsDeleted: recordsDeleted, SubdomainsDeleted: len(subdomains)}, nil
}

func (s *AdminDeletionService) DeleteDomain(ctx context.Context, adminID int64, did int64, mode string, source string) (AdminDeleteResult, *apperrors.AppError) {
	if did <= 0 {
		return AdminDeleteResult{}, apperrors.New(apperrors.CodeInvalidArgument, "主域 ID 不正确")
	}
	mode = normalizeDomainDeleteMode(mode)
	source = adminRecordSource(source)
	domain, err := s.Repo.GetDomain(ctx, did)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeNotFound, "主域不存在", err)
	}
	subdomains, err := s.Repo.ListSubdomainsForDomain(ctx, domain.ID)
	if err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取主域二级域名失败", err)
	}
	recordsDeleted := 0
	if mode == DomainDeleteModePlatformRecords {
		records, err := s.Repo.ListRecordsForDomain(ctx, domain.ID)
		if err != nil {
			return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "读取主域解析记录失败", err)
		}
		var appErr *apperrors.AppError
		recordsDeleted, appErr = s.deleteRecords(ctx, adminID, domain, records, source, "record.admin_delete_by_domain")
		if appErr != nil {
			return AdminDeleteResult{}, appErr
		}
	}
	if err := s.Repo.DeleteAdminDomain(ctx, domain, models.OperationLog{
		AdminUID: adminID, Source: source, TargetType: "domain", TargetID: fmt.Sprintf("%d", domain.ID),
		Action:  "domain.admin_delete",
		Message: fmt.Sprintf("后台删除主域 [%s]", domain.Domain),
		Extra: mustJSON(map[string]any{
			"domain": domain.Domain, "provider": domain.ProviderKey, "mode": mode,
			"records_deleted": recordsDeleted, "subdomains_deleted": len(subdomains),
		}),
	}); err != nil {
		return AdminDeleteResult{}, apperrors.Wrap(apperrors.CodeInternal, "删除主域失败", err)
	}
	return AdminDeleteResult{Deleted: true, Mode: mode, RecordsDeleted: recordsDeleted, SubdomainsDeleted: len(subdomains)}, nil
}

func (s *AdminDeletionService) deleteRecordsByDomain(ctx context.Context, adminID int64, records []models.Record, source string, action string) (int, *apperrors.AppError) {
	deleted := 0
	domains := map[int64]models.Domain{}
	groupedRecords := map[int64][]models.Record{}
	for _, record := range records {
		domain, ok := domains[record.DID]
		if !ok {
			var err error
			domain, err = s.Repo.GetDomain(ctx, record.DID)
			if err != nil {
				return deleted, apperrors.Wrap(apperrors.CodeNotFound, "解析记录所属主域不存在", err)
			}
			domains[record.DID] = domain
		}
		groupedRecords[record.DID] = append(groupedRecords[record.DID], record)
	}
	for did, group := range groupedRecords {
		count, appErr := s.deleteRecords(ctx, adminID, domains[did], group, source, action)
		deleted += count
		if appErr != nil {
			return deleted, appErr
		}
	}
	return deleted, nil
}

func (s *AdminDeletionService) deleteRecords(ctx context.Context, adminID int64, domain models.Domain, records []models.Record, source string, action string) (int, *apperrors.AppError) {
	if len(records) == 0 {
		return 0, nil
	}
	provider, err := s.Resolver.Resolve(ctx, domain)
	if err != nil {
		return 0, dnsProviderError("域名配置错误", err)
	}
	deleted := 0
	zone := dns.Zone{ID: domain.RemoteZoneID, Domain: domain.Domain}
	for _, record := range records {
		if err := provider.DeleteRecord(ctx, zone, record.RecordID); err != nil {
			return deleted, dnsProviderError("删除解析记录失败", err)
		}
		err := s.Repo.ApplyDeletedRecord(ctx, record.ID, models.OperationLog{
			UID: record.UID, AdminUID: adminID, Source: source, TargetType: "record", TargetID: fmt.Sprintf("%d", record.ID),
			Action:  action,
			Message: fmt.Sprintf("后台删除解析记录 [%s.%s]", record.Name, domain.Domain),
			Extra: mustJSON(map[string]any{
				"domain": domain.Domain, "provider": domain.ProviderKey, "remote_record_id": record.RecordID,
				"subdomain_id": record.SubdomainID,
			}),
		})
		if err != nil {
			_ = s.Repo.EnqueueDNSWriteJob(ctx, models.DNSWriteJob{
				UID: record.UID, Source: source, ProviderKey: domain.ProviderKey, Domain: domain.Domain,
				RecordName: record.Name, RecordType: record.Type, ValueDigest: digest(record.Value),
				RemoteRecordID: record.RecordID, Operation: "restore_deleted_record", Status: "pending",
				LastError: err.Error(), Payload: mustJSON(map[string]any{"record": record}),
			})
			return deleted, apperrors.Wrap(apperrors.CodeInternal, "本地删除失败，已记录待修复任务", err)
		}
		deleted++
	}
	return deleted, nil
}

func (s *AdminDeletionService) deleteSubdomainLocal(ctx context.Context, adminID int64, subdomain models.Subdomain, source string, action string) *apperrors.AppError {
	if err := s.Repo.DeleteAdminSubdomain(ctx, subdomain, models.OperationLog{
		UID: subdomain.UID, AdminUID: adminID, Source: source, TargetType: "subdomain", TargetID: fmt.Sprintf("%d", subdomain.ID),
		Action:  action,
		Message: fmt.Sprintf("后台删除二级域名 [%s]", subdomain.FullDomain),
		Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain}),
	}); err != nil {
		return apperrors.Wrap(apperrors.CodeConflict, "删除二级域名失败，请确认解析记录已清理", err)
	}
	return nil
}

func sameConfirmation(input string, expected string) bool {
	return strings.EqualFold(strings.TrimSpace(input), strings.TrimSpace(expected))
}

func normalizeDomainDeleteMode(mode string) string {
	mode = strings.TrimSpace(mode)
	if mode == DomainDeleteModePlatformRecords {
		return DomainDeleteModePlatformRecords
	}
	return DomainDeleteModeLocalOnly
}
