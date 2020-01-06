<?php

use Illuminate\Support\Facades\Route;

// 登录
Route::post('auth/login', 'AuthController@login');
// 模拟接单建店
Route::post('arrange/{order}', 'TestController@arrange');
Route::post('shopStatus/{shop}', 'TestController@shopStatus');

Route::middleware('auth:api')->group(function () {
    // 退出
    Route::post('auth/logout', 'AuthController@logout');
    // 注册
    // Route::post('auth/register', 'AuthController@register');
    // 个人中心-用户信息
    Route::get('user/info', 'AuthController@user');
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
    // 同步订单
    Route::get('order/sync', 'OrderController@sync')->name('api.order.sync')->middleware('role:super_man');
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
    // 订单
    Route::resource('order', "OrderController", ['only' => ['store', 'show', 'index', 'destroy']]);
    // 个人中心-用户充值
    Route::resource('deposit', "DepositController", ['only' => ['store', 'show', 'index']]);
});


Route::namespace('MeiTuan')->prefix('mt')->group(function () {
    // 门店状态回调
    Route::post('shop/status', "ShopController@status");
    // 订单状态回调
    Route::post('order/status', "OrderController@status");
    // 订单异常回调
    Route::post('order/exception', "OrderController@exception");
});
