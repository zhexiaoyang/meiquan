<?php

use Illuminate\Support\Facades\Route;

Route::post('auth/login', 'AuthController@login');


Route::post('auth/logout', 'AuthController@logout');
Route::post('auth/register', 'AuthController@register');
Route::get('user/info', 'AuthController@user');

Route::resource('shop', "ShopController", ['only' => ['store', 'show', 'index']]);
Route::resource('user', "UserController", ['only' => ['store', 'show', 'index']]);
Route::resource('order', "OrderController", ['only' => ['store', 'show', 'index', 'destroy']]);
Route::get('order/status/{order}', "OrderController@checkStatus");
Route::get('order/location/{order}', "OrderController@location");


Route::namespace('MeiTuan')->prefix('mt')->group(function () {
    // 门店状态回调
    Route::post('shop/status', "ShopController@status");
    Route::post('order/status', "OrderController@status");
    Route::post('order/exception', "OrderController@exception");
});
