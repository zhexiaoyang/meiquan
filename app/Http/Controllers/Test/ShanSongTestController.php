<?php


namespace App\Http\Controllers\Test;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShanSongTestController extends Controller
{

    private $shansong;

    public function __construct()
    {
        $this->shansong = app('shansong');
    }

    // 创建门店
    public function createShop(Request $request)
    {
        return $this->shansong->createShop($request->all());
    }

    // 获取所有门店
    public function getShop()
    {
        return $this->shansong->getShop();
    }

    // 订单

    // 订单计价
    public function orderCalculate(Request $request)
    {
        return $this->shansong->orderCalculate($request->all());
    }

    // 创建订单
    public function createOrder(Request $request)
    {
        return $this->shansong->createOrder($request->get('order_id'));
    }

    // 取消订单
    public function cancelOrder(Request $request)
    {
        return $this->shansong->cancelOrder($request->get('order_id'));
    }

    // 查询订单状态
    public function getOrder(Request $request)
    {
        return $this->shansong->getOrder($request->all());
    }

    // 其它

    public function carrier(Request $request)
    {
        return $this->shansong->carrier($request->all());
    }

}