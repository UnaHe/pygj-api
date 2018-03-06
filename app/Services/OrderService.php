<?php
/**
 * Created by PhpStorm.
 * User: una
 * Date: 2018/2/7
 * Time: 16:04
 */
namespace App\Services;

use App\Helpers\QueryHelper;
use App\Models\PayInfo;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderProcess;
use App\Models\CodePrice;
use App\Models\InviteCode;
use App\Models\UserIncome;
use App\Services\CaptchaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderService{
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
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'path'])->toArray();

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
                case 1:
                    $subtype = 16;
                    break;
            }

            $types = ($types == 1) ? -1 : $types;
            $type = Order::ORDER_APPLY;
            $to_user_id = ($user['grade'] === 3) ? 0 : explode(':', $user['path'])[0];
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
                'to_user_id' => $to_user_id,
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
            $member = $User->where('phone', $phone)->with('UserInfo')->first(['id', 'phone', 'invite_code', 'grade', 'path']);

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
            $to_user_id = ($member['grade'] === 3) ? 0 : explode(':', $member['path'])[0];;
            $member_phone = $member['phone'];
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
                'to_user_id' => $to_user_id,
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
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'invite_code', 'grade', 'expiry_time']);
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
            $to_user_id = 0;
            $num = 1;
            $user_phone  = $userArr['phone'];
            $user_name = $userArr['user_info']['actual_name'];
            $user_grade = $userArr['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $newCodeType)->pluck('code_price')->first();
            $status = 100;

            $params = [
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $userId,
                'to_user_id' => $to_user_id,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $unit_price,
                'status' => $status,
                'remark' => $remark,
                'date' => date('Y-m-d'),
            ];

            // Redis 队列.
            $codeUserId = $isCode['user_id'];
            $codeType = $isCode['code_type'];
            $redisParams = [
                'type' => 4,
                'code' => $newCode,
                'uprice' => $unit_price,
                'userId' => $codeUserId,
                'effdays' => $newCodeType,
                'codetype' => $codeType,
            ];
            $redisParamsJson = json_encode($redisParams, JSON_FORCE_OBJECT);

            // 创建升级订单.
            DB::beginTransaction();

            $res = Order::create($params);

            if($res){
                // 更改用户邀请码.
                $user->invite_code = $newCode;
                $user->expiry_time = $newEndTime;
                $user->save();
                // 更改邀请码状态.
                $isCode->status = InviteCode::STATUS_USED;
                $isCode->update_time = date('Y-m-d H:i:s');
                $isCode->date = date('Y-m-d');
                $isCode->save();
            } else {
                DB::rollBack();
                throw new \LogicException('升级失败');
            }
            DB::commit();

            // 存入收益统计队列.
            Redis::lpush('manager:queue:complate_order_info', $redisParamsJson);
        }catch (\Exception $e){
            DB::rollBack();
            $error = $e instanceof \LogicException ? $e->getMessage() : '升级失败';
            throw new \Exception($error);
        }
    }

    /**
     * 提现申请
     * @param $userId
     * @param $money
     * @return mixed
     * @throws \Exception
     */
    public function withdrawal($userId, $money){
        try{
            // 查询我的可提现金额.
            $withdrawalsNum = $this->withdrawalsNum($userId);
            $allowMoney = $withdrawalsNum - $money;
            if($allowMoney < 0){
                throw new \LogicException('最大可提现金额'.$withdrawalsNum.'元');
            }

            // 我的用户信息.
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'path'])->toArray();

            $type = Order::ORDER_EXTRACT;
            $to_user_id = ($user['grade'] === 3) ? 0 : explode(':', $user['path'])[0];
            $subtype = 41;
            $user_phone  = $user['phone'];
            $user_name = $user['user_info']['actual_name'];
            $user_grade = $user['grade'] ? : 1;
            $status = 1;
            $remark = $user['user_info']['alipay_id'];

            // 创建提现订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'target_user_id' => $userId,
                'to_user_id' => $to_user_id,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $money,
                'status' => $status,
                'remark' => $remark,
                'date' => date('Y-m-d'),
            ]);

            if(!$res){
                throw new \LogicException('提现申请失败');
            }
        }catch (\Exception $e){
            $error = $e instanceof \LogicException ? $e->getMessage() : '提现申请失败';
            throw new \Exception($error);
        }
    }

    /**
     * 可提现金额
     * @param $userId
     * @return mixed
     * @throws \Exception
     */
    public function withdrawalsNum($userId){
        $incomeOrder = Order::where([
            'type' => 4,
            'target_user_id' => $userId,
            ['status', '<>', -1]
        ])->sum('unit_price');

        $income = UserIncome::where('user_id', $userId)->sum('income_num');

        $withdrawalsNum = ($income - $incomeOrder) > 0 ? ($income - $incomeOrder) : 0;

        return $withdrawalsNum;
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
            $codes = explode(' ', $code);
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
                case 1:
                    $subtype = 56;
                    break;
            }

            $type = Order::ORDER_TRANSFER;
            $to_user_id = 0;
            $user_id  = $toUserArray['id'];
            $user_phone  = $toPhone;
            $user_name = $toUserName;
            $user_grade = $toUserArray['grade'] ? : 1;
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $total_price = $unit_price * $num;
            $status = 100;

            $params = [
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $userId,
                'to_user_id' => $to_user_id,
                'user_id' => $user_id,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'status' => $status,
                'remark' => rtrim($codeRemark, ','),
                'date' => date('Y-m-d'),
            ];

            // 创建转码订单.
            DB::beginTransaction();

            $res = Order::create($params);

            if($res){
                $InviteCode->whereIn('invite_code', $codeArray)->update(['user_id' => $user_id]);
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
     * 邀请订单
     * @param $masterPhone
     * @param $phone
     * @param $types
     * @throws \Exception
     */
    public function acceptInvite($masterPhone, $phone, $types){
        try{
            // 同一用户只能存在一个邀请订单.
            $orderInfo = Order::where([
                'type' => 1,
                'user_id' => NUll,
                'user_phone' => $phone,
            ])->first();

            if($orderInfo){
                throw new \LogicException('订单已存在,请等待审核');
            }

            // 父级用户信息.
            $masterUser = User::where("phone", $masterPhone)->first(['id', 'phone', 'grade', 'path'])->toArray();

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
                case 1:
                    $subtype = 16;
                    break;
            }

            $types = ($types == 1) ? -1 : $types;
            $type = Order::ORDER_APPLY;
            $num = 1;
            $target_user_id = $masterUser['id'];
            $to_user_id = ($masterUser['grade'] === 3) ? $masterUser['id'] : explode(':', $masterUser['path'])[0];
            $userId = NULL;
            $user_phone  = $phone;
            $user_name = '';
            $user_grade = 1;
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $status = 1;

            // 创建申请订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $num,
                'target_user_id' => $target_user_id,
                'to_user_id' => $to_user_id,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'unit_price' => $unit_price,
                'total_price' => $unit_price,
                'status' => $status,
                'date' => date('Y-m-d'),
            ]);

            // 报错一般为用户PATH未完善.
            if(!$res){
                throw new \LogicException('提交失败');
            }
        }catch (\Exception $e){
            $error = $e instanceof \LogicException ? $e->getMessage() : '提交失败';
            throw new \Exception($error);
        }
    }

    /**
     * 提现审批记录
     * @param $userId
     * @param string $startTime
     * @param string $endTime
     * @param $status
     * @return mixed
     */
    public function approvedList($userId, $startTime = '', $endTime = '', $status){
        // 记录状态.
        $compare = ($status == 1) ? '=' : '<>';

        if($startTime && $endTime){
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 查询时间范围订单.
            $data = Order::where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', $compare, 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['id', 'user_name', 'user_phone', 'unit_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }else if(!$startTime && !$endTime) {
            // 查询订单.
            $data = Order::where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', $compare, 1]
            ])->select(['id', 'user_name', 'user_phone', 'unit_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }else{
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 查询单天订单.
            $data = Order::where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', $compare, 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['id', 'user_name', 'user_phone', 'unit_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }

        // 分页.
        $data = (new QueryHelper())->pagination($data)->get();

        $OrderProcess = new OrderProcess();

        foreach ($data as $k => $v) {
            if($v['status'] === -1){
                $data[$k]['remark'] = $OrderProcess->where('order_id', $v['id'])->orderBy('created_at', 'desc')->pluck('remark')->first();
            }
            $data[$k]['status'] = isset(Order::$order_status[$v['status']]) ? Order::$order_status[$v['status']] : Order::$order_status[99];
        }

        return $data;
    }

    /**
     * 提现审批
     * @param $userId
     * @param $orderId
     * @param $types
     * @param $remark
     * @return mixed
     * @throws \Exception
     */
    public function approvedOrTurnDown($userId, $orderId, $types, $remark = ''){
        try{
            // 订单信息.
            $orderInfo = Order::where([
                'id' => $orderId,
                'type' => Order::ORDER_EXTRACT,
                'status' => 1,
                'to_user_id' => $userId,
            ])->first();

            if(!$orderInfo){
                throw new \LogicException('记录不存在');
            }

            // 查询用户.
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'expiry_time'])->toArray();

            // 创建订单审批记录表.
            $actualName = $user['user_info']['actual_name'];
            $bizBeforeStatus = 1;
            $bizRearStatus = $types;
            $OrderProcessParams = [
                'order_id' => $orderId,
                'biz_user_id' => $userId,
                'biz_user_name' => $actualName,
                'biz_before_status' => $bizBeforeStatus,
                'biz_rear_status' => $bizRearStatus,
                'remark' => $remark,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 审批提现申请.
            DB::beginTransaction();
            $res = $orderInfo->update(['status' => $types]);

            if($res){
                OrderProcess::create($OrderProcessParams);
            } else {
                DB::rollBack();
                throw new \LogicException('批准失败');
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            $error = $e instanceof \LogicException ? $e->getMessage() : '批准失败';
            throw new \Exception($error);
        }
    }

    /**
     * 订单审批记录
     * @param $userId
     * @param string $startTime
     * @param string $endTime
     * @param $status
     * @return mixed
     */
    public function ordersApprovedList($userId, $startTime = '', $endTime = '', $status){
        // 记录类型.
        $type = [Order::ORDER_APPLY, Order::ORDER_RENEWFEE, Order::ORDER_INVITE];

        // 记录状态.
        $compare = ($status == 1) ? [1, 99] : [-1, 100];

        if($startTime && $endTime){
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 查询时间范围订单.
            $data = Order::whereIn('type', $type)->whereIn('status', $compare)->where([
                ['to_user_id', $userId],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['id', 'type', 'subtype', 'number', 'target_user_id', 'user_name', 'user_phone', 'unit_price', 'total_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }else if(!$startTime && !$endTime) {
            // 查询订单.
            $data = Order::whereIn('type', $type)->whereIn('status', $compare)->where([
                ['to_user_id', $userId]
            ])->select(['id', 'type', 'subtype', 'number', 'target_user_id', 'user_name', 'user_phone', 'unit_price', 'total_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }else{
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 查询单天订单.
            $data = Order::whereIn('type', $type)->whereIn('status', $compare)->where([
                ['to_user_id', $userId],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['id', 'type', 'subtype', 'number', 'target_user_id', 'user_name', 'user_phone', 'unit_price', 'total_price', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }

        // 分页.
        $data = (new QueryHelper())->pagination($data)->get();

        $OrderProcess = new OrderProcess();

        foreach ($data as $k => $v) {
            if($v['status'] === -1){
                $data[$k]['remark'] = $OrderProcess->where('order_id', $v['id'])->orderBy('created_at', 'desc')->pluck('remark')->first();
            }
            $data[$k]['status'] = isset(Order::$order_status[$v['status']]) ? Order::$order_status[$v['status']] : Order::$order_status[98];
            $data[$k]['subtype'] = Order::$order_subtype[$v['subtype']];
        }

        return $data;
    }

    /**
     * 订单审批
     * @param $userId
     * @param $orderId
     * @param $types
     * @param $remark
     * @return mixed
     * @throws \Exception
     */
    public function ordersApprovedOrTurnDown($userId, $orderId, $types, $remark = ''){
        try{
            // 订单信息.
            $orderInfo = Order::where([
                'id' => $orderId,
                'status' => 1,
                'to_user_id' => $userId,
            ])->first();

            if(!$orderInfo){
                throw new \LogicException('记录不存在');
            }

            $orderType = $orderInfo['type'];
            $orderSubtype = $orderInfo['subtype'];
            $orderNum = $orderInfo['number'];
            $targetUserId = $orderInfo['target_user_id'];
            $orderUserId = $orderInfo['user_id'];
            $orderUserPhone = $orderInfo['user_phone'];

            // 批准申请邀请码订单.
            if($types == 100){
                $InviteCode = new InviteCode();

                if($orderType == 1){
                    // 发什么类型邀请码.
                    switch ($orderSubtype){
                        case 14:
                            $effectiveDays = -1;
                            break;
                        case 11:
                            $effectiveDays = 30;
                            break;
                        case 12:
                            $effectiveDays = 90;
                            break;
                        case 13:
                            $effectiveDays = 365;
                            break;
                        case 15:
                            $effectiveDays = 5;
                            break;
                        case 16:
                            $effectiveDays = -1;
                            break;
                    }
                    // 通过邀请WAP提交订单的查询父级码.
                    $codeIsWho = ($orderUserId == NUll) ? $targetUserId : $userId;

                    // 查询自己可用对应类型邀请码.
                    $unusedCodeNum = $InviteCode->where([
                        'user_id' => $codeIsWho,
                        'status' => InviteCode::STATUS_UNUSE,
                        'effective_days' => $effectiveDays,
                        'is_dispatch' => 0,
                    ])->count();

                    $isUnused = $unusedCodeNum - $orderNum;
                    if($isUnused < 0){
                        throw new \LogicException(Order::$order_subtype[$orderSubtype].'剩余可派发'.$unusedCodeNum.'个,不足订单需求'.$orderNum.'个,请先前往申请');
                    }

                    // 获取订单对应数量类型的邀请码.
                    $transferCode = $InviteCode->where([
                        'user_id' => $codeIsWho,
                        'status' => InviteCode::STATUS_UNUSE,
                        'effective_days' => $effectiveDays,
                        'is_dispatch' => 0,
                    ])->select('invite_code')->take($orderNum)->get();

                    $transferCodeArray = $transferCode->toArray();
                    $str = '';
                    foreach($transferCodeArray as $k=>$v){
                        $str .=$v['invite_code'].',';
                    }
                    $remark = rtrim($str, ',');
                }
            }

            // 查询用户.
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'expiry_time'])->toArray();

            // 是否为半货半款订单.
            $type = ($orderSubtype == 16) ? 99 : $types;

            // 创建订单审批记录表.
            $actualName = $user['user_info']['actual_name'];
            $bizBeforeStatus = 1;
            $bizRearStatus = $type;
            $OrderProcessParams = [
                'order_id' => $orderId,
                'biz_user_id' => $userId,
                'biz_user_name' => $actualName,
                'biz_before_status' => $bizBeforeStatus,
                'biz_rear_status' => $bizRearStatus,
                'remark' => $remark,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 正常邀请码.
            $inviteCodeParams['user_id'] = $targetUserId;
            // 半货半款.
            if($orderSubtype == 16){
                $inviteCodeParams['code_type'] = 1;
            }
            // 通过邀请WAP提交订单的用户发送邀请码.
            if($orderUserId == NUll){
                $inviteCodeParams['is_dispatch'] = 1;
            }

            // 审批申请.
            DB::beginTransaction();
            $orderType = $orderInfo->update(['status' => $type]);

            if($orderType){
                OrderProcess::create($OrderProcessParams);
                if($types == 100){
                    $InviteCode->whereIn('invite_code', $transferCodeArray)->update($inviteCodeParams);
                }
                // 通过邀请WAP提交订单的用户发送邀请码.
                if($orderUserId == NUll){
                    (new CaptchaService())->sendInviteCode($orderUserPhone, $remark);
                }
            } else {
                DB::rollBack();
                throw new \LogicException('批准失败');
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            $error = $e instanceof \LogicException ? $e->getMessage() : '批准失败';
            throw new \Exception($error);
        }
    }

    /**
     * 半货半款收货
     * @param $userId
     * @param $orderId
     * @return mixed
     */
    public function ordersReceiving($userId, $orderId){
        // 订单信息.
        $orderInfo = Order::where([
            'id' => $orderId,
            'subtype' => 16,
            'status' => 99,
            'to_user_id' => $userId,
        ])->first();

        if(!$orderInfo){
            throw new \LogicException('记录不存在');
        }

        // 获取订单对应邀请码.
        $orderProcessRemark = OrderProcess::where('order_id', $orderId)->orderBy('created_at', 'desc')->pluck('remark')->first();
        $codes = explode(',', $orderProcessRemark);

        $User = new User();
        $InviteCode = new InviteCode();
        $arr = [];
        foreach($codes as $k=>$v){
            // 我的用户信息.
            $userInfo = $User->where("invite_code", $v)->with('UserInfo')->first(['id', 'phone', 'grade', 'path']);
            $isReceiving = $InviteCode->where('invite_code', $v)->pluck('is_receiving')->first();
            if($userInfo){
                $userInfo = $userInfo->toArray();
                $arr[$k]['user_name'] = $userInfo['user_info']['actual_name'];
                $arr[$k]['invite_code'] = $v;
                $arr[$k]['user_phone'] = $userInfo['phone'];
                $arr[$k]['is_receiving'] = $isReceiving;
            }else{
                $arr[$k]['user_name'] = $orderInfo['user_name'];
                $arr[$k]['invite_code'] = $v;
                $arr[$k]['user_phone'] = $orderInfo['user_phone'];
                $arr[$k]['is_receiving'] = $isReceiving;
            }
        }

        return $arr;
    }

    /**
     * 确认收货
     * @param $userId
     * @param $inviteCode
     * @return mixed
     * @throws \Exception
     */
    public function ordersConfirmReceiving($userId, $orderId, $inviteCode){
        try{
            $InviteCode = new InviteCode();
            $OrderProcess = new OrderProcess();

            $orderProcess = $OrderProcess->where([
                'order_id' => $orderId,
                'biz_user_id' => $userId,
                'biz_rear_status' => 99,
                ['remark', 'like', "%$inviteCode%"]
            ])->first();

            if(!$orderProcess){
                throw new \LogicException('记录不存在');
            }

            $order = Order::where(['id' => $orderId, 'status' => 100])->first();

            if($order){
                throw new \LogicException('订单已完成');
            }

            // 确认收货.
            $inviteCodeInfo = $InviteCode->where('invite_code', $inviteCode)->first();

            // Redis 队列.
            $types = $inviteCodeInfo['effective_days'];
            $unit_price = CodePrice::where('duration', $types)->pluck('code_price')->first();
            $codeUserId = $inviteCodeInfo['user_id'];
            $codeType = $inviteCodeInfo['code_type'];
            $redisParams = [
                'type' => 3,
                'code' => $inviteCode,
                'uprice' => $unit_price,
                'userId' => $codeUserId,
                'effdays' => $types,
                'codetype' => $codeType,
            ];
            $redisParamsJson = json_encode($redisParams, JSON_FORCE_OBJECT);

            $res = $inviteCodeInfo->update(['is_receiving' => 1]);

            if(!$res){
                throw new \LogicException('确认收货失败');
            }

            $codes = explode(',', $orderProcess['remark']);
            $total = count($codes);
            $num = $InviteCode->whereIn('invite_code', $codes)->where('is_receiving', 1)->count();

            // 如果全部收货完成.
            if ($total == $num) {
                // 查询用户.
                $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'expiry_time'])->toArray();

                // 创建订单审批记录表.
                $actualName = $user['user_info']['actual_name'];
                $bizBeforeStatus = 1;
                $bizRearStatus = 100;
                $OrderProcessParams = [
                    'order_id' => $orderId,
                    'biz_user_id' => $userId,
                    'biz_user_name' => $actualName,
                    'biz_before_status' => $bizBeforeStatus,
                    'biz_rear_status' => $bizRearStatus,
                    'remark' => '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                Order::where('id', $orderId)->update(['status' => $bizRearStatus]);
                $OrderProcess->create($OrderProcessParams);
            }

            // 存入收益统计队列.
            Redis::lpush('manager:queue:complate_order_info', $redisParamsJson);
        }catch (\Exception $e){
            $error = $e instanceof \LogicException ? $e->getMessage() : '确认收货失败';
            throw new \Exception($error);
        }
    }

    /**
     * 获取支付信息
     * @param $phone
     * @return array
     */
    public function getPayInfo($phone){
        $User = new User();

        // 查询用户信息.
        $userInfo = $User->where("phone", $phone)->first(['id', 'phone', 'grade', 'path']);

        if(!$userInfo){
            throw new \LogicException('记录不存在');
        }

        $userGrade = $userInfo['grade'] ? : 1;

        if ($userGrade == 3) {
            // 获取公司支付信息.
            $pay = PayInfo::where('character', 'admin')->pluck('pay')->first();
        } else {
            // 获取父级支付信息.
            $masterId = explode(':', $userInfo['path'])[0];
            $masterUser = $User->where("id", $masterId)->with('UserInfo')->first(['id', 'phone', 'grade'])->toArray();
            $pay = $masterUser['user_info']['alipay_id'] ? : $masterUser['phone'];
        }

        return ['pay' => $pay];
    }

    /**
     * 提现订单数量
     * @param $userId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function withdrawalsNumber($userId, $startTime ,$endTime){
        $Order = new Order();

        if($startTime && $endTime){
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 查询时间范围数量.
            $not = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();

            $already = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', '<>', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();
        }else if(!$startTime && !$endTime) {
            // 查询数量.
            $not = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', 1]
            ])->count();

            $already = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', '<>', 1]
            ])->count();
        }else{
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 查询单天数量.
            $not = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();

            $already = $Order->where([
                ['type', Order::ORDER_EXTRACT],
                ['to_user_id', $userId],
                ['status', '<>', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();
        }

        $data = [
            'not' => $not,
            'already' => $already
        ];

        return $data;
    }

    /**
     * 订单数量
     * @param $userId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function orderNum($userId, $startTime ,$endTime){
        // 记录类型.
        $type = [Order::ORDER_APPLY, Order::ORDER_RENEWFEE, Order::ORDER_INVITE];
        $Order = new Order();

        if($startTime && $endTime){
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 查询时间范围数量.
            $not = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();

            $already = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', '<>', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();
        }else if(!$startTime && !$endTime) {
            // 查询数量.
            $not = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', 1]
            ])->count();

            $already = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', '<>', 1]
            ])->count();
        }else{
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 查询单天数量.
            $not = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();

            $already = $Order->whereIn('type', $type)->where([
                ['to_user_id', $userId],
                ['status', '<>', 1],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->count();
        }

        $data = [
            'not' => $not,
            'already' => $already
        ];

        return $data;
    }

    /**
     * 半货半款收货数量
     * @param $userId
     * @param $orderId
     * @return array
     */
    public function ordersReceivingNum($userId ,$orderId){
        $orderProcess = OrderProcess::where([
            'order_id' => $orderId,
            'biz_user_id' => $userId,
            'biz_rear_status' => 100,
        ])->first();

        if(!$orderProcess){
            throw new \LogicException('记录不存在');
        }

        $codes = explode(',', $orderProcess['remark']);

        $total = count($codes);

        $num = InviteCode::whereIn('invite_code', $codes)->where('is_receiving', 1)->count();

        $data = [
            'total' => $total,
            'num' => $num
        ];

        return $data;
    }

}