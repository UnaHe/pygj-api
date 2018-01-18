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

        $user = User::where("id", $userId)->with('UserInfo')->first()->toArray();
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
        $upgradeNeedNum = $user['user_info']['upgrade'] ? : 12;

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
        $UserInfo = new UserInfo();

        $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->leftjoin($UserInfo->getTable()." as userinfo", "userinfo.user_id", '=', "user.id");
        $query->select(["user.id", "user.phone", "userinfo.actual_name", "userinfo.wechat_id", "userinfo.taobao_id"]);

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
                    $masterUser = $User->from($User->getTable()." as user")->where('user.id', $masterUserId)
                        ->leftjoin($UserInfo->getTable()." as userinfo", "userinfo.user_id", '=', "user.id")
                        ->select(["user.id", "user.phone", "userinfo.actual_name", "userinfo.wechat_id", "userinfo.taobao_id"])->first();
                    if($masterUser){
                        $masterUser['type'] = 2;
                        $masterUser['type_desc'] = "师傅";
                        $masterUser['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $masterUserId])->pluck('remark')->first();
                        array_push($users, $masterUser);
                    }
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
            $data = UserInfo::where('user_id', $userId)->first();

            if($data !== NULL){
                $res = $data->update([
                    'user_id' => $userId,
                    'actual_name' => $actual_name,
                    'wechat_id' => $wechat_id,
                    'taobao_id' => $taobao_id,
                    'alipay_id' => $alipay_id,
                ]);
            } else {
                $res = UserInfo::create([
                    'user_id' => $userId,
                    'actual_name' => $actual_name,
                    'wechat_id' => $wechat_id,
                    'taobao_id' => $taobao_id,
                    'alipay_id' => $alipay_id,
                ]);
            }
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
            $user = User::where("id", $userId)->with('UserInfo')->first(['id', 'phone', 'grade', 'expiry_time'])->toArray();
            $user['upgrade'] = $user['user_info']['upgrade'];
            $user['actual_name'] = $user['user_info']['actual_name'];
            $user['wechat_id'] = $user['user_info']['wechat_id'];
            $user['taobao_id'] = $user['user_info']['taobao_id'];
            $user['alipay_id'] = $user['user_info']['alipay_id'];
            unset($user['user_info']);
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
        $UserInfo = new UserInfo();

        // 查找我的学员.
        $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->leftjoin($UserInfo->getTable()." as userinfo", "userinfo.user_id", '=', "user.id");
        $query->select(["user.id", "user.phone", "userinfo.actual_name", "userinfo.wechat_id", "userinfo.taobao_id"]);

        // 查找关键字.
        $query->where(function($query) use($keyword){
            $query->where('user.phone', 'like', "%$keyword%")
                ->orwhere('userinfo.wechat_id', 'like', "%$keyword%");
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
                $masterUser = $User->from($User->getTable()." as user")->where("user.id", $masterUserId)
                    ->leftjoin($UserInfo->getTable()." as userinfo", "userinfo.user_id", '=', "user.id")
                    ->select(["user.id", "user.phone", "userinfo.actual_name", "userinfo.wechat_id", "userinfo.taobao_id"])
                    ->where(function($query) use($keyword){
                        $query->where('user.phone', 'like', "%$keyword%")
                            ->orwhere('userinfo.wechat_id', 'like', "%$keyword%");
                    })->first();
                if($masterUser){
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
     * 获取学员位申请记录
     * @param $userId
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public function applyList($userId, $startTime='', $endTime=''){
        if($startTime && $endTime){
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 查询时间范围订单.
            $data = Order::where([
                ['type', '<=', 2],
                ['target_user_id', $userId],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['subtype', 'number', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        } else if(!$startTime && !$endTime) {
            // 查询订单.
            $data = Order::where([
                ['type', '<=', 2],
                ['target_user_id', $userId]
            ])->select(['subtype', 'number', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        } else {
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 查询单天订单.
            $data = Order::where([
                ['type', '<=', 2],
                ['target_user_id', $userId],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(['subtype', 'number', 'status', 'created_at', 'remark'])->orderBy('created_at', 'desc');
        }

        // 分页.
        $data = (new QueryHelper())->pagination($data)->get();

        foreach ($data as $k => $v) {
            $data[$k]['subtype'] = Order::$order_subtype[$v['subtype']];
            $data[$k]['status'] = isset(Order::$order_status[$v['status']]) ? Order::$order_status[$v['status']] : Order::$order_status[99];
            $data[$k]['date'] = explode(' ', $v['created_at'])[0];
            $data[$k]['time'] = explode(' ', $v['created_at'])[1];
        }

        // 按日期分组.
        $result = [];
        foreach($data as $k=>$v){
            $result[$v['date']][] = $v;
        }

        // 分组计数,合计.
        $num = 0;
        $numbers = 0;
        foreach ($result as $k => $v){
            foreach ($v as $key => $vel){
                $num += $vel['number'];
                $numbers += $vel['number'];
            }
            $result[$k]['num'] = $num;
            $num = 0;
        }
        $result['numbers'] = $numbers;
        $result['servertime'] = time();

        return $result;
    }

    /**
     * 获取学员招募记录
     * @param $userId
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public function recruit($userId, $page=1, $startTime='', $endTime=''){
        $User = new User();
        $InviteCode = new InviteCode();
        $UserInfo = new UserInfo();

        $query = $InviteCode->from($InviteCode->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin($User->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->leftjoin($UserInfo->getTable()." as userinfo", "userinfo.user_id", '=', "user.id");
        $query->select(["user.id", "user.phone", "userinfo.wechat_id"]);

        // 我的学员.
        $users = (new QueryHelper())->pagination($query)->get()->toArray();

        // 我的学员ID.
        $usersId = [];
        foreach($users as $k=>$v){
            $usersId[] = $v['id'];
        }

        if($startTime && $endTime){
            // 初始化时间.
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
            // 时间段学员招募.
            $member = Order::whereIn('target_user_id', $usersId)->where([
                ['type', '<=', 2],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
        } else if(!$startTime && !$endTime) {
            // 学员招募.
            $member = Order::whereIn('target_user_id', $usersId)->where([
                ['type', '<=', 2]
            ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
        } else {
            // 初始化时间.
            $endTime = $startTime.' 23:59:59';
            $startTime = $startTime.' 00:00:00';
            // 单天学员招募.
            $member = Order::whereIn('target_user_id', $usersId)->where([
                ['type', '<=', 2],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
        }
        // 分页.
        $member = $member->get()->toArray();

        foreach ($member as $k => $v) {
            if (isset(Order::$order_type[$v['type']])) {
                $member[$k]['type'] = Order::$order_type[$v['type']];
            } else {
                unset($member[$k]);
            }
            $member[$k]['subtype'] = Order::$order_subtype[$v['subtype']];
        }

        // 我的招募.
        if ($page == 1) {
            // 我的用户信息.
            $me = $User->where('id', $userId)->with('UserInfo')->first()->toArray();
            $meInfo['id'] = $me['id'];
            $meInfo['phone'] = $me['phone'];
            $meInfo['wechat_id'] = $me['user_info']['wechat_id'];
            array_push($users, $meInfo);

            if($startTime && $endTime){
                // 初始化时间.
                $startTime = $startTime.' 00:00:00';
                $endTime = $endTime.' 23:59:59';
                // 我的时间段招募.
                $data = Order::where([
                    ['type', '<=', 2],
                    ['target_user_id', $userId],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime]
                ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
            } else if(!$startTime && !$endTime) {
                // 我的招募.
                $data = Order::where([
                    ['type', '<=', 2],
                    ['target_user_id', $userId]
                ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
            } else {
                // 初始化时间.
                $endTime = $startTime.' 23:59:59';
                $startTime = $startTime.' 00:00:00';
                // 我的单天招募.
                $data = Order::where([
                    ['type', '<=', 2],
                    ['target_user_id', $userId],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime]
                ])->select(DB::raw("target_user_id, type, subtype, sum(number) as number, DATE_FORMAT(created_at,'%Y-%m-%d') as date"))->groupBy(['target_user_id', 'type', 'subtype', 'date'])->orderBy('date', 'desc');
            }
            // 分页.
            $data = (new QueryHelper())->pagination($data)->get()->toArray();

            foreach ($data as $k => $v) {
                if (isset(Order::$order_type[$v['type']])) {
                    $data[$k]['type'] = Order::$order_type[$v['type']];
                } else {
                    unset($data[$k]);
                }
                $data[$k]['subtype'] = Order::$order_subtype[$v['subtype']];
            }

            foreach ($data as $k => $v){
                $member[] = $v;
            };
        }

        $result = [];
        foreach($member as $k=>$v){
            $result[$v['date']][$v['target_user_id']][$v['type']][] = $v;
        }

        // 用户信息.
        $info = [];
        foreach ($users as $k => $v) {
            $info[$v['id']]['phone'] = $v['phone'];
            $info[$v['id']]['wechat_id'] = $v['wechat_id'];
        }

        // 分组计数,合计.
        $num = 0;
        $numbers = 0;
        foreach ($result as $k => $v){
            foreach ($v as $ke => $ve){
                foreach ($ve as $key => $vel){
                    foreach ($vel as $key1 => $vel1){
                        $num += $vel1['number'];
                        $numbers += $vel1['number'];
                    }
                    $result[$k][$ke][$key]['num'] = $num;
                    $num = 0;
                    $result[$k][$ke][$key]['wechat_id'] = $info[$vel1['target_user_id']]['wechat_id'];
                    $result[$k][$ke][$key]['phone'] = $info[$vel1['target_user_id']]['phone'];
                }
            }
        }
        $result['numbers'] = $numbers;
        $result['user_id'] = $userId;
        $result['servertime'] = time();

        return $result;
    }

    /**
     * 今日新增招募
     * @param $userId
     * @return mixed
     */
    public function nowAdded($userId){
        // 初始化时间.
        $year = date("Y");
        $month = date("m");
        $day = date("d");
        $start = mktime(0,0,0,$month,$day,$year);
        $end= mktime(23,59,59,$month,$day,$year);
        $startTime = date('Y-m-d H:i:s', $start);
        $endTime = date('Y-m-d H:i:s', $end);

        $data = Order::where([
            ['type', '<=', 2],
            ['target_user_id', $userId],
            ['created_at', '>=', $startTime],
            ['created_at', '<=', $endTime]
        ])->select(DB::raw('sum(number) as number'))->get();

        return $data;
    }

    /**
     * 今日收益
     * @param $userId
     * @return array
     */
    public function income($userId){
        $data = [
            'day' => 10,
            'month' => 100,
            'total' => 1000,
        ];

        return $data;
    }

    /**
     * 收益列表
     * @param $userId
     * @return array
     */
    public function incomeList($userId){
        $data = [
            'day' => 10,
            'month' => 100,
            'total' => 1000,
        ];

        return $data;
    }

    /**
     * 提现申请
     * @param $userId
     * @param $money
     * @return mixed
     * @throws \Exception
     */
    public function extract($userId, $money){
        try{
            // 我的用户信息.
            $user = User::where("id", $userId)->with('UserInfo')->first()->toArray();

            $type = Order::ORDER_EXTRACT;
            $subtype = 41;
            $user_phone  = $user['phone'];
            $user_name = $user['user_info']['actual_name'];
            $user_grade = $user['grade'] ? : 1;
            $status = 1;
            $remark = $user['user_info']['alipay_id'];

            // 创建申请订单.
            $res = Order::create([
                'type' => $type,
                'subtype' => $subtype,
                'number' => $money,
                'target_user_id' => $userId,
                'user_id' => $userId,
                'user_phone' => $user_phone,
                'user_name' => $user_name,
                'user_grade' => $user_grade,
                'status' => $status,
                'remark' => $remark,
            ]);

            if(!$res){
                throw new \LogicException('提现申请失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '提现申请失败';
            }
            throw new \Exception($error);
        }
    }

}
