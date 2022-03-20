<?php

namespace App\Http\Controllers;

use App\Models\Shop;
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

    public function dashboard(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');
        $shop_id = $request->get('shop_id');
        $user_id = $request->user()->id;

        // 折线图
        $data = $this->initOrderDayData($sdate, $edate);
        // VIP门店总数
        $shops = Shop::query()->where('vip_status', 1)->where('user_id', $user_id)->pluck('id');
        // VIP订单
        $order_sale = 0;
        $order_profit = 0;
        $order_total = 0;
        $order_query = WmOrder::where('is_vip', 1)->whereIn('shop_id', $shops);
        $order_cancel_query = WmOrder::where('is_vip', 1)->whereIn('shop_id', $shops);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
            $order_cancel_query->where('cancel_at', '>=', $sdate)->where('cancel_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        if ($shop_id) {
            $order_query->where('shop_id', $shop_id);
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->status == 18) {
                    $order_at = date('Y-m-d', strtotime($order->finish_at));
                    $order_total++;
                    $order_sale += $order->poi_receive;
                    $order_profit += $order->vip_business;
                    $data[$order_at]['有效订单']++;
                    $data[$order_at]['销售额'] += $order->poi_receive;
                    $data[$order_at]['总利润'] += $order->vip_business;
                }
            }
        }

        if (!empty($data)) {
            foreach ($data as $k => $v) {
                // $data[$k]['销售额'] = number_format((float) $data[$k]['销售额'], 2);
                $data[$k]['销售额'] = (float) sprintf("%.2f", $data[$k]['销售额']);
                $data[$k]['总利润'] = (float) sprintf("%.2f", $data[$k]['总利润']);
            }
        }

        $result = [
            'data' => array_values($data),
            'shop_total' => count($shops),
            'order_total' => $order_total,
            'order_cancel' => $order_cancel_query->count(),
            'order_sale' => number_format($order_sale, 2),
            'order_profit' => number_format($order_profit, 2),
        ];
        return $this->success($result);
    }

    public function initOrderDayData($sdate, $edate)
    {
        $data = [];

        while (strtotime($sdate) <= strtotime($edate)) {
            $data[$sdate] = [
                'day' => date("m-d", strtotime($sdate)),
                '销售额' => 0,
                '有效订单' => 0,
                '总利润' => 0,
            ];
            $sdate = date("Y-m-d", strtotime($sdate) + 86400);
        }

        return $data;
    }
}
