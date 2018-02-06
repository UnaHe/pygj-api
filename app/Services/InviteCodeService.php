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
        $inviteCodes =  InviteCode::where('user_id', $userId)->select(["invite_code", "status"])->orderBy("status","asc")->get();

        foreach ($inviteCodes as &$inviteCode){
            if($inviteCode['status'] == InviteCode::STATUS_UNUSE){
                $inviteCode['invite_code'] = preg_replace("/^(\w)\w+?(\w)$/", "$1****$2",$inviteCode['invite_code']);
            }
        }
        return $inviteCodes;
    }

    /**
     * 申请邀请码
     * @param $userId
     * @param $types
     * @param $num
     * @throws \Exception
     */
    public function appInviteCode($userId, $types, $num){
        try{
            // 我的用户信息.
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade'])->toArray();

            // 生成订单字段.
            switch ($types){
                case -1:
                    $subtype = 14;
                    break;
                case 30:
                    $subtype = 11;
                    break;
                case 90:
                    $subtype = 12;
                    break;
                case 365:
                    $subtype = 13;
                    break;
                case 5:
                    $subtype = 15;
                    break;
            }

            $type = Order::ORDER_APPLY;
            $user_phone  = $user['phone'];
            $user_name = $user['user_info']['actual_name'];
            $user_grade = $user['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $total_price = $unit_price * $num;
            $status = 1;

            // 创建申请订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $userId,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'status' => $status,
                'date' => date('Y-m-d'),
            ]);

            if(!$res){
                throw new \LogicException('申请邀请码失败');
            }
        }catch (\Exception $e){
            $error = $e instanceof \LogicException ? $e->getMessage() : '申请邀请码失败';
            throw new \Exception($error);
        }
    }

    /**
     * 派发邀请码列表
     * @param $userId
     * @return mixed
     */
    public function sendInviteList($userId){
        // 查询可用邀请码.
        $data =  InviteCode::where([
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

    /**
     * 转让邀请码
     * @param $userId
     * @param $code
     * @param $types
     * @param $toName
     * @param $toPhone
     * @return mixed
     * @throws \Exception
     */
    public function transfer($userId, $code, $types, $toName, $toPhone){
        try{
            $InviteCode = new InviteCode();

            // 码信息.
            $codes = explode(',', $code);
            $codeInfo = $InviteCode->whereIn('invite_code', $codes)->where([
                'user_id'=> $userId,
                'status' => InviteCode::STATUS_UNUSE
            ])->select('invite_code')->get();
            $codeInfoArray = $codeInfo->toArray();
            if(!$codeInfoArray){
                throw new \LogicException('邀请码错误');
            }
            $codeArray = [];
            $codeRemark = '';
            $num = 0;
            foreach ($codeInfoArray as $k=>$v) {
                $codeArray[] = $v['invite_code'];
                $codeRemark .= $v['invite_code'].',';
                $num++;
            }

            // 用户信息.
            $toUser = User::where('phone', $toPhone)->with('UserInfo')->first(['id','path', 'grade']);

            if(!$toUser){
                throw new \LogicException('转让用户不存在');
            }
            $toUserArray = $toUser->toArray();
            $toUserPath = explode(':', $toUserArray['path']);
            $toUserName = $toUserArray['user_info']['actual_name'];

            if(!in_array($userId, $toUserPath)){
                throw new \LogicException('转让用户不是您的推客');
            }

            if(empty($toUserName) || $toUserName != $toName){
                throw new \LogicException('真实姓名不正确或对方未使用管家');
            }

            // 生成订单字段.
            switch ($types){
                case 30:
                    $subtype = 51;
                    break;
                case 90:
                    $subtype = 52;
                    break;
                case 365:
                    $subtype = 53;
                    break;
                case -1:
                    $subtype = 54;
                    break;
                case 5:
                    $subtype = 55;
                    break;
            }

            $type = Order::ORDER_TRANSFER;
            $user_id  = $toUserArray['id'];
            $user_phone  = $toPhone;
            $user_name = $toUserName;
            $user_grade = $toUserArray['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $total_price = $unit_price * $num;
            $status = 100;

            // 创建申请订单.
            DB::beginTransaction();
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $userId,
                'user_id' => $user_id,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'status' => $status,
                'remark' => rtrim($codeRemark, ','),
                'date' => date('Y-m-d'),
            ]);

            if($res){
                $InviteCode->whereIn('invite_code', $codeArray)->select('id')->update(['user_id' => $user_id]);
                if ($subtype === 54) {
                    Redis::lpush(Order::REDIS_QUEUE, $res);
                }
            } else {
                DB::rollBack();
                throw new \LogicException('转让邀请码失败');
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            $error = $e instanceof \LogicException ? $e->getMessage() : '转让邀请码失败';
            throw new \Exception($error);
        }
    }

    /**
     * 续费
     * @param $userId
     * @param $phone
     * @param $types
     * @throws \Exception
     */
    public function renewFee($userId, $phone, $types){
        try{
            $User = new User();
            $InviteCode = new InviteCode();

            // 用户信息.
            $member = $User->where('phone', $phone)->with('UserInfo')->first(['id', 'phone', 'invite_code', 'grade']);

            if(!$member){
                throw new \LogicException('用户不存在');
            }
            $member = $member->toArray();
            $member_id = $member['id'];
            $memberCode = $member['invite_code'];

            // 是否自己续费.
            if(!($member_id == $userId)){
                $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
                    'invite.user_id' => $userId,
                    'invite.status' => InviteCode::STATUS_USED
                ]);
                $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
                $query->select("user.id");

                //我的推客
                $members = $query->get()->toArray();
                foreach($members as $k=>$v){
                    foreach($v as $key=>$val){
                        $new_arr[] = $val;
                    }
                }
                if(!in_array($member_id, $new_arr)){
                    throw new \LogicException('申请用户不是您的推客');
                }
            }

            // 当前邀请码级别.
            $memberType = $InviteCode->where('invite_code', $memberCode)->pluck('effective_days')->first();

            // 生成订单字段.
            switch ($memberType){
                case -1:
                    throw new \LogicException('用户已经是终身用户');
                    break;
                case 5 :
                    throw new \LogicException('试用用户不支持续费');
                    break;
                case 30:
                    switch ($types){
                        case 30:
                            $subtype = 21;
                            break;
                        case 90:
                            $subtype = 22;
                            break;
                        case 365:
                            $subtype = 23;
                            break;
                    }
                    break;
                case 90:
                    switch ($types){
                        case 30:
                            $subtype = 24;
                            break;
                        case 90:
                            $subtype = 25;
                            break;
                        case 365:
                            $subtype = 26;
                            break;
                    }
                    break;
                case 365:
                    switch ($types){
                        case 30:
                            $subtype = 27;
                            break;
                        case 90:
                            $subtype = 28;
                            break;
                        case 365:
                            $subtype = 29;
                            break;
                    }
                    break;
            }

            $type = Order::ORDER_RENEWFEE;
            $num = 1;
            $member_phone  = $member['phone'];
            $member_name = $member['user_info']['actual_name'];
            $member_grade = $member['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $total_price = $unit_price * $num;
            $status = 1;

            // 创建续费订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $userId,
                'user_id' => $member_id,
                'user_phone' => $member_phone,
                'user_name' => $member_name,
                'user_grade' => $member_grade,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'status' => $status,
                'remark' => $memberCode,
                'date' => date('Y-m-d'),
            ]);

            if(!$res){
                throw new \LogicException('续费失败');
            }
        }catch (\Exception $e){
            $error = $e instanceof \LogicException ? $e->getMessage() : '续费失败';
            throw new \Exception($error);
        }
    }

    /**
     * 升级终身码
     * @param $userId
     * @param $newCode
     * @throws \Exception
     */
    public function upVip($userId, $newCode){
        try{
            $InviteCode = new InviteCode();

            // 我的用户信息.
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'invite_code', 'grade']);
            $userArr = $user->toArray();
            $userOldCode = $userArr['invite_code'];

            // 当前邀请码级别.
            $userCode = $InviteCode->where([
                'invite_code' => $userOldCode
            ])->first()->toArray();

            $userCodeType = $userCode['effective_days'];

            if($userCodeType == '-1'){
                throw new \LogicException('用户已经是终身用户');
            }

            // 验证邀请码有效性.
            $isCode = $InviteCode->where([
                'invite_code' => $newCode,
                'user_id' => $userCode['user_id'],
                'status' => InviteCode::STATUS_UNUSE,
                ['effective_days', '<>', 5]
            ])->first();

            if(!$isCode){
                throw new \LogicException('邀请码错误');
            }

            // 用户过期时间.
            $expiryTime = strtotime($userArr['expiry_time']);
            if($expiryTime){
                $year = date("Y", $expiryTime);
                $month = date("m", $expiryTime);
                $day = date("d", $expiryTime);
            }else{
                $year = date("Y");
                $month = date("m");
                $day = date("d");
            }
            $endTime = mktime(23,59,59,$month,$day,$year);

            // 新邀请码级别.
            $newCodeType = $isCode['effective_days'];

            // 生成订单字段.
            switch ($userCodeType){
                case 5:
                    switch ($newCodeType){
                        case 30:
                            $subtype = 301;
                            $remark = "原始试用[{$userOldCode}]升级为月码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 2592000);
                            break;
                        case 90:
                            $subtype = 302;
                            $remark = "原始试用[{$userOldCode}]升级为季码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 7776000);
                            break;
                        case 365:
                            $subtype = 303;
                            $remark = "原始试用[{$userOldCode}]升级为年码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 31536000);
                            break;
                        case -1:
                            $subtype = 304;
                            $remark = "原始试用[{$userOldCode}]升级为终码[{$newCode}]";
                            $newEndTime = NULL;
                            break;
                    }
                    break;
                case 30:
                    switch ($newCodeType){
                        case 30:
                            $subtype = 305;
                            $remark = "原始月码[{$userOldCode}]升级为月码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 2592000);
                            break;
                        case 90:
                            $subtype = 306;
                            $remark = "原始月码[{$userOldCode}]升级为季码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 7776000);
                            break;
                        case 365:
                            $subtype = 307;
                            $remark = "原始月码[{$userOldCode}]升级为年码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 31536000);
                            break;
                        case -1:
                            $subtype = 308;
                            $remark = "原始月码[{$userOldCode}]升级为终码[{$newCode}]";
                            $newEndTime = NULL;
                            break;
                    }
                    break;
                case 90:
                    switch ($newCodeType){
                        case 30:
                            $subtype = 309;
                            $remark = "原始季码[{$userOldCode}]升级为月码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 2592000);
                            break;
                        case 90:
                            $subtype = 310;
                            $remark = "原始季码[{$userOldCode}]升级为季码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 7776000);
                            break;
                        case 365:
                            $subtype = 311;
                            $remark = "原始季码[{$userOldCode}]升级为年码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 31536000);
                            break;
                        case -1:
                            $subtype = 312;
                            $remark = "原始季码[{$userOldCode}]升级为终码[{$newCode}]";
                            $newEndTime = NULL;
                            break;
                    }
                    break;
                case 365:
                    switch ($newCodeType){
                        case 30:
                            $subtype = 313;
                            $remark = "原始年码[{$userOldCode}]升级为月码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 2592000);
                            break;
                        case 90:
                            $subtype = 314;
                            $remark = "原始年码[{$userOldCode}]升级为季码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 7776000);
                            break;
                        case 365:
                            $subtype = 315;
                            $remark = "原始年码[{$userOldCode}]升级为年码[{$newCode}]";
                            $newEndTime = date('Y-m-d H:i:s', $endTime + 31536000);
                            break;
                        case -1:
                            $subtype = 316;
                            $remark = "原始年码[{$userOldCode}]升级为终码[{$newCode}]";
                            $newEndTime = NULL;
                            break;
                    }
                    break;
            }
            $type = Order::ORDER_UPVIP;
            $number = 1;
            $user_phone  = $userArr['phone'];
            $user_name = $userArr['user_info']['actual_name'];
            $user_grade = $userArr['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $newCodeType)->pluck('code_price')->first();
            $status = 100;

            // 创建升级订单.
            DB::beginTransaction();
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $number,
                'target_user_id' => $userId,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $unit_price,
                'status' => $status,
                'remark' => $remark,
                'date' => date('Y-m-d'),
            ]);

            if($res){
                $user->invite_code = $newCode;
                $user->expiry_time = $newEndTime;
                $user->save();
                $isCode->status = InviteCode::STATUS_USED;
                $isCode->update_time = date('Y-m-d H:i:s');
                $isCode->save();
            } else {
                DB::rollBack();
                throw new \LogicException('升级失败');
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            $error = $e instanceof \LogicException ? $e->getMessage() : '升级失败';
            throw new \Exception($error);
        }
    }

}
