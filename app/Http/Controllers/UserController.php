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

}
