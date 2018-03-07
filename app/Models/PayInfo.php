<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 支付信息
 * Class PayInfo
 * @package App\Models
 */
class PayInfo extends Model
{
    protected $table = "xmt_pygj_pay_info";
    protected $guarded = ['id'];
    public $timestamps = false;
}
