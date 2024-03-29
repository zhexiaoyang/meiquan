<?php

use Illuminate\Support\Facades\Route;

/**
 * ERP接口
 */
Route::middleware(['force-json'])->prefix("erp")->namespace("Erp")->group(function() {
    Route::prefix("v1")->namespace("V1")->group(function() {
        Route::post("product/stock", "ProductController@stock");
        Route::post("product/add", "ProductController@add");
        Route::post("test/product/stock", "ProductController@testStock");
        Route::post("test/product/add", "ProductController@testAdd");
        Route::post("product/code/update", "ProductController@codeUpdate");
    });
    Route::prefix("v2")->namespace("V2")->group(function() {
        Route::post("product/stock", "ProductController@stock");
        // ERP接口
        Route::post("medicine/list", "MedicineController@index");
        Route::post("medicine/add", "MedicineController@add");
        Route::post("medicine/update", "MedicineController@update");
        Route::post("medicine/delete", "MedicineController@delete");
        Route::post("order/info", "OrderController@info");
        Route::post("order/status", "OrderController@orderStatus");
        Route::post("order/no", "OrderController@order_no");
    });
});
Route::middleware(['force-json'])->prefix("test/erp")->namespace("Erp")->group(function() {
    Route::prefix("v1")->namespace("V1")->group(function() {
        Route::post("product/stock", "ProductController@testStock");
        Route::post("product/add", "ProductController@testAdd");
        Route::post("product/code/update", "ProductController@codeUpdate");
    });
});

/**
 * 订单接口
 */
Route::middleware(['force-json'])->prefix("open/v1")->namespace("OpenApi\V1")->group(function() {
    Route::post("order/calculate", "OrderController@calculate");
    Route::post("order/create", "OrderController@create");
    Route::post("order/info", "OrderController@info");
    Route::post("order/cancel", "OrderController@cancel");
});
