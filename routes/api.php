<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['force-json'])->group(function() {
    // 登录
    Route::post('auth/login', 'AuthController@login');
    // 模拟接单建店
    Route::post('arrange/{order}', 'TestController@arrange');
    Route::post('shopStatus/{shop}', 'TestController@shopStatus');
    // 同步订单
    Route::get('order/sync', 'OrderController@sync2')->name('api.order.sync');
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
        // 创建待审核门店
        Route::post('storeShop', 'ShopController@storeShop')->name('shop.storeShop');
        // 审核门店
        Route::post('/shop/examine/{shop}', 'ShopController@examine')->name('shop.examine');
        // 门店
        Route::resource('shop', "ShopController", ['only' => ['store', 'show', 'index']]);
        // 门店地址加配送范围信息
        Route::get('shop/range/{shop}', "ShopController@range")->name('shop.range');
        Route::get('rangeByShopId', "ShopController@rangeByShopId")->name('shop.rangeByShopId');
        // 订单
        Route::post('order/send/{order}', 'OrderController@send')->name('order.send');
        Route::post('order2', 'OrderController@store2')->name('order.store2');
        // 取消订单
        Route::delete('order/cancel2/{order}', 'OrderController@cancel2')->name('api.order.cancel2');
        Route::resource('order', "OrderController", ['only' => ['store', 'show', 'index', 'destroy']]);
        // 获取配送费
        Route::get('order/money/{shop}', "OrderController@money");
        // 个人中心-用户充值
        Route::resource('deposit', "DepositController", ['only' => ['store', 'show', 'index']]);
        // 所有权限列表
        Route::get('permission/all', "PermissionController@all")->name('permission.all');
        // 权限管理
        Route::resource('permission', "PermissionController", ['only' => ['store', 'index', 'update']]);
        // 所有角色列表
        Route::get('role/all', "RoleController@all")->name('role.all');
        // 角色管理
        Route::resource('role', "RoleController", ['only' => ['store', 'show', 'index', 'update']]);
    });
});

/**
 * 支付回调接口
 */
Route::group(['namespace' => 'Api'], function () {
    Route::post('payment/wechat/notify', 'PaymentController@wechatNotify');
    Route::post('payment/alipay/notify', 'PaymentController@alipayNotify');
});

/**
 * 美团回调接口
 */
Route::namespace('MeiTuan')->prefix('mt')->group(function () {
    // 门店状态回调
    Route::post('shop/status', "ShopController@status");
    // 订单状态回调
    Route::post('order/status', "OrderController@status");
    // 订单异常回调
    Route::post('order/exception', "OrderController@exception");
});

/**
 * 蜂鸟回调接口
 */
Route::namespace('FengNiao')->prefix('fengniao')->group(function () {
    // 门店状态回调
    Route::post('shop/status', "ShopController@status");
    // 订单状态回调
    Route::post('order/status', "OrderController@status");
});

/**
 * 测试接口
 */
Route::group(['namespace' => 'Test'], function () {
    Route::post('/test/fn/createShop', 'FengNiaoTestController@createShop');
    Route::post('/test/fn/updateShop', 'FengNiaoTestController@updateShop');
    Route::post('/test/fn/getShop', 'FengNiaoTestController@getShop');
    Route::post('/test/fn/getArea', 'FengNiaoTestController@getArea');

    Route::post('/test/fn/createOrder', 'FengNiaoTestController@createOrder');
    Route::post('/test/fn/cancelOrder', 'FengNiaoTestController@cancelOrder');
    Route::post('/test/fn/getOrder', 'FengNiaoTestController@getOrder');
    Route::post('/test/fn/complaintOrder', 'FengNiaoTestController@complaintOrder');

    Route::post('/test/fn/delivery', 'FengNiaoTestController@delivery');
    Route::post('/test/fn/carrier', 'FengNiaoTestController@carrier');
    Route::post('/test/fn/route', 'FengNiaoTestController@route');
});