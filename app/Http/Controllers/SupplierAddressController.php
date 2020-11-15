<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class SupplierAddressController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;

        $shops = Shop::query()->select("id", "shop_address")->where("own_id", $user_id)->where('auth', 10)
            ->orderBy("id", "asc")->get();

        return $this->success($shops);
    }
}
