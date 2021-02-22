<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['force-json'])->group(function() {
    // *注册、登录验证码
    Route::post('code', 'CommonController@getVerifyCode')->name('code');
    // *中台注册
    Route::post('auth/register', 'AuthController@register');
    // *中台登录
    Route::post('auth/login', 'AuthController@login');

    // 模拟接单建店
    Route::post('arrange/{order}', 'TestController@arrange');
    Route::post('shopStatus/{shop}', 'TestController@shopStatus');
    // 同步订单
    Route::get('order/sync', 'OrderController@sync2')->name('api.order.sync');
    // 取消订单
    Route::get('order/cancel', 'OrderController@cancel')->name('api.order.cancel');
    // 服务协议
    Route::get('getAgreementList', 'CommonController@agreement')->name('agreement');

    Route::middleware('multiauth:api')->group(function () {
        // 退出
        Route::post('auth/logout', 'AuthController@logout');
        // 个人中心-用户信息
        Route::get('user/info', 'AuthController@user');
        // 个人中心-用户信息-ant框架返回
        Route::get('user/me', 'AuthController@me');
        // 用户全部可发单药店
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
         * 门店管理
         */
        // *门店地址加配送范围信息
        Route::get('shop/range/{shop}', "ShopController@range")->name('shop.range');
        // 根据门店ID获取门店地址加配送范围信息
        Route::get('rangeByShopId', "ShopController@rangeByShopId")->name('shop.rangeByShopId');
        // *绑定门店-自动发单
        Route::post('/shop/binding', 'ShopController@binding')->name('shop.binding');
        // *关闭自动发单
        Route::post('/shop/closeAuto', 'ShopController@closeAuto')->name('shop.closeAuto');
        // *资源路由-门店
        Route::resource('shop', "ShopController", ['only' => ['store', 'show', 'index']]);

        /**
         * 商城门店认证
         */
        // *所属用户门店-根据认证结果筛选
        Route::get("shopping", "ShoppingController@index");
        // *提交门店认证
        Route::post("shopping", "ShoppingController@store");
        // *修改门店认证
        Route::put("shopping", "ShoppingController@update");
        // *提交认证门店列表-多状态
        Route::get("shopping/list", "ShoppingController@shopAuthList");
        // *提交认证门店详情
        Route::get("shopping/info", "ShoppingController@show");
        // 认证成功门店列表-待优化（根据状态查询）
        Route::get("shop_auth_success_list", "ShoppingController@shopAuthSuccessList");
        // *已认证门店修改-详情
        Route::get("shopping/change/info", "ShoppingChangeController@show");
        // *已认证门店修改-提交修改
        Route::post("shopping/change", "ShoppingChangeController@store");

        /**
         * 外卖资料上线
         */
        Route::prefix("/online")->group(function () {
            // *上线门店列表
            Route::get("material", "OnlineController@material");
            // *保存上线门店
            Route::post("shop", "OnlineController@store");
            // *更新上线门店
            Route::put("shop", "OnlineController@update");
            // *上线门店列表
            Route::get("shop", "OnlineController@index");
            // *上线门店详情
            Route::get("shop/info", "OnlineController@show");
        });

        /**
         * 管理员操作
         */
        Route::middleware(['role:super_man'])->group(function () {
            // *商城认证-审核列表
            Route::get("examine/shopping", "ExamineShoppingController@index");
            // *商城认证-审核
            Route::post("examine/shopping", "ExamineShoppingController@store");
            // *商城认证-资料修改申请-审核列表
            Route::get("examine/shopping/change", "ExamineShoppingController@changeIndex");
            // *商城认证-资料修改申请-审核列表
            Route::post("examine/shopping/change", "ExamineShoppingController@changeStore");

            // *审核接口-管理员-审核列表
            Route::get("online/shop/examine", "OnlineController@examineList");
            // *审核接口-管理员-详情
            Route::get("online/shop/examine/show", "OnlineController@examineShow");
            // *审核接口-管理员-审核
            Route::post("online/shop/examine", "OnlineController@examine");
            // *审核接口-管理员-审核
            Route::post("online/shop/examine", "OnlineController@examine");

            // *跑腿审核-门店列表
            Route::get("examine/shop", "ExamineShopController@index");
            // *跑腿审核-审核操作
            Route::post("examine/shop", "ExamineShopController@store");
            // *跑腿审核-更改门店名称
            Route::post("examine/shop/update", "ExamineShopController@update");

            // *自动接单-门店列表
            Route::get("examine/auto", "ExamineShopController@autoList");
            // *自动接单-审核操作
            Route::post("examine/auto", "ExamineShopController@AutoStore");

            // 商城后台
            // *商城后台-商品列表
            Route::get("/shopAdmin/product", "ShopAdminController@productList");
            // *商城后台-商品排序
            Route::post("/shopAdmin/product/sort", "ShopAdminController@productSort");
            // *商城后台-商品活动设置
            Route::post("/shopAdmin/product/active", "ShopAdminController@productActive");
            // *商城后台-订单列表
            Route::get("/shopAdmin/order", "ShopAdminController@orderList");
            // *商城后台-取消订单
            Route::post("/shopAdmin/order/cancel", "ShopAdminController@cancelOrder");

            // ERP管理
            // ERP管理-key列表
            Route::get("/erpAdmin/access_key/info", "ErpAdminAccessKeyController@info");
            // ERP管理-key列表
            Route::get("/erpAdmin/access_key", "ErpAdminAccessKeyController@index");
            // ERP管理-key添加
            Route::post("/erpAdmin/access_key", "ErpAdminAccessKeyController@store");
            // ERP管理-key修改
            Route::put("/erpAdmin/access_key", "ErpAdminAccessKeyController@update");
            // ERP管理-key删除
            Route::delete("/erpAdmin/access_key", "ErpAdminAccessKeyController@destroy");
            // ERP管理-key门店列表
            Route::get("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@index");
            // ERP管理-key门店添加
            Route::post("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@store");
            // ERP管理-key门店修改
            Route::put("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@update");
            // ERP管理-key门店删除
            Route::delete("/erpAdmin/access_key/shop", "ErpAdminAccessKeyShopController@destroy");

        });

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
        // 可以看到的所有门店
        Route::get('shopAll', 'ShopController@shopAll')->name('shop.shopAll');
        // 订单
        Route::post('order/send/{order}', 'OrderController@send')->name('order.send');
        // 重新发送订单
        Route::post('order/resend', 'OrderController@resend')->name('order.resend');
        // 物品已送回
        Route::post('order/returned', 'OrderController@returned')->name('order.returned');
        // Route::post('order2', 'OrderController@store2')->name('order.store2');
        // 取消订单
        Route::delete('order/cancel2/{order}', 'OrderController@cancel2')->name('api.order.cancel2');
        // 通过订单获取门店详情（配送平台）
        Route::get('/order/getShopInfoByOrder', "OrderController@getShopInfoByOrder")->name('api.order.getShopInfoByOrder');
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
         * 采购商城
         */
        Route::prefix("/purchase")->group(function () {
            // 采购购物车
            Route::get("category", "SupplierCategoryController@index");
            // 采购商品列表
            Route::get("product", "SupplierProductController@index");
            // 采购活动商品列表
            Route::get("active/product", "SupplierProductController@activeList");
            // 采购商品详情
            Route::get("product/{supplier_product}", "SupplierProductController@show");
            // 供货商信息
            Route::get("shop/info", "SupplierShopController@show");
            // 供货商商品列表
            Route::get("shop/product", "SupplierShopController@productList");
            // 采购购物车列表
            Route::get("cart", "SupplierCartController@index");
            // 采购购物车添加
            Route::post("cart", "SupplierCartController@store");
            // 采购购物车删除
            Route::delete("cart", "SupplierCartController@destroy");
            // 采购购物车-修改数量
            Route::post("cart/change", "SupplierCartController@change");
            // 采购购物车-设置选中
            Route::post("cart/checked", "SupplierCartController@checked");
            // 采购购物车-结算
            Route::get("cart/settlement", "SupplierCartController@settlement");
            // 地址
            Route::get("address", "SupplierAddressController@index");
            // 订单
            Route::resource("order", "SupplierOrderController");
            // 支付订单
            Route::post("order/pay", "PaymentController@pay");
            // 支付订单页面调用-订单详情
            Route::get("payOrders", "SupplierOrderController@payOrders");
            // 收货
            Route::post("received/{supplier_order}", "SupplierOrderController@received")->name("supplier.order.received");
            // 供货商审核列表
            Route::get("supplier/authList", "SupplierUserController@user");
            // 供货商审核
            Route::post("supplier/setAuth", "SupplierUserController@example");
            // 药品审核列表
            Route::get("depot/authList", "ExampleProductController@index");
            // 药品审核
            Route::post("depot/setAuth", "ExampleProductController@setAuth");
        });
        // 设置默认收货门店
        Route::post("shop/userShop", "ShopController@setUserShop");
    });
});

/**
 * 回调接口
 */

/**
 * 支付回调接口
 */
Route::group(['namespace' => 'Api'], function () {
    Route::post('payment/wechat/notify', 'PaymentController@wechatNotify');
    Route::post('payment/wechat/notify_supplier', 'PaymentController@wechatSupplierNotify');
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
 * 顺丰回调接口
 */
Route::namespace('Api')->prefix('shunfeng')->group(function () {
    // 结算订单
    Route::post('order/status', "ShunfengController@status");
    // 隐私号降级
    Route::post('order/complete', "ShunfengController@complete");
    // 创建订单
    Route::post('order/cancel', "ShunfengController@cancel");
    // 取消订单
    Route::post('order/fail', "ShunfengController@fail");
});

/**
 * 美团外卖民康回调接口
 */
Route::namespace('Api')->prefix('waimai')->group(function () {
    // 结算订单
    Route::post('minkang/confirm', "MinKangController@confirm");
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


/**
 * 美团测试接口
 */
Route::namespace('Test')->prefix("test/mt")->group(function () {
    Route::get('shopIdList', 'MeiTuanTestController@shopIdList');
});
