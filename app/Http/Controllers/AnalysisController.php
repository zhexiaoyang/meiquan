<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shop;
use App\Models\WmAnalysis;
use App\Models\WmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    public function business(Request $request)
    {
        $res = [
            // 销售总额
            'sales_volume' => 0,
            // 订单实收
            'order_receipts' => 0,
            // 订单总数
            'order_total_number' => 0,
            // 完成订单
            'order_complete_number' => 0,
            // 进行中订单
            'order_ongoing_number' => 0,
            // 商品成本
            'product_cost' => 0,
            // 取消订单
            'order_cancel_number' => 0,
            // 单均价
            'order_average' => 0,
            // 跑腿支持
            'running_money' => 0,
            // 处方审方费
            'prescription' => 0,
            // 利润
            'profit' => 0,
            // --------------------------------
            // 销售总额
            'sales_volume_compare' => 0,
            // 订单实收
            'order_receipts_compare' => 0,
            // 订单总数
            'order_total_number_compare' => 0,
            // 完成订单
            'order_complete_number_compare' => 0,
            // 进行中订单
            'order_ongoing_number_compare' => 0,
            // 商品成本
            'product_cost_compare' => 0,
            // 取消订单
            'order_cancel_number_compare' => 0,
            // 单均价
            'order_average_compare' => 0,
            // 跑腿支持
            'running_money_compare' => 0,
            // 处方审方费
            'prescription_compare' => 0,
            // 利润
            'profit_compare' => 0,
        ];
        $res2 = [
            // 销售总额
            'sales_volume' => 0,
            // 订单实收
            'order_receipts' => 0,
            // 订单总数
            'order_total_number' => 0,
            // 完成订单
            'order_complete_number' => 0,
            // 进行中订单
            'order_ongoing_number' => 0,
            // 商品成本
            'product_cost' => 0,
            // 取消订单
            'order_cancel_number' => 0,
            // 单均价
            'order_average' => 0,
            // 跑腿支持
            'running_money' => 0,
            // 处方审方费
            'prescription' => 0,
            // 利润
            'profit' => 0,
        ];

        $query = WmOrder::with(['running' => function($query) {
            $query->select('id', 'wm_id', 'status', 'money');
        }])->select('id', 'poi_receive', 'original_price', 'prescription_fee', 'vip_cost', 'status', 'finish_at', 'cancel_at');

        if ($shop_id = $request->get('shop_id')) {
            if (!Shop::where('user_id', $request->user()->id)->where('id', $shop_id)->first()) {
                return $this->error('门店不存在');
            }
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $query2 = clone($query);
        $orders = $query->where('created_at', '>=', date("Y-m-d"))->get();
        $orders2 = $query2->where('created_at', '>=', date("Y-m-d", time() - 86400))
            ->where('created_at','<=', date("Y-m-d H:i:s", time() - 86400))->get();
            // ->where('finish_at','<=', date("Y-m-d H:i:s", time() - 86400))->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $running_money = 0;
                $res['order_total_number'] ++;
                if ($order->status == 18) {
                    $res['order_complete_number']++;
                } elseif ($order->status > 18) {
                    $res['order_cancel_number'] ++;
                } else {
                    $res['order_ongoing_number'] ++;
                }
                if ($order->status <= 18) {
                    $res['sales_volume'] += $order->original_price * 100;
                    $res['order_receipts'] += $order->poi_receive * 100;
                    $res['product_cost'] += $order->vip_cost * 100;
                    if (isset($order->running->money)) {
                        if ($order->running->status === 70) {
                            $running_money = $order->running->money * 100;
                        }
                    }
                    $res['running_money'] += $running_money;
                    $res['prescription'] += $order->prescription_fee * 100;
                    $res['profit'] += $order->poi_receive* 100 - $running_money - $order->vip_cost* 100 - $order->prescription_fee* 100;
                }
            }
            if (($res['order_complete_number'] + $res['order_ongoing_number']) > 0) {
                $res['order_average'] = (float) sprintf("%.2f", $res['sales_volume'] / ($res['order_complete_number'] + $res['order_ongoing_number']) / 100);
            } else {
                $res['order_average'] = 0;
            }

        }
        if (!empty($orders2)) {
            foreach ($orders2 as $order) {
                $running_money = 0;
                $res2['order_total_number'] ++;
                if ($order->status == 18 && (strtotime($order->finish_at) < strtotime(date("Y-m-d H:i:s", time() - 86400)))) {
                    $res2['order_complete_number']++;
                } elseif ($order->status > 18 && (strtotime($order->cancel_at) < strtotime(date("Y-m-d H:i:s", time() - 86400)))) {
                    $res2['order_cancel_number'] ++;
                } else {
                    $res2['order_ongoing_number'] ++;
                }
                if ($order->status <= 18) {
                    $res2['sales_volume'] += $order->original_price * 100;
                    $res2['order_receipts'] += $order->poi_receive * 100;
                    $res2['product_cost'] += $order->vip_cost * 100;
                    if (isset($order->running->money)) {
                        if ($order->running->status === 70) {
                            $running_money = $order->running->money * 100;
                        }
                    }
                    $res2['running_money'] += $running_money;
                    $res2['prescription'] += $order->prescription_fee * 100;
                    $res2['profit'] += $order->poi_receive* 100 - $running_money - $order->vip_cost* 100 - $order->prescription_fee* 100;
                }
            }
            if (($res2['order_complete_number'] + $res2['order_ongoing_number']) > 0) {
                $res2['order_average'] = (float) sprintf("%.2f", $res2['sales_volume'] / ($res2['order_complete_number'] + $res2['order_ongoing_number']) / 100);
            } else {
                $res2['order_average'] = 0;
            }
            // $res2['sales_volume'] /= 100;
            // $res2['order_receipts'] /= 100;
            // $res2['product_cost'] /= 100;
            // $res2['running_money'] /= 100;
            // $res2['prescription'] /= 100;
            // $res2['profit'] /= 100;
        }


        // 销售总额
        $res['sales_volume_compare'] = $res['sales_volume'] - $res2['sales_volume'];
        // 订单实收
        $res['order_receipts_compare'] = $res['order_receipts'] - $res2['order_receipts'];
        // 订单总数
        $res['order_total_number_compare'] = $res['order_total_number'] - $res2['order_total_number'];
        // 完成订单
        $res['order_complete_number_compare'] = $res['order_complete_number'] - $res2['order_complete_number'];
        // 进行中订单
        $res['order_ongoing_number_compare'] = $res['order_ongoing_number'] - $res2['order_ongoing_number'];
        // 商品成本
        $res['product_cost_compare'] = $res['product_cost'] - $res2['product_cost'];
        // 取消订单
        $res['order_cancel_number_compare'] = $res['order_cancel_number'] - $res2['order_cancel_number'];
        // 单均价
        $res['order_average_compare'] = (float) sprintf("%.2f", $res['order_average'] - $res2['order_average']);
        // 跑腿支持
        $res['running_money_compare'] = $res['running_money'] - $res2['running_money'];
        // 处方审方费
        $res['prescription_compare'] = $res['prescription'] - $res2['prescription'];
        // 利润
        $res['profit_compare'] = $res['profit'] - $res2['profit'];

        $res['sales_volume'] /= 100;
        $res['order_receipts'] /= 100;
        $res['product_cost'] /= 100;
        $res['running_money'] /= 100;
        $res['prescription'] /= 100;
        $res['profit'] /= 100;
        $res['sales_volume_compare'] /= 100;
        $res['order_receipts_compare'] /= 100;
        $res['product_cost_compare'] /= 100;
        $res['running_money_compare'] /= 100;
        $res['prescription_compare'] /= 100;
        $res['profit_compare'] /= 100;

        // $res2['sales_volume'] /= 100;
        // $res2['order_receipts'] /= 100;
        // $res2['product_cost'] /= 100;
        // $res2['running_money'] /= 100;
        // $res2['prescription'] /= 100;
        // $res2['profit'] /= 100;

        // return $this->success(compact('res', 'res2'));
        return $this->success($res);
    }

    public function business_history(Request $request)
    {
        $platform = $request->get('platform', 0);
        $sdate = $request->get('sdate');
        if (!$sdate) {
            $sdate = date("Y-m-d", strtotime("-7 day"));
        }
        $edate = $request->get('edate');
        if (!$edate) {
            $edate = date("Y-m-d", strtotime("-1 day"));
        }
        if (strtotime($sdate) < date("Y-m-d",strtotime("-93 day"))) {
            return $this->error('只能查询3个月内的数据');
        }
        if ((strtotime($edate) - strtotime($sdate)) > 86400 *31) {
            return $this->error('查询范围不能超过31天');
        }
        $date_arr = [$sdate];
        $day = 0;
        while ((strtotime($sdate) + 86400 * $day) < strtotime($edate)) {
            $day++;
            array_push($date_arr, date("Y-m-d", strtotime($sdate) + 86400 * $day));
        }
        // return $date_arr;
        $query = WmAnalysis::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', $platform);

        if ($shop_id = $request->get('shop_id')) {
            if (!Shop::where('user_id', $request->user()->id)->where('id', $shop_id)->first()) {
                return $this->error('门店不存在');
            }
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }
        $data_arr = $query->get();
        if (!empty($data_arr)) {
            $data_date = [];
            foreach ($data_arr as $arr) {
                isset($data_date[$arr->date]['sales_volume']) || $data_date[$arr->date]['sales_volume'] = 0;
                isset($data_date[$arr->date]['order_receipts']) || $data_date[$arr->date]['order_receipts'] = 0;
                isset($data_date[$arr->date]['order_effective_number']) || $data_date[$arr->date]['order_effective_number'] = 0;
                isset($data_date[$arr->date]['order_cancel_number']) || $data_date[$arr->date]['order_cancel_number'] = 0;
                isset($data_date[$arr->date]['product_cost']) || $data_date[$arr->date]['product_cost'] = 0;
                isset($data_date[$arr->date]['running_money']) || $data_date[$arr->date]['running_money'] = 0;
                isset($data_date[$arr->date]['prescription']) || $data_date[$arr->date]['prescription'] = 0;
                isset($data_date[$arr->date]['profit']) || $data_date[$arr->date]['profit'] = 0;
                $data_date[$arr->date]['sales_volume'] += $arr->sales_volume * 100;
                $data_date[$arr->date]['order_receipts'] += $arr->order_receipts * 100;
                $data_date[$arr->date]['order_effective_number'] += $arr->order_effective_number;
                $data_date[$arr->date]['order_cancel_number'] += $arr->order_cancel_number;
                $data_date[$arr->date]['product_cost'] += $arr->product_cost * 100;
                $data_date[$arr->date]['running_money'] += $arr->running_money * 100;
                $data_date[$arr->date]['prescription'] += $arr->prescription * 100;
                $data_date[$arr->date]['profit'] += $arr->profit * 100;
            }
        }

        $res = [];
        foreach ($date_arr as $date) {
            $tmp['sales_volume'] = ($data_date[$date]['sales_volume'] ?? 0) / 100;
            $tmp['order_receipts'] = ($data_date[$date]['order_receipts'] ?? 0) / 100;
            $tmp['order_effective_number'] = ($data_date[$date]['order_effective_number'] ?? 0);
            $tmp['order_cancel_number'] = ($data_date[$date]['order_cancel_number'] ?? 0);
            $tmp['product_cost'] = ($data_date[$date]['product_cost'] ?? 0) / 100;
            $tmp['running_money'] = ($data_date[$date]['running_money'] ?? 0) / 100;
            $tmp['prescription'] = ($data_date[$date]['prescription'] ?? 0) / 100;
            $tmp['profit'] = ($data_date[$date]['profit'] ?? 0) / 100;
            $res[] = ['day' => date("n-d", strtotime($date)), 'data' => $tmp];
        }

        return $this->success($res);
    }

    public function shop(Request $request)
    {
        $sdate = $request->get('sdate');
        if (!$sdate) {
            $sdate = date("Y-m-d", strtotime("-1 day"));
        }
        $edate = $request->get('edate');
        if (!$edate) {
            $edate = date("Y-m-d", strtotime("-1 day"));
        }
        if (strtotime($sdate) < date("Y-m-d",strtotime("-93 day"))) {
            return $this->error('只能查询3个月内的数据');
        }
        if ((strtotime($edate) - strtotime($sdate)) > 86400 *31) {
            return $this->error('查询范围不能超过31天');
        }

        // $shops = $request->user()->shops();
        $shops = Shop::select('id', 'shop_name')->where('user_id', $request->user()->id)->get();
        // $shops = Shop::query()->whereIn('id', [6610,6467,6058,6308])->get();
        $shop_ids = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop_ids[] = $shop->id;
            }
        }

        $res = [];

        if (!empty($shop_ids)) {
            $data_arr = WmAnalysis::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', 0)
                ->whereIn('shop_id', $shop_ids)->get();
            if (!empty($data_arr)) {
                $data_shop_id = [];
                foreach ($data_arr as $datum) {
                    isset($data_shop_id[$datum->shop_id]['sales_volume']) || $data_shop_id[$datum->shop_id]['sales_volume'] = 0;
                    isset($data_shop_id[$datum->shop_id]['order_receipts']) || $data_shop_id[$datum->shop_id]['order_receipts'] = 0;
                    isset($data_shop_id[$datum->shop_id]['order_effective_number']) || $data_shop_id[$datum->shop_id]['order_effective_number'] = 0;
                    isset($data_shop_id[$datum->shop_id]['order_cancel_number']) || $data_shop_id[$datum->shop_id]['order_cancel_number'] = 0;
                    isset($data_shop_id[$datum->shop_id]['product_cost']) || $data_shop_id[$datum->shop_id]['product_cost'] = 0;
                    isset($data_shop_id[$datum->shop_id]['running_money']) || $data_shop_id[$datum->shop_id]['running_money'] = 0;
                    isset($data_shop_id[$datum->shop_id]['prescription']) || $data_shop_id[$datum->shop_id]['prescription'] = 0;
                    isset($data_shop_id[$datum->shop_id]['profit']) || $data_shop_id[$datum->shop_id]['profit'] = 0;
                    $data_shop_id[$datum->shop_id]['sales_volume'] += $datum->sales_volume * 100;
                    $data_shop_id[$datum->shop_id]['order_receipts'] += $datum->order_receipts * 100;
                    $data_shop_id[$datum->shop_id]['order_effective_number'] += $datum->order_effective_number;
                    $data_shop_id[$datum->shop_id]['order_cancel_number'] += $datum->order_cancel_number;
                    $data_shop_id[$datum->shop_id]['product_cost'] += $datum->product_cost * 100;
                    $data_shop_id[$datum->shop_id]['running_money'] += $datum->running_money * 100;
                    $data_shop_id[$datum->shop_id]['prescription'] += $datum->prescription * 100;
                    $data_shop_id[$datum->shop_id]['profit'] += $datum->profit * 100;
                }
                foreach ($shops as $shop) {
                    $tmp['shop_id'] = $shop->id;
                    $tmp['shop_name'] = $shop->shop_name;
                    $tmp['sales_volume'] = ($data_shop_id[$shop->id]['sales_volume'] ?? 0) / 100;
                    $tmp['order_receipts'] = ($data_shop_id[$shop->id]['order_receipts'] ?? 0) / 100;
                    $tmp['order_effective_number'] = ($data_shop_id[$shop->id]['order_effective_number'] ?? 0);
                    $tmp['order_cancel_number'] = ($data_shop_id[$shop->id]['order_cancel_number'] ?? 0);
                    $tmp['product_cost'] = ($data_shop_id[$shop->id]['product_cost'] ?? 0) / 100;
                    $tmp['running_money'] = ($data_shop_id[$shop->id]['running_money'] ?? 0) / 100;
                    $tmp['prescription'] = ($data_shop_id[$shop->id]['prescription'] ?? 0) / 100;
                    $tmp['profit'] = ($data_shop_id[$shop->id]['profit'] ?? 0) / 100;
                    $res[] = $tmp;
                }
            }
        }

        return $this->success($res);
    }

    public function platform(Request $request)
    {
        $sdate = $request->get('sdate');
        if (!$sdate) {
            $sdate = date("Y-m-d", strtotime("-1 day"));
        }
        $edate = $request->get('edate');
        if (!$edate) {
            $edate = date("Y-m-d", strtotime("-1 day"));
        }
        if (strtotime($sdate) < date("Y-m-d",strtotime("-93 day"))) {
            return $this->error('只能查询3个月内的数据');
        }
        if ((strtotime($edate) - strtotime($sdate)) > 86400 *31) {
            return $this->error('查询范围不能超过31天');
        }

        // $shops = $request->user()->shops();
        // $shops = Shop::query()->whereIn('id', [6610,6467,6058,6308])->get();
        $shops = Shop::select('id', 'shop_name')->where('user_id', $request->user()->id)->get();
        $shop_ids = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop_ids[] = $shop->id;
            }
        }
        $zong = WmAnalysis::select(
            DB::raw("sum(sales_volume) as sales_volume"),
            DB::raw("sum(order_receipts) as order_receipts"),
            DB::raw("sum(order_effective_number) as order_effective_number"),
            DB::raw("sum(order_cancel_number) as order_cancel_number"),
            DB::raw("sum(product_cost) as product_cost"),
            DB::raw("sum(running_money) as running_money"),
            DB::raw("sum(prescription) as prescription"),
            DB::raw("sum(profit) as profit")
        )->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', 0)->whereIn('shop_id', $shop_ids)->first()->toArray();
        foreach ($zong as $k => $v) {
            if (is_null($v)) {
                $zong[$k] = 0;
            } else {
                $zong[$k] = (float) $v;
            }
        }
        $zong['platform'] = '总计';
        $mt = WmAnalysis::select(
            DB::raw("sum(sales_volume) as sales_volume"),
            DB::raw("sum(order_receipts) as order_receipts"),
            DB::raw("sum(order_effective_number) as order_effective_number"),
            DB::raw("sum(order_cancel_number) as order_cancel_number"),
            DB::raw("sum(product_cost) as product_cost"),
            DB::raw("sum(running_money) as running_money"),
            DB::raw("sum(prescription) as prescription"),
            DB::raw("sum(profit) as profit")
        )->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', 1)->whereIn('shop_id', $shop_ids)->first()->toArray();
        foreach ($mt as $k => $v) {
            if (is_null($v)) {
                $mt[$k] = 0;
            } else {
                $mt[$k] = (float) $v;
            }
        }
        $mt['platform'] = '美团';
        $ele = WmAnalysis::select(
            DB::raw("sum(sales_volume) as sales_volume"),
            DB::raw("sum(order_receipts) as order_receipts"),
            DB::raw("sum(order_effective_number) as order_effective_number"),
            DB::raw("sum(order_cancel_number) as order_cancel_number"),
            DB::raw("sum(product_cost) as product_cost"),
            DB::raw("sum(running_money) as running_money"),
            DB::raw("sum(prescription) as prescription"),
            DB::raw("sum(profit) as profit")
        )->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', 2)->whereIn('shop_id', $shop_ids)->first()->toArray();
        foreach ($ele as $k => $v) {
            if (is_null($v)) {
                $ele[$k] = 0;
            } else {
                $ele[$k] = (float) $v;
            }
        }
        $ele['platform'] = '饿了么';

        $res = [
            $zong,
            $mt,
            $ele,
        ];
        return $this->success($res);
    }

    public function running(Request $request)
    {
        $sdate = $request->get('sdate');
        if (!$sdate) {
            $sdate = date("Y-m-d", strtotime("-7 day"));
        }
        $edate = $request->get('edate');
        if (!$edate) {
            $edate = date("Y-m-d", strtotime("-1 day"));
        }
        if (strtotime($sdate) < date("Y-m-d",strtotime("-93 day"))) {
            return $this->error('只能查询3个月内的数据');
        }
        if ((strtotime($edate) - strtotime($sdate)) > 86400 *31) {
            return $this->error('查询范围不能超过31天');
        }

        // $shops = $request->user()->shops();
        // $shops = Shop::query()->whereIn('id', [6121,2597,2583,2241])->get();
        $shops = Shop::select('id', 'shop_name')->where('user_id', $request->user()->id)->get();
        $shop_ids = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop_ids[] = $shop->id;
            }
        }
        $orders = Order::select('id', 'ps', 'money', 'receive_at', 'over_at')
            ->where('status', 70)
            ->where('created_at', '>=', $sdate)->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400))
            ->whereIn('shop_id', $shop_ids)->get();
        $res = [];
        $ps_map = ['', '美团', '蜂鸟', '闪送', '美全达', '达达', 'UU', '顺丰', '美团众包'];
        if (!empty($orders)) {
            $order_ps = [];
            foreach ($orders as $order) {
                isset($order_ps[$order->ps]['order_total']) || $order_ps[$order->ps]['order_total'] = 0;
                isset($order_ps[$order->ps]['second']) || $order_ps[$order->ps]['second'] = 0;
                isset($order_ps[$order->ps]['money']) || $order_ps[$order->ps]['money'] = 0;
                isset($order_ps[$order->ps]['tip']) || $order_ps[$order->ps]['tip'] = 0;

                $order_ps[$order->ps]['order_total']++;
                $order_ps[$order->ps]['second'] += strtotime($order->over_at) - strtotime($order->receive_at);
                $order_ps[$order->ps]['money'] += $order->money * 100;
            }
            foreach ($order_ps as $ps => $order) {
                $tmp['ps'] = $ps_map[$ps];
                $tmp['ps_type'] = $ps;
                $tmp['order_total'] = $order['order_total'];
                $tmp['unit_price'] = (float) sprintf("%.2f", $order['money'] / $order['order_total'] / 100);
                $tmp['money'] = $order['money'] / 100;
                $tmp['total_money'] = ($order['money'] + $order['tip']) / 100;
                $avg_time = gmdate("H:i:s", intval($order['second'] / $order['order_total']));
                $avg_time = str_replace('00:', '', $avg_time);
                $tmp['avg_time'] = $avg_time;
                $tmp['tip'] = $order['tip'] / 100;
                $res[] = $tmp;
            }
        }
        return $this->success($res);
    }
}
