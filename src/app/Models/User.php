<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    protected $primaryKey = 'uid';
    protected $guarded = ['uid'];
    protected $hidden = ['password', 'sid', 'remember_token'];

    public function fromDateTime($value)
    {
        return strtotime(parent::fromDateTime($value));
    }

    public function scopeSearch($query)
    {
        $query->with(['group' => function ($query) {
            $query->select(['gid', 'name']);
        }]);
        $gid = intval(request()->post('gid'));
        if ($gid) $query->where('gid', $gid);
        $username = request()->post('username');
        if ($username) $query->where('username', $username);
        $uid = request()->post('uid');
        if ($uid) $query->where('uid', $uid);
        $email = request()->post('email');
        if ($email) $query->where('email', $email);
    }

    public function scopePageSelect($query)
    {
        $pageSize = intval(request()->post('pageSize'));
        $pageSize = ($pageSize < 10 || $pageSize > 200) ? 10 : $pageSize;

        return $query->paginate($pageSize);
    }

    public function scopeToday($query)
    {
        $query->whereRaw("created_at >= UNIX_TIMESTAMP(CURDATE())");
    }

    public static function point($uid, $action, $point, $remark = null)
    {
        if ($uid && $user = static::find($uid)) {
            if ($point < 0 && abs($point) > $user->point) {
                return false;
            }
            if ($user->increment('point', $point)) {
                UserPointRecord::create([
                    'uid' => $uid,
                    'action' => $action,
                    'point' => $point,
                    'rest' => $user->point,
                    'remark' => $remark
                ]);
                return true;
            }
        }
        return false;
    }

    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'gid', 'gid');
    }
}
