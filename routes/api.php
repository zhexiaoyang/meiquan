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

    // 注册验证码
    Route::post('code', 'CommonController@getVerifyCode')->name('code');
    // 服务协议
    Route::get('getAgreementList', 'CommonController@agreement')->name('agreement');
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
        // 统计页面
        Route::get('statistics', 'StatisticsController@index');
        // 统计导出-统计
        Route::get('statistics/export', 'StatisticsController@export');
        // 统计导出-统计-明细
        // Route::get('statistics/export/detail', 'StatisticsController@detail');

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
        // Route::post('order2', 'OrderController@store2')->name('order.store2');
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

        /**
         * 采购系统
         */
        Route::prefix("/purchase")->group(function () {
            // 采购购物车
            Route::get("category", "SupplierCategoryController@index");
            // 采购商品列表
            Route::get("product", "SupplierProductController@index");
            // 采购商品详情
            Route::get("product/{supplier_product}", "SupplierProductController@show");
            // 采购购物车
            Route::get("cart", "SupplierCartController@index");
            // 采购购物车
            Route::post("cart", "SupplierCartController@store");
            // 地址
            Route::get("address", "SupplierAddressController@index");
            // 订单
            Route::resource("order", "SupplierOrderController");
            // 收货
            Route::post("received/{supplier_order}", "SupplierOrderController@received")->name("supplier.order.received");
        });
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
 * 闪送回调接口
 */
Route::namespace('ShanSong')->prefix('shansong')->group(function () {
    // 订单状态回调
    Route::post('order/status', "OrderController@status");
});

/**
 * 药柜回调接口
 */
Route::namespace('Api')->prefix('zg')->group(function () {
    // 结算订单
    Route::post('order/settlement', "YaoguiController@settlement");
    // 隐私号降级
    Route::post('order/downgrade', "YaoguiController@downgrade");
    // 创建订单
    Route::post('order/create', "YaoguiController@create");
    // 取消订单
    Route::post('order/cancel', "YaoguiController@cancel");
    // 催单
    Route::post('order/urge', "YaoguiController@urge");
});

/**
 * 蜂鸟测试接口
 */
// Route::group(['namespace' => 'Test'], function () {
//     Route::post('/test/fn/createShop', 'FengNiaoTestController@createShop');
//     Route::post('/test/fn/updateShop', 'FengNiaoTestController@updateShop');
//     Route::post('/test/fn/getShop', 'FengNiaoTestController@getShop');
//     Route::post('/test/fn/getArea', 'FengNiaoTestController@getArea');
//
//     Route::post('/test/fn/createOrder', 'FengNiaoTestController@createOrder');
//     Route::post('/test/fn/cancelOrder', 'FengNiaoTestController@cancelOrder');
//     Route::post('/test/fn/getOrder', 'FengNiaoTestController@getOrder');
//     Route::post('/test/fn/complaintOrder', 'FengNiaoTestController@complaintOrder');
//
//     Route::post('/test/fn/delivery', 'FengNiaoTestController@delivery');
//     Route::post('/test/fn/carrier', 'FengNiaoTestController@carrier');
//     Route::post('/test/fn/route', 'FengNiaoTestController@route');
// });

/**
 * 闪送测试接口
 */
// Route::namespace('Test')->prefix("test/ss")->group(function () {
//     Route::post('createShop', 'ShanSongTestController@createShop');
//     Route::post('getShop', 'ShanSongTestController@getShop');
//
//     Route::post('orderCalculate', 'ShanSongTestController@orderCalculate');
//     Route::post('createOrder', 'ShanSongTestController@createOrder');
//     Route::post('cancelOrder', 'ShanSongTestController@cancelOrder');
//     Route::post('getOrder', 'ShanSongTestController@getOrder');
//     Route::post('complaintOrder', 'ShanSongTestController@complaintOrder');
//
//     Route::post('delivery', 'ShanSongTestController@delivery');
//     Route::post('carrier', 'ShanSongTestController@carrier');
//     Route::post('route', 'ShanSongTestController@route');
// });