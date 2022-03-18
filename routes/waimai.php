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

/**
 * 美全科技-美团三方开发者-餐饮服务商
 */
Route::middleware(['force-json'])->prefix('meituan/minkang')->namespace('Api\Waimai\MinKang')->group(function () {
    // 推送美配订单配送状态回调
    // https://psapi.meiquanda.com/api/waimai/meituan/minkang/order/refund
    // 推送已支付订单
    Route::post('order/create', "OrderController@create");
    // 推送已确认订单
    // Route::post('order/confirm', "OrderController@confirm");
    // 推送用户或客服取消订单
    Route::post('order/cancel', "OrderController@cancel");
    // 推送全额退款信息
    Route::any('order/refund', "OrderController@refund");
    // 推送部分退款信息
    Route::any('order/partrefund', "OrderController@partrefund");
    // 推送美配订单配送状态
    Route::post('order/rider', "OrderController@rider");
    // 推送已完成订单
    Route::post('order/finish', "OrderController@finish");
    // 推送催单消息
    // Route::post('order/remind', "OrderController@remind");
    // 隐私号降级通知
    // Route::post('order/down', "OrderController@down");
    // 自配订单配送状态
    Route::post('order/status/self', "OrderController@status_self");
    // 门店绑定
    // Route::post('shop/bind', "ShopController@bind");
    // 门店解绑
    // Route::post('shop/unbound', "ShopController@unbound");
});

/**
 * 美团外卖-闪购-服务商
 */
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

/**
 * 美全科技-美团三方开发者-餐饮服务商
 */
Route::middleware(['force-json'])->prefix('meituan/sanfang')->namespace('Api\Waimai\MeiTuanSanFang')->group(function () {
    // 推送美配订单配送状态回调
    // https://psapi.meiquanda.com/api/waimai/meituan/sanfang/order/cancel
    Route::post('order/create', "OrderController@create");
    Route::post('order/confirm', "OrderController@confirm");
    Route::post('order/cancel', "OrderController@cancel");
    Route::post('order/refund', "OrderController@refund");
    Route::post('order/rider', "OrderController@rider");
    Route::post('order/finish', "OrderController@finish");
    Route::post('order/partrefund', "OrderController@partrefund");
    Route::post('order/remind', "OrderController@remind");
    Route::post('order/down', "OrderController@down");
    Route::post('order/bill', "OrderController@bill");
    // 门店绑定
    Route::post('shop/bind', "ShopController@bind");
    // 门店解绑
    Route::post('shop/unbound', "ShopController@unbound");
});

/**
 * 美全达跑腿
 */
Route::middleware(['force-json'])->prefix('mqd')->namespace('Api')->group(function () {
    Route::post('order', "MeiQuanDaController@order_status");
});

/**
 * 达达跑腿
 */
Route::middleware(['force-json'])->prefix('dada')->namespace('Api')->group(function () {
    Route::post('order', "DaDaController@order_status");
});

/**
 * UU跑腿
 */
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
