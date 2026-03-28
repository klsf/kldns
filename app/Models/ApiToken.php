<?php

namespace App\Models;

class ApiToken extends Model
{
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    protected $appends = ['last_used_at_text', 'expires_at_text'];

    public function scopeSearch($query, $guard = 'web')
    {
        if ($guard === 'web') {
            $query->where('uid', auth()->id());
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    public function getLastUsedAtTextAttribute()
    {
        return !empty($this->attributes['last_used_at']) ? date('Y-m-d H:i:s', intval($this->attributes['last_used_at'])) : '-';
    }

    public function getExpiresAtTextAttribute()
    {
        return !empty($this->attributes['expires_at']) ? date('Y-m-d H:i:s', intval($this->attributes['expires_at'])) : '不过期';
    }
}
