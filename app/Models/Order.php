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

    /**
     * 1:学员位申请订单
     */
    const ORDER_APPLY = 1;

    /**
     * 2:续费订单
     */
    const ORDER_RENEWFEE = 2;

    /**
     * 3:升级VIP订单
     */
    const ORDER_UPVIP = 3;
}
