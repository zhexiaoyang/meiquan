<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtOrder;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $orders = Order::query()->orderBy('id', 'desc')->paginate();
        return $this->success($orders);
    }

    public function store(Request $request, Order $order)
    {
        $order->fill($request->all());
        if ($order->save()) {
            dispatch(new CreateMtOrder($order));
            return $this->success([]);
        }
        return $this->error("创建失败");
    }

    public function destroy(Order $order)
    {
        $meituan = app("meituan");
        $result = $meituan->delete([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
            'cancel_reason_id' => 101,
            'cancel_reason' => '顾客主动取消',
        ]);

        $order->update(['status' => 99]);
    }

    public function show(Order $order)
    {
        return $this->success($order);
    }

    public function checkStatus(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->queryStatus([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
        ]);

        return $this->success($result);
    }

    public function location(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->location([
            'delivery_id' => $order->delivery_id,
            'mt_peisong_id' => $order->mt_peisong_id,
        ]);

        return $this->success($result);
    }
}
