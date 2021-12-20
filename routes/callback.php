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
