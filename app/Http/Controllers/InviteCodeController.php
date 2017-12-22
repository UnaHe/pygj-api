<?php

namespace App\Http\Controllers;

use App\Services\InviteCodeService;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    /**
     * 获取未使用邀请码数量
     * @param Request $request
     * @return static
     */
    public function getUnUseInviteCodeNum(Request $request){
        $userId = $request->user()->id;
        $data = (new InviteCodeService())->getUnUseInviteCodeNum($userId);
        return $this->ajaxSuccess($data);
    }

    /**
     * 邀请码列表
     * @param Request $request
     * @return static
     */
    public function getList(Request $request){
        $userId = $request->user()->id;
        $data = (new InviteCodeService())->getListByUserId($userId);
        return $this->ajaxSuccess($data);
    }

}
