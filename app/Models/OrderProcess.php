<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 订单记录表
 * Class OrderProcess
 * @package App\Models
 */
class OrderProcess extends Model
{
    protected $table = "xmt_pygj_order_process";
    protected $guarded = ['id'];
}
