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
