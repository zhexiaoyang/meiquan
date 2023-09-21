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
 * 美团外卖-统一回调
 */
Route::middleware(['force-json'])->prefix('meituan/callback')->namespace('Api\Waimai\MeiTuanWaiMai')->group(function () {
    // https://psapi.meiquanda.com/api/waimai/meituan/callback/order/confirm/4
    // 推送已支付订单
    Route::post('order/create/{platform}', "OrderController@create");
    // 全部退款
    Route::any('order/refund/{platform}', "OrderController@refund");
    // 部分退款
    Route::any('order/partrefund/{platform}', "OrderController@partrefund");
    // 推送美配订单配送状态
    Route::post('order/rider/{platform}', "OrderController@rider");
    // 推送美配订单异常配送状态
    Route::post('order/rider_exception/{platform}', "OrderController@rider_exception");
    // 自配订单配送状态
    Route::post('order/status/self/{platform}', "OrderController@status_self");
    // 推送已完成订单
    Route::post('order/finish/{platform}', "OrderController@finish");
    // 推送订单结算信息
    Route::post('order/settlement/{platform}', "OrderController@settlement");
    // 已确认订单
    Route::any('order/confirm/{platform}', "OrderConfirmController@confirm");
    // 取消订单
    Route::any('order/cancel/{platform}', "OrderCancelController@cancel");
    // 推送催单消息
    Route::post('order/remind/{platform}', "OrderController@remind");
    // 隐私号降级通知
    Route::post('order/down/{platform}', "OrderController@down");
    // 美团实时拉取商家单门店下指定商品库存
    Route::post('product/stock/{platform}', "ProductStockController@stock");
    // 创建商品
    Route::post('product/create/{platform}', "ProductController@create");
    // 更新商品
    Route::post('product/update/{platform}', "ProductController@update");
    // 删除商品
    Route::post('product/delete/{platform}', "ProductController@delete");
    // 门店状态变更
    Route::post('shop/status/{platform}', "ShopController@status");
    // 门店绑定状态
    Route::post('shop/bind/{platform}', "ShopBindController@status");
    // IM消息推送
    Route::post('im/create/{platform}', "ImController@create");
});

/**
 * 美团外卖-民康开发者
 */
Route::middleware(['force-json'])->prefix('meituan/minkang')->namespace('Api\Waimai\MinKang')->group(function () {
    // 推送美配订单配送状态回调
    // https://psapi.meiquanda.com/api/waimai/meituan/minkang/order/refund
    // 推送已支付订单
    Route::post('order/create', "OrderController@create");
    // 推送已确认订单
    Route::post('order/confirm', "OrderConfirmController@confirm");
    // 推送用户或客服取消订单
    Route::post('order/cancel', "OrderCancelController@cancel");
    // 推送全额退款信息
    Route::any('order/refund', "OrderController@refund");
    // 推送部分退款信息
    Route::any('order/partrefund', "OrderController@partrefund");
    // 推送美配订单配送状态
    Route::post('order/rider', "OrderController@rider");
    // 推送已完成订单
    Route::post('order/finish', "OrderController@finish");
    // 推送订单结算信息
    Route::post('order/settlement', "OrderController@settlement");
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
    // 创建商品
    Route::post('product/create', "ProductController@create");
    // 更新商品
    Route::post('product/update', "ProductController@update");
    // 删除商品
    Route::post('product/delete', "ProductController@delete");
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
 * 美全科技-美团三方开发者-【餐饮、闪购等】服务商
 */
Route::middleware(['force-json'])->prefix('meituan/sanfang')->namespace('Api\Waimai\MeiTuanSanFang')->group(function () {
    // 推送美配订单配送状态回调
    // https://psapi.meiquanda.com/api/waimai/meituan/sanfang/order/cancel
    Route::post('order/create', "OrderController@create");
    Route::post('order/confirm', "OrderConfirmController@confirm");
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
 * 美全科技-美团三方开发者-【餐饮、闪购等】服务商-【非接单】
 */
Route::middleware(['force-json'])->prefix('meituan/sanfang')->namespace('Api\Waimai\MeiTuanSanFang')->group(function () {
    // https://psapi.meiquanda.com/api/waimai/meituan/sanfang/fei/order
    // https://psapi.meiquanda.com/api/waimai/meituan/sanfang/fei/rider
    // https://psapi.meiquanda.com/api/waimai/meituan/sanfang/fei/marketing
    Route::post('fei/order', "OrderConfirmController@confirm");
    Route::post('fei/rider', "FeiController@rider");
    Route::post('fei/marketing', "FeiController@marketing");
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
    Route::post('message', "DaDaController@dadaMessage");
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
