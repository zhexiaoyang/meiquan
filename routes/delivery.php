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
            Route::get("statistics", "OrderController@statistics");
            Route::get("index", "OrderController@index");
            Route::get("search", "OrderController@searchList");
            Route::get("info", "OrderController@show");
            Route::get("calculate", "OrderController@calculate");
            Route::post("send", "OrderController@send");
            Route::post("cancel", "OrderController@cancel");
            // 忽略订单
            Route::post("ignore", "OrderController@ignore");
            // 加小费
            Route::post("add_tip", "OrderController@add_tip");
            Route::get("print_order", "OrderController@print_order");
            Route::get("operate_record", "OrderController@operate_record");
        });
        // 门店
        Route::prefix('shop')->group(function () {
            Route::get("index", "ShopController@index");
        });
    });
});
