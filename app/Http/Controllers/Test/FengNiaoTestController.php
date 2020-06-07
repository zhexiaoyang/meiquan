<?php


namespace App\Http\Controllers\Test;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FengNiaoTestController extends Controller
{

    private $fengniap;

    public function __construct()
    {
        $this->fengniap = app('fengniao');
    }

    public function createShop(Request $request)
    {
        return $this->fengniap->createShop($request->all());
    }

    public function updateShop(Request $request)
    {
        return $this->fengniap->updateShop($request->all());
    }

    public function getShop(Request $request)
    {
        return $this->fengniap->getShop($request->all());
    }

    public function getArea(Request $request)
    {
        return $this->fengniap->getArea($request->all());
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