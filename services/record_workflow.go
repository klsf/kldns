package services

import (
	"context"

	"kldns/models"
	"kldns/pkg/dns"
	apperrors "kldns/pkg/errors"
)

type enqueueDNSWriteJobFunc func(context.Context, models.DNSWriteJob) error

func createRemoteRecordThenLocal(
	ctx context.Context,
	resolver ProviderResolver,
	enqueue enqueueDNSWriteJobFunc,
	domain models.Domain,
	record models.Record,
	source string,
	applyLocal func(models.Record) error,
) (SubmitRecordResult, *apperrors.AppError) {
	provider, err := resolver.Resolve(ctx, domain)
	if err != nil {
		return SubmitRecordResult{}, dnsProviderError("域名配置错误", err)
	}
	zone := dns.Zone{ID: domain.RemoteZoneID, Domain: domain.Domain}
	remoteRecord, err := provider.CreateRecord(ctx, zone, dns.RecordInput{
		Name: record.Name, Type: record.Type, Value: record.Value, LineID: record.LineID,
	})
	if err != nil {
		return SubmitRecordResult{}, dnsProviderError("添加记录失败", err)
	}
	record.RecordID = remoteRecord.RemoteID
	if remoteRecord.Line != "" {
		record.Line = remoteRecord.Line
	}
	if err := applyLocal(record); err == nil {
		return SubmitRecordResult{Mode: "direct"}, nil
	} else {
		if deleteErr := provider.DeleteRecord(ctx, zone, remoteRecord.RemoteID); deleteErr != nil {
			enqueueRecordRepair(ctx, enqueue, models.DNSWriteJob{
				UID: record.UID, Source: source, ProviderKey: domain.ProviderKey, Domain: domain.Domain,
				RecordName: record.Name, RecordType: record.Type, ValueDigest: digest(record.Value),
				RemoteRecordID: remoteRecord.RemoteID, Operation: "compensate_delete", Status: "pending",
				LastError: deleteErr.Error(), Payload: mustJSON(map[string]any{"record": record, "delete_error": deleteErr.Error()}),
			})
		}
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeInternal, "本地保存失败，已触发远端补偿流程", err)
	}
}

func updateRemoteRecordThenLocal(
	ctx context.Context,
	resolver ProviderResolver,
	enqueue enqueueDNSWriteJobFunc,
	domain models.Domain,
	oldRecord models.Record,
	next models.Record,
	source string,
	applyLocal func(models.Record) error,
) (SubmitRecordResult, *apperrors.AppError) {
	provider, err := resolver.Resolve(ctx, domain)
	if err != nil {
		return SubmitRecordResult{}, dnsProviderError("域名配置错误", err)
	}
	zone := dns.Zone{ID: domain.RemoteZoneID, Domain: domain.Domain}
	remoteRecord, err := provider.UpdateRecord(ctx, zone, oldRecord.RecordID, dns.RecordInput{
		Name: next.Name, Type: next.Type, Value: next.Value, LineID: next.LineID,
	})
	if err != nil {
		return SubmitRecordResult{}, dnsProviderError("更新记录失败", err)
	}
	next.RecordID = oldRecord.RecordID
	if remoteRecord.RemoteID != "" {
		next.RecordID = remoteRecord.RemoteID
	}
	if remoteRecord.Line != "" {
		next.Line = remoteRecord.Line
	}
	if err := applyLocal(next); err == nil {
		return SubmitRecordResult{Mode: "direct"}, nil
	} else {
		if _, restoreErr := provider.UpdateRecord(ctx, zone, next.RecordID, dns.RecordInput{
			Name: oldRecord.Name, Type: oldRecord.Type, Value: oldRecord.Value, LineID: oldRecord.LineID,
		}); restoreErr != nil {
			enqueueRecordRepair(ctx, enqueue, models.DNSWriteJob{
				UID: oldRecord.UID, Source: source, ProviderKey: domain.ProviderKey, Domain: domain.Domain,
				RecordName: oldRecord.Name, RecordType: oldRecord.Type, ValueDigest: digest(oldRecord.Value),
				RemoteRecordID: next.RecordID, Operation: "restore_update", Status: "pending",
				LastError: restoreErr.Error(), Payload: mustJSON(map[string]any{"old": oldRecord, "next": next}),
			})
		}
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeInternal, "本地保存失败，已触发远端恢复流程", err)
	}
}

func deleteRemoteRecordThenLocal(
	ctx context.Context,
	resolver ProviderResolver,
	enqueue enqueueDNSWriteJobFunc,
	domain models.Domain,
	record models.Record,
	source string,
	applyLocal func() error,
) (SubmitRecordResult, *apperrors.AppError) {
	provider, err := resolver.Resolve(ctx, domain)
	if err != nil {
		return SubmitRecordResult{}, dnsProviderError("域名配置错误", err)
	}
	zone := dns.Zone{ID: domain.RemoteZoneID, Domain: domain.Domain}
	if err := provider.DeleteRecord(ctx, zone, record.RecordID); err != nil {
		return SubmitRecordResult{}, dnsProviderError("删除记录失败", err)
	}
	if err := applyLocal(); err == nil {
		return SubmitRecordResult{Mode: "direct"}, nil
	} else {
		enqueueRecordRepair(ctx, enqueue, models.DNSWriteJob{
			UID: record.UID, Source: source, ProviderKey: domain.ProviderKey, Domain: domain.Domain,
			RecordName: record.Name, RecordType: record.Type, ValueDigest: digest(record.Value),
			RemoteRecordID: record.RecordID, Operation: "restore_deleted_record", Status: "pending",
			LastError: err.Error(), Payload: mustJSON(map[string]any{"record": record}),
		})
		return SubmitRecordResult{}, apperrors.Wrap(apperrors.CodeInternal, "本地删除失败，已记录待修复任务", err)
	}
}

func enqueueRecordRepair(ctx context.Context, enqueue enqueueDNSWriteJobFunc, job models.DNSWriteJob) {
	if enqueue == nil {
		return
	}
	_ = enqueue(ctx, job)
}
