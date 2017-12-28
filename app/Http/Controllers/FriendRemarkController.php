<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FriendRemarkService;

class FriendRemarkController extends Controller
{
    public function setRemark(Request $request){
        // 获取信息.
        $userId = $request->user()->id;
        $friend_user_id = $request->input('friend_user_id');
        $remark = $request->input('remark');

        if(!$friend_user_id){
            return $this->ajaxError("参数错误");
        }

        // 执行插入备注表.
        try{
            (new FriendRemarkService())->setRemark($userId, $friend_user_id, $remark);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }
}
