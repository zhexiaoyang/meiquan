<?php

use Illuminate\Support\Facades\Route;

/**
 * 订单接口
 */
Route::middleware(['force-json'])->prefix("app/order")->namespace("Delivery\V1")->group(function() {
    // 需要登录
    Route::middleware("multiauth:api")->group(function () {
        Route::get("index", "OrderController@index");
        Route::get("info", "OrderController@show");
        Route::post("cancel", "OrderController@cancel");
    });
});
