<?php

namespace App\Services;

use App\Models\FriendRemark;

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
            $data = FriendRemark::where(['user_id' => $userId, 'friend_user_id' => $friend_user_id])->first();
            if($data == NULL){
                $res = FriendRemark::create([
                    'user_id' => $userId,
                    'friend_user_id' => $friend_user_id,
                    'remark' => $remark
                ]);
            } else {
                $res = $data->update(['remark' => $remark]);
            }
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