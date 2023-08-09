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
            Route::get("info", "OrderController@show");
            Route::get("calculate", "OrderController@calculate");
            Route::get("send", "OrderController@send");
            Route::get("cancel", "OrderController@cancel");
            Route::get("ignore", "OrderController@ignore");
        });
        // 门店
        Route::prefix('shop')->group(function () {
            Route::get("index", "ShopController@index");
        });
    });
});
