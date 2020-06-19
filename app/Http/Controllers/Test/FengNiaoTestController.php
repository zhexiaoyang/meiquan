<?php


namespace App\Http\Controllers\Test;


use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class FengNiaoTestController extends Controller
{

    private $fengniap;

    public function __construct()
    {
        $this->fengniap = app('fengniao');
    }

    public function createShop(Request $request, Shop $shop)
    {
        $shop->shop_name = $request->name;
        $shop->contact_phone = $request->phone;
        $shop->shop_address = $request->address;
        $shop->shop_lng = $request->shop_lng;
        $shop->shop_lat = $request->shop_lat;
        $shop->save();

        return $this->fengniap->createShop($shop);
    }

    // public function updateShop(Request $request)
    // {
    //     return $this->fengniap->updateShop($request->all());
    // }

    public function getShop(Request $request)
    {
        return $this->fengniap->getShop($request->get("shop_id"));
    }

    public function getArea(Request $request)
    {
        return $this->fengniap->getArea($request->get("shop_id"));
    }

    // 订单


    public function createOrder(Request $request)
    {
        return $this->fengniap->createOrder($request->all());
    }

    public function cancelOrder(Request $request)
    {
        return $this->fengniap->cancelOrder($request->all());
    }

    public function getOrder(Request $request)
    {
        return $this->fengniap->getOrder($request->get('order_id'));
    }

    public function complaintOrder(Request $request)
    {
        return $this->fengniap->complaintOrder($request->all());
    }

    // 其它


    public function delivery(Request $request)
    {
        return $this->fengniap->delivery($request->all());
    }


    public function carrier(Request $request)
    {
        return $this->fengniap->carrier($request->all());
    }


    public function route(Request $request)
    {
        return $this->fengniap->route($request->all());
    }

}