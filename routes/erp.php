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
    });
});
Route::middleware(['force-json'])->prefix("test/erp")->namespace("Erp")->group(function() {
    Route::prefix("v1")->namespace("V1")->group(function() {
        Route::post("product/stock", "ProductController@testStock");
        Route::post("product/add", "ProductController@testAdd");
        Route::post("product/code/update", "ProductController@codeUpdate");
    });
});
