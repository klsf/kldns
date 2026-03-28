<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:36
 */

namespace App\Models;


class UserGroup extends Model
{
    protected $primaryKey = 'gid';
    protected $guarded = ['gid'];
}