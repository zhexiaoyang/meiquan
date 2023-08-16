<?php

use Illuminate\Support\Facades\Route;

/**
 * 订单接口
 */
Route::middleware(['force-json'])->prefix("app")->namespace("Delivery\V1")->group(function() {
    // 支付宝APP支付回调
    Route::post("pay/notify/alipay", "PaymentController@alipay_notify");
    // 需要登录
    Route::middleware("multiauth:api")->group(function () {

        Route::prefix('account')->group(function () {
            // 用户信息
            Route::get("user", "AccountController@user_info");
            // 余额记录
            Route::get("money_balance", "AccountController@money_balance");
            // 余额充值-获取支付订单
            Route::post("pay", "PaymentController@pay");
            // 支付方式
            Route::get("pay_method", "PaymentController@pay_method");
        });
        // 订单
        Route::prefix('order')->group(function () {
            // 订单数量统计
            Route::get("statistics", "OrderController@statistics");
            // 订单列表
            Route::get("index", "OrderController@index");
            // 订单搜索
            Route::get("search", "OrderController@search_list");
            // 订单详情
            Route::get("info", "OrderController@show");
            // 计算订单
            Route::get("calculate", "OrderController@calculate");
            // 计算订单后派单
            Route::post("send", "OrderController@send");
            // 取消订单
            Route::post("cancel", "OrderController@cancel");
            // 忽略订单
            Route::post("ignore", "OrderController@ignore");
            // 加小费
            Route::post("add_tip", "OrderController@add_tip");
            // 打印订单
            Route::get("print_order", "OrderController@print_order");
            // 订单操作日志
            Route::get("operate_record", "OrderController@operate_record");
            // 手动下单-地址识别
            Route::post("address_recognition", "OrderController@address_recognition");
            // 手动下单-地址识别-地址搜索
            Route::post("map_search", "OrderController@map_search");
            // 手动下单
            Route::post("create", "OrderController@store");
        });
        // 门店
        Route::prefix('shop')->group(function () {
            // 顶部门店列表
            Route::get("index", "ShopController@index");
            // 门店分类
            Route::get("category", "ShopController@category");
            // 线上店铺
            Route::get("takeout", "ShopController@takeout");
            // 线上店铺-统计
            Route::get("takeout_statistics", "ShopController@takeout_statistics");
            // 绑定门店
            Route::get("bind_shop", "ShopController@bind_shop");
        });
        // 数据分析
        Route::prefix('analysis')->group(function () {
            // 概况
            Route::get("business", "AnalysisController@business");
            // 折线图
            Route::get("history", "AnalysisController@history");
            // 门店
            Route::get("shop", "AnalysisController@shop");
            // 运力
            Route::get("delivery", "AnalysisController@delivery");
            // 渠道
            Route::get("channel", "AnalysisController@channel");
        });
        // 数据分析
        Route::prefix('medicine')->group(function () {
            // 门店列表
            Route::get("shops", "MedicineController@shops");
            // 药品列表
            Route::get("index", "MedicineController@index");
            // 药品列表-统计
            Route::get("statistics", "MedicineController@statistics");
            // 更新药品-价格
            Route::post("update_price", "MedicineController@update_price");
            // 更新药品-库存
            Route::post("update_stock", "MedicineController@update_stock");
            // 更新药品-同步
            Route::post("update_sync", "MedicineController@update_sync");
        });
    });
});
