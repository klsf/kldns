<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:34
 */

namespace App\Models;


class DnsConfig extends Model
{
    protected $primaryKey = 'dns';
    public $incrementing = false;
    protected $guarded = [];

    public function getConfigAttribute()
    {
        $value = json_decode($this->attributes['config'], true);
        return $value ? $value : [];
    }
}