<?php

namespace App\Services;

use App\Helper;
use App\Klsf\Dns\Helper as DnsHelper;
use App\Models\Domain;
use App\Models\DomainRecord;
use App\Models\DomainRecordReview;
use App\Models\OperationLog;
use App\Models\User;

class DomainRecordService
{
    public function submit(User $user, array $input, array $options = [])
    {
        $source = $options['source'] ?? 'web';
        $respectReview = array_key_exists('respect_review', $options) ? (bool)$options['respect_review'] : true;
        $id = intval($input['id'] ?? 0);

        $data = [
            'uid' => $user->uid,
            'did' => intval($input['did'] ?? 0),
            'name' => trim((string)($input['name'] ?? '')),
            'type' => strtoupper(trim((string)($input['type'] ?? ''))),
            'line_id' => (string)($input['line_id'] ?? '0'),
            'value' => trim((string)($input['value'] ?? '')),
            'line' => '默认',
        ];

        list($check, $error) = Helper::checkDomainName($data['name']);
        if (!$check) {
            return [false, $error, null];
        }

        $record = null;
        if ($id) {
            $record = DomainRecord::where('uid', $user->uid)->where('id', $id)->first();
            if (!$record) {
                return [false, '记录不存在', null];
            }
        }

        if (!$data['value']) {
            return [false, '请输入记录值', null];
        }

        if (!$id && DomainRecord::where('did', $data['did'])->where('name', $data['name'])->where('uid', '!=', $user->uid)->where('line_id', $data['line_id'])->first()) {
            return [false, '此主机记录已被使用', null];
        }

        $domain = Domain::available($user->gid)->where('did', $data['did'])->first();
        if (!$domain) {
            return [false, '域名不存在，或无此权限', null];
        }

        if (!in_array($data['type'], $domain->record_type_list, true)) {
            return [false, '当前域名不支持此解析类型', null];
        }

        list($valueValid, $valueMessage) = Domain::validateRecordValue($data['type'], $data['value']);
        if (!$valueValid) {
            return [false, $valueMessage ?: '记录值格式不正确', null];
        }

        $dns = $domain->dnsConfig;
        if (!$dns) {
            return [false, '域名配置错误[No Config]', null];
        }

        $_dns = DnsHelper::getModel($dns->dns);
        if (!$_dns) {
            return [false, '域名配置错误[Unsupporte]', null];
        }

        $_dns->config($dns->config);
        foreach ($_dns->getRecordLine($domain->domain_id, $domain->domain) as $line) {
            if ((string)$line['Id'] === (string)$data['line_id']) {
                $data['line'] = $line['Name'];
                break;
            }
        }

        if ($respectReview && intval($domain->review_mode) === 1) {
            return $this->createReview($user, $domain, $record, $data, $source);
        }

        if ($id) {
            return $this->applyUpdate($user, $domain, $record, $data, $source);
        }

        return $this->applyCreate($user, $domain, $data, $source);
    }

