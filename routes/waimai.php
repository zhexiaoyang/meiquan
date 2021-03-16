<?php

use Illuminate\Support\Facades\Route;

/**
 * 接收外卖订单
 */

// 美全科技-美团-服务商
Route::middleware(['force-json'])->prefix('meituan/meiquan')->namespace('Api\Waimai')->group(function () {
    // 推送已支付订单回调
    Route::post('pay', "MeiTuanMeiquanController@pay");
    // 推送已确认订单回调
    Route::post('create', "MeiTuanMeiquanController@create");
    // 推送用户或客服取消订单回调
    Route::get('cancel', "MeiTuanMeiquanController@cancel");
    // 推送全额退款信息回调
    Route::get('refund', "MeiTuanMeiquanController@refund");
    // 推送部分退款信息回调
    Route::get('refund/part', "MeiTuanMeiquanController@refundPart");
    // 推送美配订单配送状态回调
    Route::post('logistics', "MeiTuanMeiquanController@logistics");
    // 推送美配订单配送状态回调
    Route::post('test', "MeiTuanMeiquanController@test");
});
