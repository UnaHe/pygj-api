<?php
// 邀请页面
Route::get('/invite/{phone}', 'UserController@invitePage')->where('phone', '^1[3456789]{1}\d{9}$');

// 邀请页面2
Route::get('/invite2/{phone}', 'UserController@invitePage');