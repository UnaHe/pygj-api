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

    });

});

