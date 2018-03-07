<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户信息表
 * Class UserInfo
 * @package App\Models
 */
class UserInfo extends Model
{
    protected $table = "xmt_pygj_user_info";
    protected $guarded = ['id'];
    public $timestamps = false;
}
