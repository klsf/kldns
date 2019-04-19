<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:30
 */

namespace App\Models;
class Model extends \Illuminate\Database\Eloquent\Model
{
    public function fromDateTime($value)
    {
        return strtotime(parent::fromDateTime($value));
    }

    public function scopePageSelect($query)
    {
        $pageSize = intval(request()->post('pageSize'));
        $pageSize = ($pageSize < 10 || $pageSize > 200) ? 10 : $pageSize;

        return $query->paginate($pageSize);
    }

    public function scopePageList($query)
    {
        $pageSize = intval(request()->post('pageSize'));
        $pageSize = ($pageSize < 10 || $pageSize > 200) ? 10 : $pageSize;
        $page = intval(request()->post('page'));
        $page = $page > 1 ? $page : 1;
        $query->offset(($page - 1) * $pageSize)->limit($pageSize);
        return $query->get();
    }

    public function scopeToday($query)
    {
        $query->whereRaw("created_at >= UNIX_TIMESTAMP(CURDATE())");
    }

}