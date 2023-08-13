<?php

use Illuminate\Support\Facades\Route;

/**
 * 订单接口
 */
Route::middleware(['force-json'])->prefix("app")->namespace("Delivery\V1")->group(function() {
    // 需要登录
    Route::middleware("multiauth:api")->group(function () {
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
            Route::get("index", "ShopController@index");
        });
        // 数据分析
        Route::prefix('analysis')->group(function () {
            Route::get("business", "AnalysisController@business");
            Route::get("history", "AnalysisController@history");
            Route::get("shop", "AnalysisController@shop");
            Route::get("delivery", "AnalysisController@delivery");
        });
    });
});
