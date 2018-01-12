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

        $params = $request->all();
        $params['user_id'] = $userId;
        if(!$data = CacheHelper::getCache($params)){
            try{
                $data = (new UserService())->getMyMember($userId, $page);
                CacheHelper::setCache($data, 1, $params);
            }catch (\Exception $e){
                $this->ajaxError("系统错误");
            }
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
        $data = $request->all();

        // 判断字段.
        if(!$data['actual_name'] || !$data['wechat_id'] || !$data['taobao_id'] || !$data['alipay_id']){
            return $this->ajaxError("参数错误");
        }

        // 执行更新数据.
        try{
            (new UserService())->setUserInfo($userId, $data);
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
            $this->ajaxError("系统错误");
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
        $data = (new UserService())->applyList($userId);
        return $this->ajaxSuccess($data);
    }

    /**
     * 获取学员招募记录
     * @param Request $request
     * @return static
     */
    public function recruit(Request $request){
        $userId = $request->user()->id;
        //分页参数
        $page = $request->get("page",1);
        $data = (new UserService())->recruit($userId, $page);
        return $this->ajaxSuccess($data);
    }

}
