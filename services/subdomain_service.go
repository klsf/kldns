package services

import (
	"context"
	"fmt"
	"strings"

	"kldns/models"
	apperrors "kldns/pkg/errors"
	"kldns/pkg/validation"
)

type SubdomainRepository interface {
	GetUser(ctx context.Context, id int64) (models.User, error)
	GetDomainForGroup(ctx context.Context, did int64, gid int64) (models.Domain, error)
	GetSubdomainForUser(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error)
	GetUserSubdomain(ctx context.Context, id int64, uid int64) (models.Subdomain, models.Domain, error)
	CountRecordsForSubdomain(ctx context.Context, subdomainID int64, uid int64) (int64, error)
	RegisterSubdomain(ctx context.Context, user models.User, domain models.Domain, name string, purpose string, requireReview bool, log models.OperationLog) (models.Subdomain, error)
	DeleteSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error
	CancelPendingSubdomain(ctx context.Context, subdomain models.Subdomain, domain models.Domain, pointRemark string, log models.OperationLog) error
}

type SubdomainService struct {
	Repo     SubdomainRepository
	Reserved []string
}

type RegisterSubdomainInput struct {
	UserID  int64
	DID     int64
	Name    string
	Purpose string
	Source  string
}

type DeleteSubdomainInput struct {
	UserID            int64
	ID                int64
	ConfirmFullDomain string
	Source            string
}

type DeleteSubdomainResult struct {
	Deleted bool `json:"deleted"`
}

func (s *SubdomainService) Register(ctx context.Context, input RegisterSubdomainInput) (models.Subdomain, *apperrors.AppError) {
	source := input.Source
	if source == "" {
		source = "web"
	}
	user, err := s.Repo.GetUser(ctx, input.UserID)
	if err != nil {
		return models.Subdomain{}, apperrors.Wrap(apperrors.CodeUnauthorized, "用户不存在", err)
	}
	if user.Status != 2 {
		return models.Subdomain{}, apperrors.New(apperrors.CodeForbidden, "账号待审核，暂不能注册二级域名")
	}
	name, message, ok := validation.ValidateSubdomainLabel(input.Name, s.Reserved)
	if !ok {
		return models.Subdomain{}, apperrors.New(apperrors.CodeInvalidArgument, message)
	}
	domain, err := s.Repo.GetDomainForGroup(ctx, input.DID, user.GroupID)
	if err != nil {
		return models.Subdomain{}, apperrors.Wrap(apperrors.CodeNotFound, "主域不存在，或无此权限", err)
	}
	if domain.PointsCost > 0 && user.Points < domain.PointsCost {
		return models.Subdomain{}, apperrors.New(apperrors.CodeInsufficientPoints, "账户剩余积分不足")
	}
	purpose := strings.TrimSpace(input.Purpose)
	requireReview := domain.RequireReview == 1
	if requireReview {
		if purpose == "" {
			return models.Subdomain{}, apperrors.New(apperrors.CodeInvalidArgument, "请输入域名用途")
		}
		if len([]rune(purpose)) > 500 {
			return models.Subdomain{}, apperrors.New(apperrors.CodeInvalidArgument, "域名用途不能超过 500 个字符")
		}
	}
	fullDomain := name + "." + domain.Domain
	subdomain, err := s.Repo.RegisterSubdomain(ctx, user, domain, name, purpose, requireReview, models.OperationLog{
		UID: user.ID, Source: source, TargetType: "subdomain", TargetID: fullDomain,
		Action:  "subdomain.register",
		Message: fmt.Sprintf("注册二级域名 [%s]", fullDomain),
		Extra:   mustJSON(map[string]any{"domain": domain.Domain, "subdomain": name, "cost": domain.PointsCost, "require_review": requireReview, "purpose": purpose}),
	})
	if err != nil {
		return models.Subdomain{}, apperrors.Wrap(apperrors.CodeConflict, "二级域名已被注册", err)
	}
	return subdomain, nil
}

func (s *SubdomainService) Delete(ctx context.Context, input DeleteSubdomainInput) (DeleteSubdomainResult, *apperrors.AppError) {
	source := input.Source
	if source == "" {
		source = "web"
	}
	if input.ID <= 0 {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeInvalidArgument, "二级域名 ID 不正确")
	}
	user, err := s.Repo.GetUser(ctx, input.UserID)
	if err != nil {
		return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeUnauthorized, "用户不存在", err)
	}
	if user.Status == 0 {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeForbidden, "账号已停用")
	}
	if user.Status != 2 {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeForbidden, "账号待审核，暂不能删除二级域名")
	}
	subdomain, domain, err := s.Repo.GetUserSubdomain(ctx, input.ID, user.ID)
	if err != nil {
		return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在，或无此权限", err)
	}
	confirm := strings.ToLower(strings.TrimSpace(input.ConfirmFullDomain))
	if confirm == "" || confirm != strings.ToLower(subdomain.FullDomain) {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeInvalidArgument, "请输入完整二级域名确认删除")
	}
	if subdomain.Status == models.SubdomainStatusPending {
		err = s.Repo.CancelPendingSubdomain(ctx, subdomain, domain, fmt.Sprintf("用户撤销域名申请，退回注册积分[%s]", subdomain.FullDomain), models.OperationLog{
			UID: user.ID, Source: source, TargetType: "subdomain", TargetID: subdomain.FullDomain,
			Action:  "subdomain.cancel_pending",
			Message: fmt.Sprintf("撤销待审核二级域名申请 [%s]", subdomain.FullDomain),
			Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain, "refund": domain.PointsCost}),
		})
		if err != nil {
			return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeInternal, "撤销申请失败", err)
		}
		return DeleteSubdomainResult{Deleted: true}, nil
	}
	if subdomain.Status == models.SubdomainStatusRejected {
		err = s.Repo.DeleteSubdomain(ctx, subdomain, models.OperationLog{
			UID: user.ID, Source: source, TargetType: "subdomain", TargetID: subdomain.FullDomain,
			Action:  "subdomain.delete_rejected",
			Message: fmt.Sprintf("删除已驳回二级域名申请记录 [%s]", subdomain.FullDomain),
			Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain}),
		})
		if err != nil {
			return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeConflict, "删除驳回记录失败", err)
		}
		return DeleteSubdomainResult{Deleted: true}, nil
	}
	if subdomain.Status != models.SubdomainStatusActive {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeForbidden, "已停用域名请联系管理员")
	}
	count, err := s.Repo.CountRecordsForSubdomain(ctx, subdomain.ID, user.ID)
	if err != nil {
		return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeInternal, "检查解析记录失败", err)
	}
	if count > 0 {
		return DeleteSubdomainResult{}, apperrors.New(apperrors.CodeConflict, "请先删除该二级域名下的解析记录")
	}
	err = s.Repo.DeleteSubdomain(ctx, subdomain, models.OperationLog{
		UID: user.ID, Source: source, TargetType: "subdomain", TargetID: subdomain.FullDomain,
		Action:  "subdomain.delete",
		Message: fmt.Sprintf("删除二级域名 [%s]", subdomain.FullDomain),
		Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain}),
	})
	if err != nil {
		return DeleteSubdomainResult{}, apperrors.Wrap(apperrors.CodeConflict, "删除二级域名失败", err)
	}
	return DeleteSubdomainResult{Deleted: true}, nil
}
