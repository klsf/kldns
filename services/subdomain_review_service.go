package services

import (
	"context"
	"fmt"
	"strings"
	"unicode/utf8"

	"kldns/models"
	apperrors "kldns/pkg/errors"
)

type SubdomainReviewRepository interface {
	GetSubdomain(ctx context.Context, id int64) (models.Subdomain, models.Domain, error)
	ApproveSubdomain(ctx context.Context, subdomain models.Subdomain, log models.OperationLog) error
	RejectPendingSubdomain(ctx context.Context, subdomain models.Subdomain, domain models.Domain, reason string, pointRemark string, log models.OperationLog) error
}

type AdminSubdomainReviewService struct {
	Repo SubdomainReviewRepository
}

type SubdomainReviewResult struct {
	Reviewed bool   `json:"reviewed"`
	Action   string `json:"action"`
	Refund   int64  `json:"refund,omitempty"`
}

func (s AdminSubdomainReviewService) Approve(ctx context.Context, adminID int64, subdomainID int64, source string) (SubdomainReviewResult, *apperrors.AppError) {
	if subdomainID <= 0 {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeInvalidArgument, "二级域名 ID 不正确")
	}
	if source == "" {
		source = "admin"
	}
	subdomain, _, err := s.Repo.GetSubdomain(ctx, subdomainID)
	if err != nil {
		return SubdomainReviewResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在", err)
	}
	if subdomain.Status != models.SubdomainStatusPending {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeConflict, "该二级域名不是待审核状态")
	}
	err = s.Repo.ApproveSubdomain(ctx, subdomain, models.OperationLog{
		UID: subdomain.UID, AdminUID: adminID, Source: source, TargetType: "subdomain", TargetID: subdomain.FullDomain,
		Action:  "subdomain.admin_approve",
		Message: fmt.Sprintf("审核通过二级域名 [%s]", subdomain.FullDomain),
		Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain, "purpose": subdomain.Purpose}),
	})
	if err != nil {
		return SubdomainReviewResult{}, apperrors.Wrap(apperrors.CodeInternal, "审核通过失败", err)
	}
	return SubdomainReviewResult{Reviewed: true, Action: "approve"}, nil
}

func (s AdminSubdomainReviewService) Reject(ctx context.Context, adminID int64, subdomainID int64, reason string, source string) (SubdomainReviewResult, *apperrors.AppError) {
	if subdomainID <= 0 {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeInvalidArgument, "二级域名 ID 不正确")
	}
	reason = strings.TrimSpace(reason)
	if reason == "" {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeInvalidArgument, "请输入驳回原因")
	}
	if utf8.RuneCountInString(reason) > 200 {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeInvalidArgument, "驳回原因不能超过 200 个字符")
	}
	if source == "" {
		source = "admin"
	}
	subdomain, domain, err := s.Repo.GetSubdomain(ctx, subdomainID)
	if err != nil {
		return SubdomainReviewResult{}, apperrors.Wrap(apperrors.CodeNotFound, "二级域名不存在", err)
	}
	if subdomain.Status != models.SubdomainStatusPending {
		return SubdomainReviewResult{}, apperrors.New(apperrors.CodeConflict, "该二级域名不是待审核状态")
	}
	pointRemark := fmt.Sprintf("域名申请审核未通过，退回注册积分[%s]：%s", subdomain.FullDomain, reason)
	err = s.Repo.RejectPendingSubdomain(ctx, subdomain, domain, reason, pointRemark, models.OperationLog{
		UID: subdomain.UID, AdminUID: adminID, Source: source, TargetType: "subdomain", TargetID: subdomain.FullDomain,
		Action:  "subdomain.admin_reject",
		Message: fmt.Sprintf("审核驳回二级域名 [%s]", subdomain.FullDomain),
		Extra:   mustJSON(map[string]any{"subdomain_id": subdomain.ID, "full_domain": subdomain.FullDomain, "purpose": subdomain.Purpose, "reason": reason, "refund": domain.PointsCost}),
	})
	if err != nil {
		return SubdomainReviewResult{}, apperrors.Wrap(apperrors.CodeInternal, "审核驳回失败", err)
	}
	return SubdomainReviewResult{Reviewed: true, Action: "reject", Refund: domain.PointsCost}, nil
}
