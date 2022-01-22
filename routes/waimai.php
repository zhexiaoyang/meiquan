<?php

use Illuminate\Support\Facades\Route;

/**
 * 接收外卖订单
 */

// 饿了么回调
Route::middleware(['force-json'])->prefix('ele')->namespace('Api\Waimai')->group(function () {
    Route::post('auth', "EleOrderController@auth");
    Route::post('order', "EleOrderController@order");
});

// 美全科技-美团-服务商
Route::middleware(['force-json'])->prefix('meituan/meiquan')->namespace('Api\Waimai')->group(function () {
    // 推送已支付订单回调
    Route::post('pay', "MeiTuanMeiquanController@pay");
    // 推送已支付订单回调
    Route::post('shop/bind ', "MeiTuanMeiquanController@bind");
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
Route::middleware(['force-json'])->prefix('meituan/meiquan')->namespace('Api\Waimai\MeiQuan')->group(function () {
    // 推送美配订单配送状态回调
    Route::post('order/status/own_delivery', "OrderStatusController@own_delivery");
});

// 美全达跑腿
Route::middleware(['force-json'])->prefix('mqd')->namespace('Api')->group(function () {
    Route::post('order', "MeiQuanDaController@order_status");
});

// 达达跑腿
Route::middleware(['force-json'])->prefix('dada')->namespace('Api')->group(function () {
    Route::post('order', "DaDaController@order_status");
});

// UU跑腿
Route::middleware(['force-json'])->prefix('uu')->namespace('Api')->group(function () {
    Route::post('order', "UuController@order_status");
});

// 寝趣
Route::middleware(['force-json'])->prefix('meituan/qinqu')->namespace('Api\Waimai')->group(function () {

    // // 推送已确认订单回调
    // Route::post('create', "QinQuController@create");
    // // 推送用户或客服取消订单回调
    // Route::get('cancel', "QinQuController@cancel");


    // 推送已支付订单回调
    // Route::post('pay', "MeiTuanMeiquanController@pay");
    // 推送全额退款信息回调
    // Route::get('refund', "MeiTuanMeiquanController@refund");
    // 推送部分退款信息回调
    // Route::get('refund/part', "MeiTuanMeiquanController@refundPart");
    // 推送美配订单配送状态回调
    // Route::post('logistics', "MeiTuanMeiquanController@logistics");
    // 推送美配订单配送状态回调
    // Route::post('test', "MeiTuanMeiquanController@test");
});
