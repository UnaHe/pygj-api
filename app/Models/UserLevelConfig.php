<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户等级配置表
 * Class UserLevelConfig
 * @package App\Models
 */
class UserLevelConfig extends Model
{
    protected $table = "xmt_user_level_config";
    public $timestamps = false;

    protected $guarded = ['id'];
}
