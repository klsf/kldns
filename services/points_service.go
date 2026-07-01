package services

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strings"
	"unicode/utf8"

	"kldns/models"
	apperrors "kldns/pkg/errors"
	"kldns/repositories"
)

const (
	PointAdjustModeIncrease = "increase"
	PointAdjustModeDecrease = "decrease"
	maxPointAdjustment      = 100000000
)

type AdminPointsRepository interface {
	AdjustUserPoints(ctx context.Context, adjustment repositories.PointAdjustment) (repositories.PointAdjustmentResult, error)
}

type AdminPointsService struct {
	Repo AdminPointsRepository
}

type AdjustUserPointsInput struct {
	AdminID int64
	UserID  int64
	Mode    string
	Points  int64
	Remark  string
	Source  string
}

func (s AdminPointsService) AdjustUserPoints(ctx context.Context, input AdjustUserPointsInput) (repositories.PointAdjustmentResult, *apperrors.AppError) {
	if input.UserID <= 0 {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "用户 ID 不正确")
	}
	if input.Points <= 0 {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "调整积分必须大于 0")
	}
	if input.Points > maxPointAdjustment {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "单次调整积分不能超过 100000000")
	}
	mode := strings.ToLower(strings.TrimSpace(input.Mode))
	if mode != PointAdjustModeIncrease && mode != PointAdjustModeDecrease {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "积分调整类型不正确")
	}
	remark := strings.TrimSpace(input.Remark)
	if remark == "" {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "请输入调整原因")
	}
	if utf8.RuneCountInString(remark) > 200 {
		return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "调整原因不能超过 200 个字符")
	}
	source := strings.TrimSpace(input.Source)
	if source == "" {
		source = "admin"
	}
	action := "后台增加"
	logAction := "points.admin_increase"
	delta := input.Points
	if mode == PointAdjustModeDecrease {
		action = "后台扣除"
		logAction = "points.admin_decrease"
		delta = -input.Points
	}
	result, err := s.Repo.AdjustUserPoints(ctx, repositories.PointAdjustment{
		UserID: input.UserID, AdminID: input.AdminID, Delta: delta, Action: action, Remark: remark,
		Log: operationLogForPointAdjustment(input.AdminID, input.UserID, source, logAction, action, delta, remark),
	})
	if err != nil {
		if errors.Is(err, repositories.ErrInsufficientPoints) {
			return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInsufficientPoints, "用户积分不足，不能扣成负数")
		}
		if errors.Is(err, repositories.ErrPointOverflow) {
			return repositories.PointAdjustmentResult{}, apperrors.New(apperrors.CodeInvalidArgument, "用户积分余额过大，无法继续增加")
		}
		if errors.Is(err, sql.ErrNoRows) {
			return repositories.PointAdjustmentResult{}, apperrors.Wrap(apperrors.CodeNotFound, "用户不存在", err)
		}
		return repositories.PointAdjustmentResult{}, apperrors.Wrap(apperrors.CodeInternal, "调整用户积分失败", err)
	}
	return result, nil
}

func operationLogForPointAdjustment(adminID int64, userID int64, source string, logAction string, action string, delta int64, remark string) models.OperationLog {
	return models.OperationLog{
		UID: userID, AdminUID: adminID, Source: source, TargetType: "user_points", TargetID: fmt.Sprintf("%d", userID),
		Action:  logAction,
		Message: fmt.Sprintf("%s用户积分 [%+d]", action, delta),
		Extra:   mustJSON(map[string]any{"uid": userID, "delta": delta, "remark": remark}),
	}
}
