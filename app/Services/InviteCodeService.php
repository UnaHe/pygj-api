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
            $user = User::where("id", $userId)->first();
//            $grade = $user['grade'] ? : 1;

            // 邀请码价格.
//            $price = json_decode((new SysConfigService())->get('invite_code_price'), true);

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
            }

            $type = Order::ORDER_APPLY;
            $user_phone  = $user['phone'];
            $user_name = $user['actual_name'];
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
     * 派发邀请码
     * @param $userId
     * @param $validity
     * @return mixed
     */
    public function sendInviteCode($userId, $validity){
        // 获得可用邀请码.
        $data =  InviteCode::where([
            'user_id' => $userId,
            'status' => InviteCode::STATUS_UNUSE,
            'effective_days' => $validity
        ])->select(['invite_code'])->get();

        return $data;
    }

    /**
     * 续费
     * @param $userId
     * @param $phone
     * @param $type
     * @param $num
     * @throws \Exception
     */
    public function renewFee($userId, $phone, $types, $num){
        try{
            $users = new User();
            $InviteCode = new InviteCode();

            // 用户信息.
            $member = $users->where('phone', $phone)->first();

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
                $query->leftjoin($users->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
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
            }

            $type = Order::ORDER_RENEWFEE;
            $member_phone  = $member['phone'];
            $member_name = $member['actual_name'];
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
            // 验证邀请码有效性.
            $isCode = InviteCode::where([
                'invite_code' => $newCode,
                'effective_days' => -1,
                'status' => InviteCode::STATUS_UNUSE
            ])->first();

            if(!$isCode){
                throw new \LogicException('邀请码错误');
            }

            // 我的用户信息.
            $user = User::where('id', $userId)->first();
            $userCode = $user['invite_code'];

            // 当前邀请码级别.
            $userType = InviteCode::where([
                'invite_code' => $userCode
            ])->pluck('effective_days')->first();

            if($userType == '-1'){
                throw new \LogicException('用户已经是VIP');
            }

            // 生成订单字段.
            switch ($userType){
                case 30:
                    $subtype = 31;
                    break;
                case 90:
                    $subtype = 32;
                    break;
                case 365:
                    $subtype = 33;
                    break;
            }
            $type = Order::ORDER_UPVIP;
            $number = 1;
            $user_phone  = $user['phone'];
            $user_name = $user['actual_name'];
            $unit_price = CodePrice::where(['duration' => '-1'])->pluck('code_price')->first();
            $status = 1;

            // 创建升级订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $number,
                'target_user_id' => $userId,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'unit_price' => $unit_price,
                'total_price' => $unit_price,
                'status' => $status,
                'remark' => $newCode,
            ]);

            if(!$res){
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

    /**
     * 获取学员位申请记录
     * @param $userId
     * @return mixed
     */
    public function applyList($userId){
        // 查询订单.
        $data =  Order::where([
            'target_user_id' => $userId
        ])->select(['subtype', 'number', 'user_phone', 'status', 'created_at'])->orderBy('created_at', 'desc')->get();

        // 订单类别.
        $order_type = [
            11 => '月付',
            12 => '季付',
            13 => '年付',
            14 => 'VIP',
            21 => '月续月',
            22 => '月续季',
            23 => '月续年',
            24 => '季续月',
            25 => '季续季',
            26 => '季续年',
            27 => '年续月',
            28 => '年续季',
            29 => '年续年',
            31 => '月升级VIP',
            32 => '季升级VIP',
            33 => '年升级VIP'
        ];

        // 订单状态.
        $order_status = [
            -1 => '已驳回',
            1 => '待审核',
            99 => '审核中',
            100 => '完成'
        ];

        foreach ($data as $k => $v) {
            $data[$k]['subtype'] = $order_type[$v['subtype']];
            $data[$k]['status'] = isset($order_status[$v['status']]) ? $order_status[$v['status']] : $order_status[99];
            $data[$k]['date'] = explode(' ', $v['created_at'])[0];
            $data[$k]['time'] = explode(' ', $v['created_at'])[1];
        }

        $result = [];
        foreach($data as $k=>$v){
            $result[$v['date']][] = $v;
        }

        return $result;
    }

}
