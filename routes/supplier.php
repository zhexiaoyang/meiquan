<?php

use Illuminate\Support\Facades\Route;

/**
 * 供货商端
 */
// 验证码
Route::post('supplier/code', 'CommonController@getVerifyCode')->name('supplier.code');
Route::middleware(['force-json'])->prefix("supplier")->namespace("Supplier")->group(function() {
    // 登录
    Route::post('auth/login', 'AuthController@login');
    // 退出登录
    Route::post('auth/logout', 'AuthController@logout');
    // 需要登录接口
    Route::middleware(["multiauth:supplier"])->group(function() {
        Route::post("auth/logout", "AuthController@logout");
        Route::get("auth/me", "AuthController@me");
        Route::prefix("product")->group(function () {
            // 品库列表
            Route::get("depot", "ProductController@depot");
            // 品库列表
            Route::get("depot_yun", "ProductController@depot_yun");
            // 商品列表
            Route::get("index", "ProductController@index");
            // 商品详情
            Route::get("show", "ProductController@show");
            // 商品分类
            Route::get("category", "ProductController@category");
            // 添加商品
            Route::post("store", "ProductController@store");
            // 编辑商品
            Route::post("update", "ProductController@update");
            // 品库添加商品
            Route::post("add", "ProductController@add");
            Route::post("add_yun", "ProductController@add_yun");
            // 删除商品
            Route::post("destroy", "ProductController@destroy");
            // 商品上下架
            Route::post("online", "ProductController@online");
            // 商品销售类型修改
            Route::post("saleType", "ProductController@saleType");
            // 城市价格获取
            Route::get("city", "ProductController@getCityPrice");
            // 城市价格设置
            Route::post("city", "ProductController@setCityPrice");
            // 城市价格删除
            Route::delete("city", "ProductController@deleteCityPrice");
        });

        Route::prefix("order")->group(function () {
            // 订单列表
            Route::get("index", "OrderController@index");
            // 导出订单
            Route::get("export", "OrderController@export");
            // 导出订单商品
            Route::get("/product/export", "OrderController@exportProduct");
            // 订单详情
            Route::get("show", "OrderController@show");
            // 订单资质
            Route::get("qualifications", "OrderController@qualifications");
            // 收到纸质
            Route::post("receiveQualification", "OrderController@receiveQualification");
            // 快递列表
            Route::get("express", "OrderController@express");
            // 订单发货
            Route::post("deliver", "OrderController@deliver");
            // 取消订单
            Route::post("cancel", "OrderController@cancel");
            // 可以开发票的订单
            Route::post("invoice", "OrderController@invoice");
        });

        // 供货商
        Route::prefix("user")->group(function () {
            // 供货商-我的余额
            Route::get("money", "UserController@money");
            // 供货商信息
            Route::get("", "UserController@show");
            // 设置供货商信息
            Route::post("", "UserController@store");
            // 修改供货商信息
            Route::post("update", "UserController@update");
        });

        // 配送费
        Route::prefix("freight")->group(function () {
            // 城市列表
            Route::get("/city", "ProvinceController@cities");
            // 配送费列表
            Route::get("", "FreightController@index");
            // 配送费详情
            Route::get("/info", "FreightController@show");
            // 设置配送费
            Route::post("", "FreightController@store");
            // 删除配送费
            Route::delete("", "FreightController@destroy");
        });

        // 余额提现
        Route::prefix("withdrawal")->group(function () {
            // 提现列表
            Route::get("", "WithdrawalController@index");
            // 申请提现
            Route::post("", "WithdrawalController@store");
        });

        // 余额明细
        Route::prefix("balance")->group(function () {
            // 列表
            Route::get("", "UserBalanceController@index");
        });

        // 发票信息
        Route::prefix("invoice")->group(function () {
            // 发票抬头信息
            Route::get("title", "InvoiceTitleController@show");
            // 设置发票抬头信息
            Route::post("title", "InvoiceTitleController@save");
            // 可以开发票订单列表
            Route::get("order", "InvoiceController@order");
            // 申请开发票列表
            Route::get("", "InvoiceController@index");
            // 开发票
            Route::post("", "InvoiceController@store");
        });
    });
});
