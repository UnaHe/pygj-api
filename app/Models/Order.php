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

    /**
     * 4:提现订单
     */
    const ORDER_EXTRACT = 4;

    // 订单类别.
    static $order_type = [
        1 => '我的招募申请',
        2 => '学员变更申请',
        3 => '学员变更申请'
    ];

    // 订单子类别.
    static $order_subtype = [
        11 => '月付',
        12 => '季付',
        13 => '年付',
        14 => 'VIP',
        15 => '试用',
        21 => '月续月',
        22 => '月续季',
        23 => '月续年',
        24 => '季续月',
        25 => '季续季',
        26 => '季续年',
        27 => '年续月',
        28 => '年续季',
        29 => '年续年',
        201 => '试用续月',
        202 => '试用续季',
        203 => '试用续年',
        31 => '月升级VIP',
        32 => '季升级VIP',
        33 => '年升级VIP',
        34 => '试用升级VIP'
    ];

    // 订单状态.
    static $order_status = [
        -1 => '已驳回',
        1 => '待审核',
        99 => '审核中',
        100 => '完成'
    ];

}
