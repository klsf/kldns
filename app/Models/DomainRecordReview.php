<?php

namespace App\Models;

class DomainRecordReview extends Model
{
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    protected $appends = ['status_text', 'action_text', 'reviewed_at_text'];

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    public function getPayloadAttribute()
    {
        $value = $this->attributes['payload'] ?? '';
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getStatusTextAttribute()
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已驳回',
        ][$this->attributes['status'] ?? self::STATUS_PENDING] ?? '未知';
    }

    public function getActionTextAttribute()
    {
        return [
            self::ACTION_CREATE => '新增',
            self::ACTION_UPDATE => '修改',
            self::ACTION_DELETE => '删除',
        ][$this->attributes['action'] ?? self::ACTION_CREATE] ?? '未知';
    }

    public function getReviewedAtTextAttribute()
    {
        return !empty($this->attributes['reviewed_at']) ? date('Y-m-d H:i:s', intval($this->attributes['reviewed_at'])) : '-';
    }

    public function scopeSearch($query, $guard = 'web')
    {
        $query->with([
            'domain' => function ($builder) {
                $builder->select(['did', 'domain', 'record_types', 'review_mode', 'beian']);
            },
            'user' => function ($builder) {
                $builder->select(['uid', 'username']);
            },
            'reviewer' => function ($builder) {
                $builder->select(['uid', 'username']);
            },
        ]);

        $status = request()->post('status');
        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', intval($status));
        }

        $action = request()->post('review_action');
        if ($action) {
            $query->where('action', $action);
        }

        $did = intval(request()->post('did'));
        if ($did) {
            $query->where('did', $did);
        }

        if ($guard === 'admin') {
            $uid = intval(request()->post('uid'));
            if ($uid) {
                $query->where('uid', $uid);
            }
        } else {
            $query->where('uid', auth()->id());
        }
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'did', 'did');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'uid');
    }
}
