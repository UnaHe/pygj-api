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

    /**
     * 申请邀请码
     * @param Request $request
     * @return static
     */
    public function appInviteCode(Request $request){
        $userId = $request->user()->id;
        $types = $request->input('types');
        $num = $request->input('codenum');

        if(!preg_match('/^[1-9]\d?$/', $num)){
            return $this->ajaxError('数量应该为1-99');
        }

        if(!preg_match('/^-1|30|90|365$/', $types)){
            return $this->ajaxError("请输入正确的邀请码类型");
        }

        try{
            (new InviteCodeService())->appInviteCode($userId, $types, $num);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 派发邀请码列表
     * @param Request $request
     * @return static
     */
    public function sendInviteList(Request $request){
        $userId = $request->user()->id;
        $data = (new InviteCodeService())->sendInviteList($userId);
        return $this->ajaxSuccess($data);
    }

    /**
     * 派发邀请码
     * @param Request $request
     * @return static
     */
    public function sendInviteCode(Request $request){
        $userId = $request->user()->id;
        // 获得要排法的邀请码类型.
        $validity = $request->input('validity');
        if(!preg_match('/^-1|30|90|365$/', $validity)){
            return $this->ajaxError("参数错误");
        }
        $data = (new InviteCodeService())->sendInviteCode($userId, $validity);
        return $this->ajaxSuccess($data);
    }

    /**
     * 续费
     * @param Request $request
     * @return static
     */
    public function renewFee(Request $request){
        $userId = $request->user()->id;
        $phone = $request->input('phone');
        $types = $request->input('types');
        $num = $request->input('num');

        // 判断参数.
        if(!preg_match('/^1\d{10}$/', $phone)){
            return $this->ajaxError('请输入正确的手机号码');
        }
        if(!preg_match('/^30|90|365$/', $types)){
            return $this->ajaxError('请输入正确的续费类型');
        }
        if(!preg_match('/^[1-9]\d?$/', $num)){
            return $this->ajaxError('数量应该为1-99');
        }
        if($types == 30 && $num > 2){
            return $this->ajaxError('推荐选择更优惠的季付套餐');
        }
        if($types == 90 && $num > 3){
            return $this->ajaxError('推荐选择更优惠的年付套餐');
        }

        try{
            (new InviteCodeService())->renewFee($userId, $phone, $types, $num);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();

    }

    /**
     * 转VIP
     * @param Request $request
     * @return static
     */
    public function turnVip(Request $request){
        $userId = $request->user()->id;
        $phone = $request->input('phone');

        if(!preg_match('/^1\d{10}$/', $phone)){
            return $this->ajaxError('请输入正确的手机号码');
        }

        try{
            (new InviteCodeService())->turnVip($userId, $phone);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 获取学员位申请记录
     * @param Request $request
     * @return static
     */
    public function applyList(Request $request){
        $userId = $request->user()->id;
        $data = (new InviteCodeService())->applyList($userId);
        return $this->ajaxSuccess($data);
    }

}
