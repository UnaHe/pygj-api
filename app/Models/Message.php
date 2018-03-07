<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 系统消息
 * Class Message
 * @package App\Models
 */
class Message extends Model
{
    protected $table = "xmt_pygj_message";
    protected $guarded = ['id'];
}
