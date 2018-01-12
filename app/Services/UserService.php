<?php
/**
 * Created by PhpStorm.
 * User: yangtao
 * Date: 2017/10/18
 * Time: 15:51
 */
namespace App\Services;

use App\Helpers\CacheHelper;
use App\Helpers\QueryHelper;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UserLevelConfig;
use App\Models\FriendRemark;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class UserService{
    /**
     * 获取用户等级信息
     * @param $userId
     * @return array
     */
    public function getUserLevel($userId){
        if($data = CacheHelper::getCache()){
            return $data;
        }

        $UserLevelConfig = new UserLevelConfig();

        $user = User::where("id", $userId)->first();
        // 账户等级.
        $grade = $user['grade'] ? : 1;
        $gradeConfig = $UserLevelConfig->where("id", $grade)->first();
        if(!$gradeConfig){
            Log::error("用户等级{$grade}未配置");
            throw new \Exception("系统错误");
        }
        // 当前等级.
        $gradeDesc = $gradeConfig['name'];
        $gradePic = 'http://'.config('domains.pygj_domains').$gradeConfig['grede_pic'];
        $isTop = $gradeConfig['is_top'];
        // 升级需要数量.
        $upgradeNeedNum = $user['upgrade'] ? : 12;

        // 升级等级.
        $upgradeGrade = ($grade + 1) <= 3 ? $grade + 1 : 3;
        $upgrade = $UserLevelConfig->where("id", $upgradeGrade)->first();

        $data = [
            'grade' => $gradeDesc,
            'upgrade_need_num' => $upgradeNeedNum,
            'upgrade_to_grade' => $upgrade['name'],
            'grade_pic' => $gradePic,
            'is_top' => $isTop
        ];

        CacheHelper::setCache($data, 2);
        return $data;
    }

    /**
     * 获取我的学员列表
     * @param $userId
     * @return array
     * @throws \Exception
     */
    public function getMyMember($userId, $page=1){
        $User = new User();
        $InviteCode = new InviteCode();

        $query =$InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->select(["user.id", "user.phone", "user.actual_name", "user.wechat_id", "user.taobao_id"]);

        //我的学员
        $users = (new QueryHelper())->pagination($query)->get()->toArray();
        $remarks = new FriendRemark();
        foreach ($users as &$user){
            $user['type'] = 1;
            $user['type_desc'] = "学员";
            $user['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $user['id']])->pluck('remark')->first();
        }

        if($page == 1){
            //我使用的邀请码
            $inviteCode = $User->where("id", $userId)->pluck('invite_code')->first();
            if($inviteCode){
                //师傅的用户id
                $masterUserId = $InviteCode->where(['invite_code' => $inviteCode])->pluck('user_id')->first();
                if($masterUserId){
                    $masterUser = User::where("id", $masterUserId)->select(["id", "phone", "actual_name", "wechat_id", "taobao_id"])->first()->toArray();
                    $masterUser['type'] = 2;
                    $masterUser['type_desc'] = "师傅";
                    $masterUser['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $masterUserId])->pluck('remark')->first();
                    array_push($users, $masterUser);
                }
            }
        }

        return $users;
    }

    /**
     * 完善资料
     * @param $userId
     * @param $actual_name
     * @param $wechat_id
     * @param $taobao_id
     * @param $alipay_id
     * @throws \Exception
     */
    public function setUserInfo($userId, $actual_name, $wechat_id ,$taobao_id ,$alipay_id){
        try{
            $res = UserInfo::create([
                'user_id' => $userId,
                'actual_name' => $actual_name,
                'wechat_id' => $wechat_id,
                'taobao_id' => $taobao_id,
                'alipay_id' => $alipay_id,
            ]);

            if(!$res){
                throw new \LogicException('信息存储失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '信息存储失败';
            }
            throw new \Exception($error);
        }
    }

    /**
     * 获取用户资料
     * @param $userId
     * @return array
     * @throws \Exception
     */
    public function getUserInfo($userId){
        try{
            // 查询用户.
            $user = User::find($userId);
            if(!$user){
                throw new \LogicException("用户不存在");
            }
            // 是否过期.
            if ($user['expiry_time'] && (strtotime($user['expiry_time']) - time()) < 0) {
                $user['is_expiry'] = 1;
            } else {
                $user['is_expiry'] = 0;
            }
            return $user;
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '用户不存在';
            }
            throw new \Exception($error);
        }
    }

    /**
     * 密码验证
     * @param $userId
     * @param $password
     * @throws \Exception
     */
    public function pwdValida($userId, $password){
        try{
            // 查询用户.
            $user = User::find($userId);
            if(!$user){
                throw new \LogicException("用户不存在");
            }

            // 验证密码.
            $sqlPassword = $user->password;
            if (!Hash::check($password, $sqlPassword)) {
                throw new \LogicException("密码不正确!");
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '密码验证失败';
            }
            throw new \Exception($error);
        }
    }

    /**
     * 朋友搜索
     * @param $userId
     * @param $keyword
     * @return array
     * @throws \Exception
     */
    public function querFriend($userId, $keyword){
        $User = new User();
        $InviteCode = new InviteCode();

        // 查找我的学员.
        $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->select(["user.id", "user.phone", "user.actual_name", "user.wechat_id", "user.taobao_id"]);

        // 查找关键字.
        $query->where(function($query) use($keyword){
            $query->where('user.phone', 'like', "%$keyword%")
                ->orwhere('user.wechat_id', 'like', "%$keyword%");
        });

        // 符合条件的学员.
        $users = $query->get()->toArray();
        $remarks = new FriendRemark();
        foreach ($users as &$user){
            $user['type'] = 1;
            $user['type_desc'] = "学员";
            $user['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $user['id']])->pluck('remark')->first();
        }

        //我使用的邀请码
        $inviteCode = $User->where("id", $userId)->pluck('invite_code')->first();
        if($inviteCode){
            //师傅的用户id
            $masterUserId = $InviteCode->where(['invite_code' => $inviteCode])->pluck('user_id')->first();
            if($masterUserId){
                // 匹配师傅关键字.
                $masterUser = $User->where("id", $masterUserId)
                    ->select(["id", "phone", "actual_name", "wechat_id", "taobao_id"])
                    ->where(function($query) use($keyword){
                    $query->where('phone', 'like', "%$keyword%")
                        ->orwhere('wechat_id', 'like', "%$keyword%");
                })->first();
                $masterUser['type'] = 2;
                $masterUser['type_desc'] = "师傅";
                $masterUser['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $masterUserId])->pluck('remark')->first();
                array_push($users, $masterUser);
            }
        }

        return $users;
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
        ])->select(['subtype', 'number', 'user_phone', 'status', 'created_at'])->orderBy('created_at', 'desc');

        // 分页.
        $data = (new QueryHelper())->pagination($data)->get();

        // 订单子类别.
        $order_subtype = [
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
            $data[$k]['subtype'] = $order_subtype[$v['subtype']];
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

    /**
     * 获取学员位申请记录
     * @param $userId
     * @return mixed
     */
    public function recruit($userId, $page=1){
        // 订单类别.
        $order_type = [
            1 => '我的招募申请',
            2 => '学员变更申请',
            3 => '学员变更申请'
        ];

        // 订单子类别.
        $order_subtype = [
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

        $User = new User();
        $InviteCode = new InviteCode();

        $query =$InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->select(["user.id", "user.phone", "user.wechat_id"]);

        //我的学员
        $users = (new QueryHelper())->pagination($query)->get()->toArray();

        foreach($users as $k=>$v){
            foreach($v as $key=>$val){
                $usersId[] = $v['id'];
            }
        }

        $member =  Order::whereIn('target_user_id', $usersId)->select(DB::raw('target_user_id, type, subtype, sum(number) as num'))->groupBy(['target_user_id', 'type', 'subtype'])->get();

        foreach ($member as $k => $v) {
            if (isset($order_type[$v['type']])) {
                $member[$k]['type'] = $order_type[$v['type']];
            } else {
                unset($member[$k]);
            }
            $member[$k]['subtype'] = $order_subtype[$v['subtype']];
        }

        $result = [];
        foreach($member as $k=>$v){
            $result[$v['target_user_id']][$v['type']][] = $v;
        }

        if ($page == 1) {
            $data =  Order::where(['target_user_id' => $userId])->select(DB::raw('target_user_id, type, subtype, sum(number) as num'))->groupBy(['target_user_id', 'type', 'subtype'])->get();

            foreach ($data as $k => $v) {
                if (isset($order_type[$v['type']])) {
                    $data[$k]['type'] = $order_type[$v['type']];
                } else {
                    unset($data[$k]);
                }
                $data[$k]['subtype'] = $order_subtype[$v['subtype']];
            }

            foreach($data as $k=>$v){
                $result[$v['target_user_id']][$v['type']][] = $v;
            }

        }

        return $result;
    }

}
