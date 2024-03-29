<?php

use Illuminate\Support\Facades\Route;

/**
 * 回调接口
 */

// 契约锁回调
Route::middleware(['force-json'])->prefix('qiyuesuo')->namespace('Api')->group(function () {
    // 企业认证回调
    Route::post('company/auth/status', "QiYueSuoController@companyAuth");
    // 企业合同状态回调
    Route::post('contract/status', "QiYueSuoController@contractStatus");
    // 门店认证回调
    Route::post('shop/auth/status', "QiYueSuoController@shopAuth");
    // 门店合同状态回调
    // Route::post('shop/contract/status', "QiYueSuoController@shopContract");
});

// 桃子医院
Route::middleware(['force-json'])->prefix('taozi')->namespace('Api\Callback')->group(function () {
    // 线下处方订单回调
    Route::post('order', "TaoziController@order");
});

// 快递100
Route::middleware(['force-json'])->prefix('kuaidi')->namespace('Api\Callback')->group(function () {
    // 线下处方订单回调
    Route::post('order', "KuaiDiController@order");
});

// 闪送-服务商
Route::middleware(['force-json'])->prefix('shansong')->namespace('Api\Callback')->group(function () {
    // 闪送订单回调
    Route::post('order', "ShanSongOrderController@order");
    // 闪送门店绑定回调
    Route::get('auth', "ShanSongAuthController@auth");
});
// 达达-服务商
Route::middleware(['force-json'])->prefix('dada')->namespace('Api\Callback')->group(function () {
    // 达达订单回调
    Route::post('order', "DaDaOrderController@order");
    // 达达门店绑定回调
    Route::get('auth', "DaDaAuthController@auth");
});
// 顺丰-服务商
Route::middleware(['force-json'])->prefix('shunfeng')->namespace('Api\Callback')->group(function () {
    // 顺丰订单回调
    Route::post('order/status', "ShunFengOrderController@order");
    Route::post('order/complete', "ShunFengOrderController@complete");
    Route::post('order/cancel', "ShunFengOrderController@cancel");
    Route::post('order/exceptional', "ShunFengOrderController@exceptional");
    Route::post('order/revoke', "ShunFengOrderController@revoke");
    // 顺丰门店绑定回调
    Route::any('auth', "ShunfengAuthController@auth");
});
