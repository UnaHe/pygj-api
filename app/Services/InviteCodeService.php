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
            $wechat_id = $user['wechat_id'];
            $phone = $user['phone'];

            // 师傅的用户id.
            $masterUserId = InviteCode::where(['invite_code' => $user['invite_code']])->pluck('user_id')->first();

            switch ($types){
                case -1:
                    $types = 'VIP';
                    break;
                case 30:
                    $types = '月付';
                    break;
                case 90:
                    $types = '季付';
                    break;
                case 365:
                    $types = '年付';
                    break;
            }

            // 生成消息字段.
            $title = '申请通知';
            $content = '学员 '.$wechat_id.'('.$phone.')'.'向您申请: '.$num.'个'.$types.'学员位.';
            $type = 1;
            $from_user_id = $userId;
            $to_user_id = $masterUserId;

            $res = Message::create([
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
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
        ])->select(['effective_days', DB::raw('count(*) as num')])->groupBy('effective_days')->get();

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
     * 转VIP
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
            // 验证用户信息.
            $obj = $users->where("phone", $phone)->first();
            if(!$obj){
                throw new \LogicException('用户不存在');
            }

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
            $member = in_array($obj['id'], $new_arr);
            if(!$member){
                throw new \LogicException('申请用户不是您的学员');
            }

            // 申请用户当前会员类型.
            $userType =  $InviteCode->where(['invite_code' => $obj['invite_code']])->pluck('effective_days')->first();

            if($userType == '-1'){
                throw new \LogicException('用户已经是VIP');
            }

            // 计算邀请码级别.
            switch ($types){
                case 30:
                    $types = '个月';
                    break;
                case 90:
                    $types = '个季度';
                    break;
                case 365:
                    $types = '年';
                    break;
            }

            // 我的用户信息.
            $user = $users->where("id", $userId)->first();
            $wechat_id = $user['wechat_id'];
            $user_phone = $user['phone'];

            // 师傅的用户id.
            $masterUserId = $InviteCode->where(['invite_code' => $user['invite_code']])->pluck('user_id')->first();

            // 生成消息字段.
            $title = '申请通知';
            $content = '学员 '.$wechat_id.'('.$user_phone.')向您申请: 申请类型: 续费 - '.$num.$types.'需要变更的学员号码: '.$phone;
            $type = 1;
            $from_user_id = $userId;
            $to_user_id = $masterUserId;

            $res = Message::create([
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
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
     * 转VIP
     * @param $userId
     * @param $phone
     * @throws \Exception
     */
    public function turnVip($userId, $phone){
        try{
            $users = new User();
            $InviteCode = new InviteCode();
            // 验证用户信息.
            $obj = $users->where("phone", $phone)->first();
            if(!$obj){
                throw new \LogicException('用户不存在');
            }

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
            $member = in_array($obj['id'], $new_arr);
            if(!$member){
                throw new \LogicException('申请用户不是您的学员');
            }

            // 申请用户当前会员类型.
            $userType =  $InviteCode->where(['invite_code' => $obj['invite_code']])->pluck('effective_days')->first();

            if($userType == '-1'){
                throw new \LogicException('用户已经是VIP');
            }

            // 计算邀请码级别.
            switch ($userType){
                case 30:
                    $userType = '月';
                    break;
                case 90:
                    $userType = '季';
                    break;
                case 365:
                    $userType = '年';
                    break;
            }

            // 我的用户信息.
            $user = $users->where("id", $userId)->first();
            $wechat_id = $user['wechat_id'];
            $user_phone = $user['phone'];

            // 师傅的用户id.
            $masterUserId = $InviteCode->where(['invite_code' => $user['invite_code']])->pluck('user_id')->first();

            // 生成消息字段.
            $title = '申请通知';
            $content = '学员 '.$wechat_id.'('.$user_phone.')向您申请: 申请类型: '.$userType.'转vip 需要变更的学员号码: '.$phone;
            $type = 1;
            $from_user_id = $userId;
            $to_user_id = $masterUserId;

            $res = Message::create([
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
            ]);

            if(!$res){
                throw new \LogicException('申请转vip失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '申请转vip失败';
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
        // 查询可用邀请码.
        $data =  InviteCode::where([
            'user_id' => $userId,
            'status' => InviteCode::STATUS_UNUSE
        ])->select(['effective_days', DB::raw('count(*) as num')])->groupBy('effective_days')->get();

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

}
