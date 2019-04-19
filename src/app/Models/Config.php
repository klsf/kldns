<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/15
 * Time: 13:23
 */

namespace App\Models;


class Config extends Model
{
    protected $primaryKey = 'k';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;

}