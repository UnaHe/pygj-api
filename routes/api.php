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
        Route::get('/inviteCode/unusedNum', "InviteCodeController@getUnUseInviteCodeNum");

        /**
         * 获取邀请码列表
         */
        Route::get('/inviteCode/getList', "InviteCodeController@getList");

        /**
         * 获取我的推客列表
         */
        Route::get('/member/getMyMember', "UserController@getMyMember");

        /**
         * 设置朋友备注
         */
        Route::post('/member/setRemark', "FriendRemarkController@setRemark");

        /**
         * 朋友搜索
         */
        Route::post('/member/queryFriend', "UserController@querFriend");

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
        Route::post('/inviteCode/appInviteCode', "OrderController@appInviteCode");

        /**
         * 派发邀请码列表
         */
        Route::post('/inviteCode/sendInviteList', "InviteCodeController@sendInviteList");

        /**
         * 转让邀请码
         */
        Route::post('/inviteCode/transfer', "OrderController@transfer");

        /**
         * 续费
         */
        Route::post('/inviteCode/renewFee', "OrderController@renewFee");

        /**
         * 升级终身码
         */
        Route::post('/inviteCode/upVip', "OrderController@upVip");

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
        Route::post('/withdrawal', "OrderController@withdrawal");

        /**
         * 可提现金额
         */
        Route::get('/withdrawalsNum', "OrderController@withdrawalsNum");

        /**
         * 提现记录
         */
        Route::get('/withdrawalRecords', "UserController@withdrawalRecords");

        /**
         * 获取推客招募记录
         */
        Route::get('/member/recruit', "UserController@recruit");

        /**
         * 今日新增招募
         */
        Route::get('/member/nowAdded', "UserController@nowAdded");

        /**
         * 获取推客位申请记录
         */
        Route::get('/member/applyList', "UserController@applyList");

        /**
         * 生成邀请链接
         */
        Route::get('/invite/inviteLink', "UserController@inviteLink");

        /**
         * 	提现审批记录
         */
        Route::get('/withdrawals/approvedList', "OrderController@approvedList");

        /**
         * 	提现审批
         */
        Route::post('/withdrawals/approvedOrTurnDown', "OrderController@approvedOrTurnDown");

        /**
         * 	订单审批记录
         */
        Route::get('/order/ordersApprovedList', "OrderController@ordersApprovedList");

        /**
         * 	订单审批
         */
        Route::post('/order/ordersApprovedOrTurnDown', "OrderController@ordersApprovedOrTurnDown");

        /**
         * 	半货半款收货
         */
        Route::get('/order/ordersReceiving', "OrderController@ordersReceiving");

        /**
         * 	确认收货
         */
        Route::post('/order/ordersConfirmReceiving', "OrderController@ordersConfirmReceiving");

    });

    /**
     * 邀请订单
     */
    Route::post('/invite/acceptInvite', "OrderController@acceptInvite");

});

