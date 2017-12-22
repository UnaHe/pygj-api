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
        //账户等级
        $grade = $user['grade'] ? : 1;
        $gradeConfig = UserLevelConfig::where("id", $grade)->first();
        if(!$gradeConfig){
            Log::error("用户等级{$grade}未配置");
            throw new \Exception("系统错误");
        }
        $gradeDesc = $gradeConfig['name'];
        $data = [
            'grade' => $gradeDesc,
        ];

        $query = InviteCode::from((new InviteCode())->getTable()." as invite")->where([
            'invite.user_id' => $userId,
            'invite.status' => InviteCode::STATUS_USED
        ]);

        //和自己同级别或比自己级别大的直属学员
        $userCount = $query->leftjoin((new User())->getTable()." as user", "user.invite_code", '=', "invite.invite_code")
            ->where('user.grade', ">=", $grade)
            ->count();

        //升级所需直属学员数量
        $upgradeNeedNum = $gradeConfig['upgrade'] - $userCount;
        $upgradeNeedNum = $upgradeNeedNum <= 0 ? 0 : $upgradeNeedNum;
        $data['upgrade_need_num'] = $upgradeNeedNum;
        $data['upgrade_need_grade'] = $gradeDesc;

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
        $query->select(["user.id", "user.phone"]);

        //我的学员
        $users = (new QueryHelper())->pagination($query)->get()->toArray();
        foreach ($users as &$user){
            $user['type'] = 1;
            $user['type_desc'] = "会员";
        }

        if($page == 1){
            //我使用的邀请码
            $inviteCode = User::where("id", $userId)->pluck('invite_code')->first();
            if($inviteCode){
                //师傅的用户id
                $masterUserId = InviteCode::where(['invite_code' => $inviteCode])->pluck('user_id')->first();
                if($masterUserId){
                    $masterUser = User::where("id", $masterUserId)->select(["id", "phone"])->first()->toArray();
                    $masterUser['type'] = 2;
                    $masterUser['type_desc'] = "师傅";
                    array_push($users, $masterUser);
                }
            }
        }

        return $users;
    }


}
