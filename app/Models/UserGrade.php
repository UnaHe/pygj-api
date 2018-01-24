<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户等级表
 * Class UserInfo
 * @package App\Models
 */
class UserGrade extends Model
{
    protected $table = "xmt_user_grade";
    protected $guarded = ['id'];
    public $timestamps = false;
}
