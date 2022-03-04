<?php

namespace App\Http\Controllers;

use App\Models\WmOrder;
use Illuminate\Http\Request;

class VipOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $query = WmOrder::with(['items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc','vip_cost');
        }, 'receives'])->where('is_vip', 1);

        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        if ($status = $request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $request->get('order_id', '')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }
        if ($name = $request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        if (!empty($data)) {
            foreach ($data as $order) {
                $order->ctime = date("Y-m-d H:i:s", $order->ctime);
                $order->estimate_arrival_time = date("Y-m-d H:i:s", $order->estimate_arrival_time);
                $ping_fee = 0;
                $poi_fee = 0;
                if (!empty($order->receives)) {
                    foreach ($order->receives as $receive) {
                        if ($receive->type == 1) {
                            $ping_fee += $receive->money;
                        } else {
                            $poi_fee += $receive->money;
                        }
                    }
                }
                $order->ping_fee = $ping_fee;
                $order->poi_fee = $poi_fee;
            }
        }

        return $this->page($data);
    }

    public function show(WmOrder $vip_order)
    {
        $vip_order->load('items');

        return $this->success($vip_order);
    }

    public function statistic()
    {
        $statistic = [
            'number_mt' => 0,
            'money_mt' => 0,
            'return_mt' => 0,
            'number_ele' => 0,
            'money_ele' => 0,
            'return_ele' => 0,
        ];

        return $this->success($statistic);
    }
}
