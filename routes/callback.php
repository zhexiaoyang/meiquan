<?php

use Illuminate\Support\Facades\Route;

/**
 * 回调接口
 */

// 契约锁回调
Route::middleware(['force-json'])->prefix('qiyuesuo')->namespace('Api')->group(function () {
    // 企业认证回调
    Route::post('company/auth/status', "QiYueSuoController@companyAuth");
    // 合同状态回调
    Route::post('contract/status', "QiYueSuoController@contractStatus");
});
