<?php
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(\App\Http\Middleware\ApiLog::class)->namespace('App\Http\Controllers')->group(function (){
    /**
     * 需要登录访问的接口列表
     */
    Route::middleware('auth.api')->group(function(){
        /**
         * 获取用户等级
         */
        Route::get('/getUserLevel', "UserController@getUserLevel");

        /**
         * 未使用邀请码数量
         */
        Route::get('/invideCode/unuseNum', "InviteCodeController@getUnUseInviteCodeNum");

        /**
         * 获取邀请码列表
         */
        Route::get('/invideCode/getList', "InviteCodeController@getList");

        /**
         * 获取我的学员列表
         */
        Route::get('/member/getMyMember', "UserController@getMyMember");

        /**
         * 设置朋友备注
         */
        Route::post('/member/setRemark', "FriendRemarkController@setRemark");

        /**
         * 朋友搜索
         */
        Route::post('/member/querFriend', "UserController@querFriend");

        /**
         * 完善资料
         */
        Route::post('/setUserInfo', "UserController@setUserInfo");

        /**
         * 获取用户资料
         */
        Route::get('/getUserInfo', "UserController@getUserInfo");

        /**
         * 密码验证
         */
        Route::post('/pwdValida', "UserController@pwdValida");

        /**
         * 申请邀请码
         */
        Route::post('/invideCode/appInviteCode', "InviteCodeController@appInviteCode");

        /**
         * 派发邀请码列表
         */
        Route::post('/invideCode/sendInviteList', "InviteCodeController@sendInviteList");

        /**
         * 转让邀请码
         */
        Route::post('/invideCode/transfer', "InviteCodeController@transfer");

        /**
         * 续费
         */
        Route::post('/invideCode/renewFee', "InviteCodeController@renewFee");

        /**
         * 升级VIP
         */
        Route::post('/invideCode/upVip', "InviteCodeController@upVip");

        /**
         * 创建消息
         */
        Route::post('/messages/setMessage', "MessageController@setMessage");

        /**
         * 获取消息列表
         */
        Route::get('/messages', "MessageController@getMessageList");

        /**
         * 标记消息为已读
         */
        Route::post('/messages/read/{messageId}', "MessageController@readMessage")->where('messageId', '[0-9]+');

        /**
         * 删除消息
         */
        Route::post('/messages/del/{messageId}', "MessageController@deleteMessage")->where('messageId', '[0-9]+');

        /**
         * 获取未读消息数量
         */
        Route::get('/messages/unReadNum', "MessageController@unReadNum");

        /**
         * 今日收益
         */
        Route::get('/income', "UserController@income");

        /**
         * 收益列表
         */
        Route::get('/incomeList', "UserController@incomeList");

        /**
         * 收益备注
         */
        Route::post('/incomeRemark', "UserController@incomeRemark");

        /**
         * 提现申请
         */
        Route::post('/withdrawal', "UserController@withdrawal");

        /**
         * 可提现金额
         */
        Route::get('/withdrawalsNum', "UserController@withdrawalsNum");

        /**
         * 提现记录
         */
        Route::get('/withdrawalRecords', "UserController@withdrawalRecords");

        /**
         * 获取学员招募记录
         */
        Route::get('/member/recruit', "UserController@recruit");

        /**
         * 今日新增招募
         */
        Route::get('/member/nowAdded', "UserController@nowAdded");

        /**
         * 获取学员位申请记录
         */
        Route::get('/member/applyList', "UserController@applyList");

    });

});

