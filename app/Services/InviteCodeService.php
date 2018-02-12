<?php
/**
 * Created by PhpStorm.
 * User: yangtao
 * Date: 2017/10/18
 * Time: 15:51
 */
namespace App\Services;

use App\Helpers\CacheHelper;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\Order;
use App\Models\CodePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class InviteCodeService{
    /**
     * 获取未使用的邀请码数量
     * @param $userId
     * @return mixed
     * @throws \Exception
     */
    public function getUnUseInviteCodeNum($userId){
        if($data = CacheHelper::getCache()){
            return $data;
        }

        $data = InviteCode::where([
            'user_id' => $userId,
            'status' => InviteCode::STATUS_UNUSE
        ])->count();
        CacheHelper::setCache($data, 1);

        return $data;
    }

    /**
     * 获取邀请码列表
     * @param $userId
     * @return mixed
     */
    public function getListByUserId($userId){
        $inviteCodes = InviteCode::where('user_id', $userId)->select(["invite_code", "status"])->orderBy("status","asc")->get();

        foreach ($inviteCodes as &$inviteCode){
            if($inviteCode['status'] == InviteCode::STATUS_UNUSE){
                $inviteCode['invite_code'] = preg_replace("/^(\w)\w+?(\w)$/", "$1****$2",$inviteCode['invite_code']);
            }
        }
        return $inviteCodes;
    }

    /**
     * 派发邀请码列表
     * @param $userId
     * @return mixed
     */
    public function sendInviteList($userId){
        // 查询可用邀请码.
        $data = InviteCode::where([
            'user_id' => $userId,
            'status' => InviteCode::STATUS_UNUSE
        ])->select(['effective_days','invite_code'])->get();

        $result = [];
        foreach($data as $k=>$v){
            $result[$v['effective_days']][] = $v['invite_code'];
        }

        $num = 0;
        $str = '';
        $res = [];
        $kk = 0;
        foreach($result as $k=>$v){
            foreach($v as $key=>$vel){
                $num++;
                $str .= $vel.',';
            }
            $res[$kk]['effective_days'] = $k;
            $res[$kk]['invite_code'] = rtrim($str, ',');
            $res[$kk]['num'] = $num;
            $str = '';
            $num = 0;
            $kk++;
        }

        return $res;
    }

}
