<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\VipOrderExport;
use App\Exports\Admin\VipOrderProductExport;
use App\Http\Controllers\Controller;
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

        // $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

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
        if ($shop_id = $request->get('shop_id', '')) {
            $query->where('shop_id', $shop_id);
        }
        if ($name = $request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }
        if ($stime = $request->get('stime', '')) {
            $query->where('created_at', '>=', $stime);
        }
        if ($etime = $request->get('etime', '')) {
            $query->where('created_at', '<', date("Y-m-d H:i:s", strtotime($etime) + 86400));
        }

        // 判断角色
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
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
        // if (!$order = WmOrder::with('items')->find($request->get('order_id', 0))) {
        //     return $this->error('订单不存在');
        // }

        $vip_order->load('items');

        return $this->success($vip_order);
    }

    public function export_order(Request $request, VipOrderExport $export)
    {
        return $export->withRequest($request);
    }

    public function export_product(Request $request, VipOrderProductExport $export)
    {
        return $export->withRequest($request);
    }
}
