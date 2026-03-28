<?php

namespace App\Models;

class OperationLog extends Model
{
    protected $primaryKey = 'id';
    protected $guarded = ['id'];

    public function getExtraAttribute()
    {
        $value = $this->attributes['extra'] ?? '';
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function write($action, $message, array $context = [])
    {
        return static::create([
            'uid' => intval($context['uid'] ?? 0),
            'admin_uid' => intval($context['admin_uid'] ?? 0),
            'source' => (string)($context['source'] ?? 'system'),
            'target_type' => (string)($context['target_type'] ?? ''),
            'target_id' => (string)($context['target_id'] ?? ''),
            'ip' => (string)($context['ip'] ?? request()->ip()),
            'action' => (string)$action,
            'message' => (string)$message,
            'extra' => json_encode($context['extra'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function scopeSearch($query)
    {
        $action = request()->post('action_name');
        if ($action) {
            $query->where('action', $action);
        }

        $source = request()->post('source');
        if ($source) {
            $query->where('source', $source);
        }

        $uid = intval(request()->post('uid'));
        if ($uid) {
            $query->where('uid', $uid);
        }

        $adminUid = intval(request()->post('admin_uid'));
        if ($adminUid) {
            $query->where('admin_uid', $adminUid);
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_uid', 'uid');
    }
}
