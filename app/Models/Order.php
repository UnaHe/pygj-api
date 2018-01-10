<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 订单表
 * Class Order
 * @package App\Models
 */
class Order extends Model
{
    protected $table = "xmt_pygj_order";
    protected $guarded = ['id'];
}
