<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户收入表
 * Class UserIncome
 * @package App\Models
 */
class UserIncome extends Model
{
    protected $table = "xmt_pygj_user_income";
    protected $guarded = ['id'];
}
