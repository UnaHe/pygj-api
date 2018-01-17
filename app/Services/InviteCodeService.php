<?php
/**
 * Created by PhpStorm.
 * User: yangtao
 * Date: 2017/10/18
 * Time: 15:51
 */
namespace App\Services;

use App\Models\InviteCode;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Message;
use App\Models\Order;
use App\Models\CodePrice;

class InviteCodeService{
    /**
     * 获取未使用的邀请码数量
     * @param $userId
     * @return mixed
     */
    public function getUnUseInviteCodeNum($userId){
        return InviteCode::where([
            'user_id' => $userId,
            'status' => InviteCode::STATUS_UNUSE
        ])->count();
    }

    /**
     * 获取邀请码列表
     * @param $userId
     * @return mixed
     */
    public function getListByUserId($userId){
        $inviteCodes =  InviteCode::where([
            'user_id' => $userId
        ])->select(["invite_code", "status"])->orderBy("status","asc")->get();

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
            $user = User::where("id", $userId)->with('UserInfo')->first()->toArray();

            // 生成订单字段.
            switch ($types){
                case -1:
                    $subtype = 14;
                    $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                    break;
                case 30:
                    $subtype = 11;
                    $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                    break;
                case 90:
                    $subtype = 12;
                    $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                    break;
                case 365:
                    $subtype = 13;
                    $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                    break;
                case 5:
                    $subtype = 15;
                    $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                    break;
            }

            $type = Order::ORDER_APPLY;
            $user_phone  = $user['phone'];
            $user_name = $user['user_info']['actual_name'];
            $user_grade = $user['grade'] ? : 1;
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
            ]);

            if(!$res){
                throw new \LogicException('申请邀请码失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '申请邀请码失败';
            }
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
        ])->select(DB::raw('effective_days, GROUP_CONCAT(invite_code)as list, count(*) as num'))->groupBy('effective_days')->get();

        // 计算邀请码级别.
        foreach ($data as $k=>$v){
            switch ($v['effective_days']){
                case -1:
                    $data[$k]['level'] = 'VIP';
                    break;
                case 30:
                    $data[$k]['level'] = '月付';
                    break;
                case 90:
                    $data[$k]['level'] = '季付';
                    break;
                case 365:
                    $data[$k]['level'] = '年付';
                    break;
            }
        }

        return $data;
    }

    /**
     * 续费
     * @param $userId
     * @param $phone
     * @param $type
     * @throws \Exception
     */
    public function renewFee($userId, $phone, $types){
        try{
            $User = new User();
            $InviteCode = new InviteCode();

            // 用户信息.
            $member = $User->where('phone', $phone)->with('UserInfo')->first()->toArray();

            if(!$member){
                throw new \LogicException('用户不存在');
            }
            $member_id = $member['id'];
            $memberCode = $member['invite_code'];

            // 是否自己续费.
            if(!$member_id == $userId){
                $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
                    'invite.user_id' => $userId,
                    'invite.status' => InviteCode::STATUS_USED
                ]);
                $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
                $query->select("user.id");

                //我的学员
                $members = $query->get()->toArray();
                foreach($members as $k=>$v){
                    foreach($v as $key=>$val){
                        $new_arr[] = $val;
                    }
                }
                if(!in_array($member_id, $new_arr)){
                    throw new \LogicException('申请用户不是您的学员');
                }
            }

            // 当前邀请码级别.
            $memberType = $InviteCode->where(['invite_code' => $memberCode])->pluck('effective_days')->first();

            if($memberType == '-1'){
                throw new \LogicException('用户已经是VIP');
            }

            // 生成订单字段.
            switch ($memberType){
                case 30:
                    switch ($types){
                        case 30:
                            $subtype = 21;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 90:
                            $subtype = 22;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 365:
                            $subtype = 23;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                    }
                    break;
                case 90:
                    switch ($types){
                        case 30:
                            $subtype = 24;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 90:
                            $subtype = 25;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 365:
                            $subtype = 26;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                    }
                    break;
                case 365:
                    switch ($types){
                        case 30:
                            $subtype = 27;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 90:
                            $subtype = 28;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 365:
                            $subtype = 29;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                    }
                    break;
                case 5:
                    switch ($types){
                        case 30:
                            $subtype = 201;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 90:
                            $subtype = 202;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                        case 365:
                            $subtype = 203;
                            $unit_price = CodePrice::where(['duration' => $types])->pluck('code_price')->first();
                            break;
                    }
                    break;
            }

            $type = Order::ORDER_RENEWFEE;
            $num = 1;
            $member_phone  = $member['phone'];
            $member_name = $member['user_info']['actual_name'];
            $member_grade = $member['grade'] ? : 1;
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
            ]);

            if(!$res){
                throw new \LogicException('续费失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '续费失败';
            }
            throw new \Exception($error);
        }
    }

    /**
     * 升级VIP
     * @param $userId
     * @param $phone
     * @throws \Exception
     */
    public function upVip($userId, $newCode){
        try{
            $InviteCode = new InviteCode();

            // 我的用户信息.
            $user = User::where("id", $userId)->with('UserInfo')->first();
            $userArr = $user->toArray();
            $userCode = $userArr['invite_code'];

            // 当前邀请码级别.
            $userType = $InviteCode->where([
                'invite_code' => $userCode
            ])->first()->toArray();

            if($userType['effective_days'] == '-1'){
                throw new \LogicException('用户已经是VIP');
            }

            // 验证邀请码有效性.
            $isCode = $InviteCode->where([
                'invite_code' => $newCode,
                'user_id' => $userType['user_id'],
                'status' => InviteCode::STATUS_UNUSE,
                'effective_days' => -1,
            ])->first();

            if(!$isCode){
                throw new \LogicException('邀请码错误');
            }

            // 生成订单字段.
            switch ($userType['effective_days']){
                case 30:
                    $subtype = 31;
                    break;
                case 90:
                    $subtype = 32;
                    break;
                case 365:
                    $subtype = 33;
                    break;
                case 5:
                    $subtype = 34;
                    break;
            }
            $type = Order::ORDER_UPVIP;
            $number = 1;
            $user_phone  = $user['phone'];
            $user_name = $user['user_info']['actual_name'];
            $user_grade = $user['grade'] ? : 1;
            $unit_price = CodePrice::where(['duration' => '-1'])->pluck('code_price')->first();
            $status = 100;

            // 创建升级订单.
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
                'remark' => $newCode,
            ]);

            if($res){
                $user->invite_code = $newCode;
                $user->expiry_time = NUll;
                $user->save();
                $isCode->status = InviteCode::STATUS_USED;
                $isCode->update_time = date('Y-m-d H:i:s');
                $isCode->save();
            } else {
                throw new \LogicException('升级VIP失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '升级VIP失败';
            }
            throw new \Exception($error);
        }
    }

}
