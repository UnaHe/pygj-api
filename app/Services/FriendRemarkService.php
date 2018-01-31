<?php

namespace App\Services;

use App\Models\FriendRemark;
use Illuminate\Support\Facades\Cache;

class FriendRemarkService{
    /**
     * 设置备注
     * @param $userId
     * @param $friend_user_id
     * @param $remark
     * @throws \Exception
     */
    public function setRemark($userId, $friend_user_id, $remark){
        try{
            $FriendRemark = new FriendRemark();
            $data = $FriendRemark->where(['user_id' => $userId, 'friend_user_id' => $friend_user_id])->first();
            if($data == NULL){
                $res = $FriendRemark->create([
                    'user_id' => $userId,
                    'friend_user_id' => $friend_user_id,
                    'remark' => $remark
                ]);
            } else {
                $res = $data->update(['remark' => $remark]);
            }
            Cache::forget('App\Services\UserService::getMyMember:8a9f8f9fe782b7cf71641ad84976113a');
            if(!$res){
                throw new \LogicException('设置备注失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '设置备注失败';
            }
            throw new \Exception($error);
        }
    }
}