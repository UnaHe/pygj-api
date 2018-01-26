<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 获取用户等级
     */
    public function getUserLevel(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->getUserLevel($userId);
        }catch (\Exception $e){
            $this->ajaxError("系统错误");
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 获取我的学员列表
     * @param Request $request
     * @return static
     */
    public function getMyMember(Request $request){
        $userId = $request->user()->id;
        //分页参数
        $page = $request->get("page",1);

        try{
            $data = (new UserService())->getMyMember($userId, $page);
        }catch (\Exception $e){
            $this->ajaxError("系统错误");
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 完善资料
     * @param Request $request
     * @return static
    */
    public function setUserInfo(Request $request){
        // 获得用户信息.
        $userId = $request->user()->id;
        $actual_name = $request->input('actual_name');
        $wechat_id = $request->input('wechat_id');
        $taobao_id = $request->input('taobao_id');
        $alipay_id = $request->input('alipay_id');

        // 判断字段.
        if(!$actual_name || !$wechat_id || !$taobao_id || !$alipay_id){
            return $this->ajaxError("参数错误");
        }

        // 执行更新数据.
        try{
            (new UserService())->setUserInfo($userId, $actual_name, $wechat_id ,$taobao_id ,$alipay_id);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 获取用户资料
     * @param Request $request
     * @return static
     */
    public function getUserInfo(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->getUserInfo($userId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 密码验证
     * @param Request $request
     * @return static
     */
    public function pwdValida(Request $request){
        // 获得用户密码.
        $userId = $request->user()->id;
        $password = $request->input('password');

        if(strlen($password) < 6){
            return $this->ajaxError('密码不正确');
        }

        // 执行密码验证.
        try{
            (new UserService())->pwdValida($userId, $password);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 朋友搜索
     * @param Request $request
     * @return static
     */
    public function querFriend(Request $request){
        // 关键字.
        $userId = $request->user()->id;
        $keyword = $request->input('keyword');

        if(!$keyword){
            return $this->ajaxError("参数错误");
        }

        // 搜索.
        try{
            $data = (new UserService())->querFriend($userId, $keyword);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 获取学员位申请记录
     * @param Request $request
     * @return static
     */
    public function applyList(Request $request){
        $userId = $request->user()->id;
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        try{
            $data = (new UserService())->applyList($userId, $startTime, $endTime);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 获取学员招募记录
     * @param Request $request
     * @return static
     */
    public function recruit(Request $request){
        $userId = $request->user()->id;
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        //分页参数
        $page = $request->get("page",1);

        try{
            $data = (new UserService())->recruit($userId, $page, $startTime, $endTime);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 今日新增招募
     * @param Request $request
     * @return static
     */
    public function nowAdded(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->nowAdded($userId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 今日收益
     * @param Request $request
     * @return static
     */
    public function income(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->income($userId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 收益列表
     * @param Request $request
     * @return static
     */
    public function incomeList(Request $request){
        $userId = $request->user()->id;
        $type = $request->input('type');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        try{
            $data = (new UserService())->incomeList($userId, $type, $startTime, $endTime);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 收益备注
     * @param Request $request
     * @return static
     */
    public function incomeRemark(Request $request){
        $userId = $request->user()->id;
        $incomeId = $request->input('income_id');
        $remark = $request->input('remark');

        if(!$remark){
            return $this->ajaxError("参数错误");
        }

        try{
            $data = (new UserService())->incomeRemark($userId, $incomeId, $remark);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 提现申请
     * @param Request $request
     * @return static
     */
    public function withdrawal(Request $request){
        $userId = $request->user()->id;
        $money = $request->input('money');

        // 提交申请.
        try{
            (new UserService())->withdrawal($userId, $money);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 可提现金额
     * @param Request $request
     * @return static
     */
    public function withdrawalsNum(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->withdrawalsNum($userId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

    /**
     * 提现记录
     * @param Request $request
     * @return static
     */
    public function withdrawalRecords(Request $request){
        $userId = $request->user()->id;

        try{
            $data = (new UserService())->withdrawalRecords($userId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess($data);
    }

}
