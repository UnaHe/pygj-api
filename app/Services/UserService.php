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
use App\Models\UserLevelConfig;
use App\Models\FriendRemark;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

        $user = User::where("id", $userId)->first();
        // 账户等级.
        $grade = $user['grade'] ? : 1;
        $gradeConfig = UserLevelConfig::where("id", $grade)->first();
        if(!$gradeConfig){
            Log::error("用户等级{$grade}未配置");
            throw new \Exception("系统错误");
        }
        // 升级需要数量.
        $gradeDesc = $gradeConfig['name'];
        $upgradeNeedNum = $user['upgrade'] ? : 12;

        // 升级等级.
        $upgradeGrade = ($grade + 1) <= 3 ? $grade + 1 : 3;
        $upgrade = UserLevelConfig::where("id", $upgradeGrade)->first();

        $data = [
            'grade' => $gradeDesc,
            'upgrade_need_num' => $upgradeNeedNum,
            'upgrade_to_grade' => $upgrade['name']
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
        $query = InviteCode::from((new InviteCode())->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin((new User())->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
        $query->select(["user.id", "user.phone", "user.actual_name", "user.wechat_id", "user.taobao_id"]);

        //我的学员
        $users = (new QueryHelper())->pagination($query)->get()->toArray();
        $remarks = new FriendRemark();
        foreach ($users as &$user){
            $user['type'] = 1;
            $user['type_desc'] = "会员";
            $user['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $user['id']])->pluck('remark')->first();
        }

        if($page == 1){
            //我使用的邀请码
            $inviteCode = User::where("id", $userId)->pluck('invite_code')->first();
            if($inviteCode){
                //师傅的用户id
                $masterUserId = InviteCode::where(['invite_code' => $inviteCode])->pluck('user_id')->first();
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
     * @param $userid
     * @param $data
     * @throws \Exception
     */
    public function setUserInfo($userId, $data){
        try{
            // 查询用户.
            $user = User::find($userId);
            if(!$user){
                throw new \LogicException("用户不存在");
            }

            // 更新数据.
            $user->update($data);
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
     * @param $userid
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
     * @param $userid
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
        // 查找我的学员.
        $query = InviteCode::from((new InviteCode())->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);
        $query->leftjoin((new User())->getTable()." as user", "user.invite_code", '=', "invite.invite_code");
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
            $user['type_desc'] = "会员";
            $user['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $user['id']])->pluck('remark')->first();
        }

        //我使用的邀请码
        $inviteCode = User::where("id", $userId)->pluck('invite_code')->first();
        if($inviteCode){
            //师傅的用户id
            $masterUserId = InviteCode::where(['invite_code' => $inviteCode])->pluck('user_id')->first();
            if($masterUserId){
                // 匹配师傅关键字.
                $masterUser = User::where("id", $masterUserId)
                    ->select(["id", "phone", "actual_name", "wechat_id", "taobao_id"])
                    ->where(function($query) use($keyword){
                    $query->where('phone', 'like', "%$keyword%")
                        ->orwhere('wechat_id', 'like', "%$keyword%");
                })->first();
                $masterUser['remark'] = $remarks->where(['user_id' => $userId, 'friend_user_id' => $masterUserId])->pluck('remark')->first();
                array_push($users, $masterUser);
            }
        }

        return $users;
    }

}
