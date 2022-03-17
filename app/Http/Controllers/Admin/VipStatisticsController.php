<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class VipStatisticsController extends Controller
{
    public function order(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');
        $shop_id = $request->get('shop_id');
        $city = $request->get('city');

        // 折线图
        $data = $this->initOrderDayData($sdate, $edate);
        // VIP门店总数
        $shop_query = Shop::where('vip_status', 1);
        if ($sdate && $edate) {
            $shop_query->where('vip_at', '>=', $sdate)->where('vip_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        if ($city) {
            $shop_query->where('city', $city);
        }
        $shops = $shop_query->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $data[date("Y-m-d", strtotime($shop->vip_at))]['VIP门店']++;
            }
        }
        // VIP订单
        $order_sale = 0;
        $order_profit = 0;
        $order_total = 0;
        $order_cancel = 0;
        $order_query = WmOrder::where('is_vip', 1);
        if ($sdate && $edate) {
            $order_query->where('created_at', '>=', $sdate)->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        if ($shop_id) {
            $order_query->where('shop_id', $shop_id);
        }
        if ($city) {
            $order_query->whereIn('shop_id', Shop::where('city', $city)->pluck('id'));
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($order->status == 18) {
                    $order_at = date('Y-m-d', strtotime($order->created_at));
                    $order_total++;
                    $order_sale += $order->poi_receive;
                    $order_profit += ($order->poi_receive - $order->vip_cost);
                    $data[$order_at]['有效订单']++;
                    $data[$order_at]['销售额'] += $order->poi_receive;
                    $data[$order_at]['总利润'] += ($order->poi_receive - $order->vip_cost);
                } else {
                    $order_cancel++;
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
            'shop_total' => $shops->count(),
            'order_total' => $order_total,
            'order_cancel' => $order_cancel,
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
                'VIP门店' => 0,
            ];
            $sdate = date("Y-m-d", strtotime($sdate) + 86400);
        }

        return $data;
    }
}