    public function delete(User $user, $id, array $options = [])
    {
        $source = $options['source'] ?? 'web';
        $respectReview = array_key_exists('respect_review', $options) ? (bool)$options['respect_review'] : true;

        $record = DomainRecord::where('id', intval($id))->where('uid', $user->uid)->first();
        if (!$record) {
            return [false, '记录不存在', null];
        }

        $domain = $record->domain;
        if (!$domain) {
            return [false, '域名不存在', null];
        }

        if ($respectReview && intval($domain->review_mode) === 1) {
            $exists = DomainRecordReview::where('record_local_id', $record->id)->where('status', DomainRecordReview::STATUS_PENDING)->exists();
            if ($exists) {
                return [false, '该记录已有待审核申请', null];
            }

            DomainRecordReview::create([
                'uid' => $user->uid,
                'did' => $record->did,
                'record_local_id' => $record->id,
                'action' => DomainRecordReview::ACTION_DELETE,
                'payload' => json_encode([
                    'id' => $record->id,
                    'did' => $record->did,
                    'name' => $record->name,
                    'type' => $record->type,
                    'value' => $record->value,
                    'line_id' => $record->line_id,
                    'line' => $record->line,
                    'record_id' => $record->record_id,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => DomainRecordReview::STATUS_PENDING,
            ]);

            OperationLog::write('review.submit', "提交删除审核申请 [{$record->name}.{$domain->domain}]", [
                'uid' => $user->uid,
                'source' => $source,
                'target_type' => 'domain_record_review',
                'target_id' => $record->id,
                'extra' => ['action' => 'delete', 'domain' => $domain->domain],
            ]);

            return [true, '已提交审核，等待管理员处理', ['mode' => 'review']];
        }

        if (!Helper::deleteRecord($record)) {
            return [false, '删除记录失败，请稍后再试', null];
        }

        if ($record->delete()) {
            OperationLog::write('record.delete', "删除解析记录 [{$record->name}.{$domain->domain}]", [
                'uid' => $user->uid,
                'source' => $source,
                'target_type' => 'domain_record',
                'target_id' => $record->id,
                'extra' => ['domain' => $domain->domain],
            ]);

            return [true, '删除成功', ['mode' => 'direct']];
        }

        return [false, '删除失败，请稍后再试', null];
    }

    public function approveReview(DomainRecordReview $review, User $admin, $remark = '')
    {
        if (intval($review->status) !== DomainRecordReview::STATUS_PENDING) {
            return [false, '该审核单已处理', null];
        }

        $payload = $review->payload;
        $user = User::find($review->uid);
        $domain = Domain::find($review->did);
        if (!$user || !$domain) {
            return [false, '关联用户或域名不存在', null];
        }

        switch ($review->action) {
            case DomainRecordReview::ACTION_CREATE:
                list($ok, $message) = $this->applyCreate($user, $domain, $payload, 'review');
                break;
            case DomainRecordReview::ACTION_UPDATE:
                $record = DomainRecord::where('id', intval($review->record_local_id))->where('uid', $user->uid)->first();
                if (!$record) {
                    return [false, '待修改记录不存在', null];
                }
                list($ok, $message) = $this->applyUpdate($user, $domain, $record, $payload, 'review');
                break;
            case DomainRecordReview::ACTION_DELETE:
                $record = DomainRecord::where('id', intval($review->record_local_id))->where('uid', $user->uid)->first();
                if (!$record) {
                    return [false, '待删除记录不存在', null];
                }
                list($ok, $message) = $this->delete($user, $record->id, ['source' => 'review', 'respect_review' => false]);
                break;
            default:
                return [false, '不支持的审核动作', null];
        }

        if (!$ok) {
            return [false, $message, null];
        }

        $review->status = DomainRecordReview::STATUS_APPROVED;
        $review->review_remark = $remark;
        $review->reviewed_by = $admin->uid;
        $review->reviewed_at = time();
        $review->save();

        OperationLog::write('review.approve', "审核通过 [{$review->action_text}] #{$review->id}", [
            'uid' => $review->uid,
            'admin_uid' => $admin->uid,
            'source' => 'admin',
            'target_type' => 'domain_record_review',
            'target_id' => $review->id,
            'extra' => ['action' => $review->action, 'remark' => $remark],
        ]);

        return [true, '审核通过', null];
    }

    public function rejectReview(DomainRecordReview $review, User $admin, $remark = '')
    {
        if (intval($review->status) !== DomainRecordReview::STATUS_PENDING) {
            return [false, '该审核单已处理', null];
        }

        $review->status = DomainRecordReview::STATUS_REJECTED;
        $review->review_remark = $remark;
        $review->reviewed_by = $admin->uid;
        $review->reviewed_at = time();
        $review->save();

        OperationLog::write('review.reject', "审核驳回 [{$review->action_text}] #{$review->id}", [
            'uid' => $review->uid,
            'admin_uid' => $admin->uid,
            'source' => 'admin',
            'target_type' => 'domain_record_review',
            'target_id' => $review->id,
            'extra' => ['action' => $review->action, 'remark' => $remark],
        ]);

        return [true, '已驳回', null];
    }

    private function createReview(User $user, Domain $domain, $record, array $data, $source)
    {
        $action = $record ? DomainRecordReview::ACTION_UPDATE : DomainRecordReview::ACTION_CREATE;
        $recordLocalId = $record ? $record->id : 0;

        if ($action !== DomainRecordReview::ACTION_CREATE) {
            $exists = DomainRecordReview::where('uid', $user->uid)
                ->where('did', $domain->did)
                ->where('record_local_id', $recordLocalId)
                ->where('action', $action)
                ->where('status', DomainRecordReview::STATUS_PENDING)
                ->exists();
            if ($exists) {
                return [false, '已有待审核申请，请勿重复提交', null];
            }
        }

        DomainRecordReview::create([
            'uid' => $user->uid,
            'did' => $domain->did,
            'record_local_id' => $recordLocalId,
            'action' => $action,
            'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => DomainRecordReview::STATUS_PENDING,
        ]);

        OperationLog::write('review.submit', "提交{$action}审核申请 [{$data['name']}.{$domain->domain}]", [
            'uid' => $user->uid,
            'source' => $source,
            'target_type' => 'domain_record_review',
            'target_id' => $recordLocalId,
            'extra' => ['action' => $action, 'domain' => $domain->domain],
        ]);

        return [true, '已提交审核，等待管理员处理', ['mode' => 'review']];
    }

    private function applyCreate(User $user, Domain $domain, array $data, $source, $writeLog = true)
    {
        $dns = $domain->dnsConfig;
        $_dns = DnsHelper::getModel($dns->dns);
        $_dns->config($dns->config);

        if ($domain->point > 0 && $user->point < $domain->point) {
            return [false, '账户剩余积分不足', null];
        }

        list($ret, $error) = $_dns->addDomainRecord($data['name'], $data['type'], $data['value'], $data['line_id'], $domain->domain_id, $domain->domain);
        if (!$ret) {
            return [false, '添加记录失败:' . $error, null];
        }

        if ($domain->point > 0 && !User::point($user->uid, '消费', 0 - $domain->point, "添加记录[{$data['name']}.{$domain->domain}]({$data['line']})")) {
            $_dns->deleteDomainRecord($ret['RecordId'], $domain->domain_id, $domain->domain);
            return [false, '账户剩余积分不足', null];
        }

        $data['record_id'] = $ret['RecordId'];
        if (DomainRecord::create($data)) {
            if ($writeLog) {
                OperationLog::write('record.create', "添加解析记录 [{$data['name']}.{$domain->domain}]", [
                    'uid' => $user->uid,
                    'source' => $source,
                    'target_type' => 'domain_record',
                    'target_id' => $ret['RecordId'],
                    'extra' => ['domain' => $domain->domain],
                ]);
            }

            return [true, '添加成功', ['mode' => 'direct']];
        }

        $_dns->deleteDomainRecord($ret['RecordId'], $domain->domain_id, $domain->domain);

        return [false, '添加失败，请稍后再试', null];
    }

    private function applyUpdate(User $user, Domain $domain, DomainRecord $record, array $data, $source, $writeLog = true)
    {
        $dns = $domain->dnsConfig;
        $_dns = DnsHelper::getModel($dns->dns);
        $_dns->config($dns->config);

        list($ret, $error) = $_dns->updateDomainRecord($record->record_id, $data['name'], $data['type'], $data['value'], $data['line_id'], $domain->domain_id, $domain->domain);
        if (!$ret) {
            return [false, '更新记录失败:' . $error, null];
        }

        if (DomainRecord::where('id', $record->id)->update($data)) {
            if ($writeLog) {
                OperationLog::write('record.update', "修改解析记录 [{$data['name']}.{$domain->domain}]", [
                    'uid' => $user->uid,
                    'source' => $source,
                    'target_type' => 'domain_record',
                    'target_id' => $record->id,
                    'extra' => ['domain' => $domain->domain],
                ]);
            }

            return [true, '更新成功', ['mode' => 'direct']];
        }

        return [false, '更新失败，请稍后再试', null];
    }
}
