<?php

use Illuminate\Support\Facades\Route;

// 登录
Route::post('auth/login', 'AuthController@login');
// 模拟接单建店
Route::post('arrange/{order}', 'TestController@arrange');
Route::post('shopStatus/{shop}', 'TestController@shopStatus');
// 同步订单
Route::get('order/sync', 'OrderController@sync')->name('api.order.sync');
// 取消订单
Route::get('order/cancel', 'OrderController@cancel')->name('api.order.cancel');

Route::post('code', 'CommonController@getVerifyCode')->name('code');
Route::post('auth/register', 'AuthController@register');

Route::middleware('auth:api')->group(function () {
    // 退出
    Route::post('auth/logout', 'AuthController@logout');
    // 注册
    // Route::post('auth/register', 'AuthController@register');
    // 个人中心-用户信息
    Route::get('user/info', 'AuthController@user');
    // 个人中心-用户信息-ant框架返回
    Route::get('user/me', 'AuthController@me');
    // 用户全部药店
    Route::get('shop/all', 'ShopController@all')->name('api.shop.all');
    // 订单状态
    Route::get('order/status/{order}', "OrderController@checkStatus");
    // 修改密码
    Route::post('user/reset_password', 'AuthController@resetPassword');

    /**
     * 管理员操作
     */
    Route::get('order/location/{order}', "OrderController@location");
    // 未绑定全部药店
    Route::get('shop/wei', 'ShopController@wei')->name('api.shop.wei')->middleware('role:super_man');
    // 同步门店
    Route::post('shop/sync', 'ShopController@sync')->name('api.shop.sync')->middleware('role:super_man');
    // 管理员手动充值
    Route::post('user/recharge', 'UserController@recharge')->name('api.shop.recharge')->middleware('role:super_man');
    // 管理员查看充值列表
    Route::get('user/recharge', 'UserController@rechargeList')->middleware('role:super_man');
    // 用户
    Route::resource('user', "UserController", ['only' => ['store', 'show', 'index', 'update']])->middleware('role:super_man');

    /**
     * 资源路由
     */
    // 门店
    Route::resource('shop', "ShopController", ['only' => ['store', 'show', 'index']]);
    // 门店地址加配送范围信息
    Route::get('shop/range/{shop}', "ShopController@range")->name('shop.range');
    // 订单
    Route::resource('order', "OrderController", ['only' => ['store', 'show', 'index', 'destroy']]);
    // 获取配送费
    Route::get('order/money/{shop}', "OrderController@money");
    // 个人中心-用户充值
    Route::resource('deposit', "DepositController", ['only' => ['store', 'show', 'index']]);
    // 权限管理
    Route::resource('permission', "PermissionController", ['only' => ['store', 'show', 'index']]);
});


Route::namespace('MeiTuan')->prefix('mt')->group(function () {
    // 门店状态回调
    Route::post('shop/status', "ShopController@status");
    // 订单状态回调
    Route::post('order/status', "OrderController@status");
    // 订单异常回调
    Route::post('order/exception', "OrderController@exception");
});

Route::group(['namespace' => 'Api'], function () {
    Route::post('payment/wechat/notify', 'PaymentController@wechatNotify');
    Route::post('payment/alipay/notify', 'PaymentController@alipayNotify');
});