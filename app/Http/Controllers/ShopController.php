<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtShop;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $shops = Shop::query()->orderBy('id', 'desc')->paginate();
        return $this->success($shops);
    }

    public function store(Request $request, Shop $shop)
    {
        $shop->fill($request->all());
        if ($shop->save()) {
            dispatch(new CreateMtShop($shop));
        }
    }

    public function show(Shop $shop)
    {
        $meituan = app("meituan");

        return $meituan->shopInfo(['shop_id' => $shop->shop_id]);
    }
}
