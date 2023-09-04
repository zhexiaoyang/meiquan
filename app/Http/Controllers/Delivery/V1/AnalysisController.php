<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Models\WmAnalysis;
use App\Models\WmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    /**
     * 营业概况
     * @data 2023/8/11 7:43 下午
     */
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
            'operate_service' => 0,
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
            'operate_service_compare' => 0,
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
            'operate_service' => 0,
        ];

        $query = WmOrder::with(['running' => function($query) {
            $query->select('id', 'wm_id', 'status', 'money');
        }])->select('id', 'poi_receive', 'original_price', 'prescription_fee', 'vip_cost', 'status', 'finish_at', 'cancel_at','operate_service_fee');

        $user = $request->user();
        $shop_id = (int) $request->get('shop_id', 0);
        if ($shop_id) {
            if ($user->hasRole('city_manager')) {
                if (!in_array($shop_id, $user->shops()->pluck('id')->toArray())) {
                    return $this->error('门店不存在');
                }
            } else {
                if (!Shop::where('user_id', $user->id)->where('id', $shop_id)->first()) {
                    return $this->error('门店不存在');
                }
            }
            $query->where('shop_id', $shop_id);
        } else {
            if ($user->hasRole('city_manager')) {
                $query->whereIn('shop_id', $user->shops()->pluck('id'));
            } else {
                $query->whereIn('shop_id', Shop::where('user_id', $user->id)->get()->pluck('id'));
            }
        }

        $query2 = clone($query);
        $orders = $query->where('created_at', '>=', date("Y-m-d"))->get();
        $orders2 = $query2->where('created_at', '>=', date("Y-m-d", time() - 86400))
            ->where('created_at','<=', date("Y-m-d H:i:s", time() - 86400))->get();
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
                    $res['operate_service'] += $order->operate_service_fee * 100;
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
                    $res2['operate_service'] += $order->operate_service_fee * 100;
                    $res2['profit'] += $order->poi_receive* 100 - $running_money - $order->vip_cost* 100 - $order->prescription_fee* 100;
                }
            }
            if (($res2['order_complete_number'] + $res2['order_ongoing_number']) > 0) {
                $res2['order_average'] = (float) sprintf("%.2f", $res2['sales_volume'] / ($res2['order_complete_number'] + $res2['order_ongoing_number']) / 100);
            } else {
                $res2['order_average'] = 0;
            }
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
        $res['operate_service_compare'] = $res['operate_service'] - $res2['operate_service'];

        $res['sales_volume'] = (float) sprintf("%.2f", $res['sales_volume'] / 100);
        $res['order_receipts'] = (float) sprintf("%.2f", $res['order_receipts'] / 100);
        $res['product_cost'] = (float) sprintf("%.2f", $res['product_cost'] / 100);
        $res['running_money'] = (float) sprintf("%.2f", $res['running_money'] / 100);
        $res['prescription'] = (float) sprintf("%.2f", $res['prescription'] / 100);
        $res['profit'] = (float) sprintf("%.2f", $res['profit'] / 100);
        $res['operate_service'] = (float) sprintf("%.2f", $res['operate_service'] / 100);

        $res['sales_volume_compare'] = (float) sprintf("%.2f", $res['sales_volume_compare'] / 100);
        $res['order_receipts_compare'] = (float) sprintf("%.2f", $res['order_receipts_compare'] / 100);
        $res['product_cost_compare'] = (float) sprintf("%.2f", $res['product_cost_compare'] / 100);
        $res['running_money_compare'] = (float) sprintf("%.2f", $res['running_money_compare'] / 100);
        $res['prescription_compare'] = (float) sprintf("%.2f", $res['prescription_compare'] / 100);
        $res['profit_compare'] = (float) sprintf("%.2f", $res['profit_compare'] / 100);
        $res['operate_service_compare'] = (float) sprintf("%.2f", $res['operate_service_compare'] / 100);

        return $this->success($res);
    }

    /**
     * 历史营业数据
     * @data 2023/8/11 8:33 下午
     */
    public function history(Request $request)
    {
        $sdate = $request->get('sdate');
        if (!$sdate) {
            $sdate = date("Y-m-d", strtotime("-30 day"));
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
        $query = WmAnalysis::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('platform', 0);

        $user = $request->user();
        if ($shop_id = (int) $request->get('shop_id')) {
            if ($user->hasRole('city_manager')) {
                if (!in_array($shop_id, $user->shops()->pluck('id')->toArray())) {
                    return $this->error('门店不存在');
                }
            } else {
                if (!Shop::where('user_id', $user->id)->where('id', $shop_id)->first()) {
                    return $this->error('门店不存在');
                }
            }
            $query->where('shop_id', $shop_id);
        } else {
            if ($user->hasRole('city_manager')) {
                $query->whereIn('shop_id', $user->shops()->pluck('id'));
            } else {
                $query->whereIn('shop_id', Shop::where('user_id', $user->id)->get()->pluck('id'));
            }
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
            $tmp['sales_volume'] = (float) sprintf("%.2f", ($data_date[$date]['sales_volume'] ?? 0) / 100);
            $tmp['order_receipts'] = (float) sprintf("%.2f", ($data_date[$date]['order_receipts'] ?? 0) / 100);
            $tmp['order_effective_number'] = ($data_date[$date]['order_effective_number'] ?? 0);
            $tmp['order_cancel_number'] = ($data_date[$date]['order_cancel_number'] ?? 0);
            $tmp['product_cost'] = (float) sprintf("%.2f", ($data_date[$date]['product_cost'] ?? 0) / 100);
            $tmp['running_money'] = (float) sprintf("%.2f", ($data_date[$date]['running_money'] ?? 0) / 100);
            $tmp['prescription'] = (float) sprintf("%.2f", ($data_date[$date]['prescription'] ?? 0) / 100);
            $tmp['profit'] = (float) sprintf("%.2f", ($data_date[$date]['profit'] ?? 0) / 100);
            $res[] = ['day' => date("n-d", strtotime($date)), 'data' => $tmp];
        }

        return $this->success($res);
    }

    /**
     * 门店统计
     * @data 2023/8/13 3:09 下午
     */
    public function shop(Request $request)
    {
        $shop_id = (int) $request->get('shop_id', 0);
        // 10 今日，20 昨日，30 近七天，40 本月，80 自定义
        $date_type = (int) $request->get('date_type', 0);
        if (!in_array($date_type, [10, 20, 30, 40, 80])) {
            return $this->error('日期类型不正确');
        }
        $date_range = $request->get('date_range', '');
        // 日期搜索判断
        $start_date = '';
        $end_date = '';
        if ($date_type === 20) {
            $start_date = date('Y-m-d', strtotime('-1 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        } elseif ($date_type === 30) {
            $start_date = date('Y-m-d', strtotime('-7 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        } elseif ($date_type === 40) {
            $start_date = date("Y-m-01");
            $end_date = date("Y-m-t");
        } elseif ($date_type === 80) {
            if (!$date_range) {
                return $this->error('日期范围不能为空');
            }
            $date_arr = explode(',', $date_range);
            if (count($date_arr) !== 2) {
                return $this->error('日期格式不正确');
            }
            $start_date = $date_arr[0];
            $end_date = $date_arr[1];
            if ($start_date !== date("Y-m-d", strtotime($start_date))) {
                return $this->error('日期格式不正确');
            }
            if ($end_date !== date("Y-m-d", strtotime($end_date))) {
                return $this->error('日期格式不正确');
            }
            if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 31) {
                return $this->error('时间范围不能超过31天');
            }
        }
        // $user_shop_id_map = $user_shops->pluck('id')->toArray();
        // 门店判断
        if ($shop_id) {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->where('user_id', $request->user()->id)->get();
            // $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->get();
        } else {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('user_id', $request->user()->id)->get();
        }
        if (empty($user_shops)) {
            return $this->success();
        }
        $user_shop_ids = $user_shops->pluck('id')->toArray();
        if ($date_type === 10) {
            // 今日数据
            $query = WmOrder::select('id', 'shop_id', 'poi_receive', 'original_price')->where('status', '<=', 18)->where('created_at','>',date('Y-m-d'));
            if ($shop_id) {
                $query->where('shop_id', $shop_id);
            } else {
                $query->whereIn('shop_id', $user_shop_ids);
            }
            $orders = $query->get();
            $result = [];
            $result_tmp = [];
            if (!empty($orders)) {
                foreach ($orders as $order) {
                    if (isset($result_tmp[$order->shop_id])) {
                        $result_tmp[$order->shop_id]['poi_receive'] += $order->poi_receive;
                        $result_tmp[$order->shop_id]['original_price'] += $order->original_price;
                        $result_tmp[$order->shop_id]['order_number']++;
                    } else {
                        $result_tmp[$order->shop_id]['poi_receive'] = $order->poi_receive;
                        $result_tmp[$order->shop_id]['original_price'] = $order->original_price;
                        $result_tmp[$order->shop_id]['order_number'] = 1;
                    }
                }
                foreach ($user_shops as $v) {
                    if (isset($result_tmp[$v->id])) {
                        $tmp = $result_tmp[$v->id];
                        $result[] = [
                            'shop_id' => $v->id,
                            'shop_name' => $v->wm_shop_name ?: $v->shop_name,
                            'poi_receive' => (float) sprintf("%.2f", $tmp['poi_receive']),
                            'original_price' => (float) sprintf("%.2f", $tmp['original_price']),
                            'order_number' => (float) sprintf("%.2f", $tmp['order_number']),
                            'unit_price' => (float) sprintf("%.2f", $tmp['poi_receive'] / $tmp['order_number'])
                        ];
                    } else {
                        $result[] = [
                            'shop_name' => $v->wm_shop_name ?: $v->shop_name,
                            'poi_receive' => 0,
                            'original_price' => 0,
                            'order_number' => 0,
                            'unit_price' => 0
                        ];
                    }
                }
            }
        } else {
            $result = [];
            $data_arr = WmAnalysis::where('date', '>=', $start_date)->where('date', '<=', $end_date)->where('platform', 0)
                ->whereIn('shop_id', $user_shop_ids)->get();
            if (!empty($data_arr)) {
                $data_shop_id = [];
                foreach ($data_arr as $datum) {
                    isset($data_shop_id[$datum->shop_id]['sales_volume']) || $data_shop_id[$datum->shop_id]['sales_volume'] = 0;
                    isset($data_shop_id[$datum->shop_id]['order_receipts']) || $data_shop_id[$datum->shop_id]['order_receipts'] = 0;
                    isset($data_shop_id[$datum->shop_id]['order_effective_number']) || $data_shop_id[$datum->shop_id]['order_effective_number'] = 0;
                    // isset($data_shop_id[$datum->shop_id]['order_cancel_number']) || $data_shop_id[$datum->shop_id]['order_cancel_number'] = 0;
                    isset($data_shop_id[$datum->shop_id]['product_cost']) || $data_shop_id[$datum->shop_id]['product_cost'] = 0;
                    // isset($data_shop_id[$datum->shop_id]['running_money']) || $data_shop_id[$datum->shop_id]['running_money'] = 0;
                    // isset($data_shop_id[$datum->shop_id]['prescription']) || $data_shop_id[$datum->shop_id]['prescription'] = 0;
                    isset($data_shop_id[$datum->shop_id]['profit']) || $data_shop_id[$datum->shop_id]['profit'] = 0;
                    // isset($data_shop_id[$datum->shop_id]['operate_service']) || $data_shop_id[$datum->shop_id]['operate_service'] = 0;
                    $data_shop_id[$datum->shop_id]['sales_volume'] += $datum->sales_volume * 100;
                    $data_shop_id[$datum->shop_id]['order_receipts'] += $datum->order_receipts * 100;
                    $data_shop_id[$datum->shop_id]['order_effective_number'] += $datum->order_effective_number;
                    // $data_shop_id[$datum->shop_id]['order_cancel_number'] += $datum->order_cancel_number;
                    $data_shop_id[$datum->shop_id]['product_cost'] += $datum->product_cost * 100;
                    // $data_shop_id[$datum->shop_id]['running_money'] += $datum->running_money * 100;
                    // $data_shop_id[$datum->shop_id]['prescription'] += $datum->prescription * 100;
                    $data_shop_id[$datum->shop_id]['profit'] += $datum->profit * 100;
                    // $data_shop_id[$datum->shop_id]['operate_service'] += $datum->operate_service * 100;
                }
                foreach ($user_shops as $shop) {
                    $tmp['shop_id'] = $shop->id;
                    $tmp['shop_name'] = $shop->wm_shop_name ?: $shop->shop_name;
                    $tmp['original_price'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['sales_volume'] ?? 0) / 100);
                    $tmp['poi_receive'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['order_receipts'] ?? 0) / 100);
                    $tmp['order_number'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['order_effective_number'] ?? 0));
                    // $tmp['order_cancel_number'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['order_cancel_number'] ?? 0));
                    $tmp['product_cost'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['product_cost'] ?? 0) / 100);
                    // $tmp['running_money'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['running_money'] ?? 0) / 100);
                    // $tmp['prescription'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['prescription'] ?? 0) / 100);
                    $tmp['profit'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['profit'] ?? 0) / 100);
                    // $tmp['operate_service'] = (float) sprintf("%.2f", ($data_shop_id[$shop->id]['operate_service'] ?? 0) / 100);
                    $unit_price = 0;
                    if ($tmp['order_number'] > 0) {
                        $unit_price =  (float) sprintf("%.2f", $tmp['poi_receive'] / $tmp['order_number']);
                    }
                    $tmp['unit_price'] = $unit_price;
                    $result[] = $tmp;
                }
            }
        }
        return $this->success($result);
    }

    /**
     * 配送
     * @data 2023/8/13 4:51 下午
     */
    public function delivery(Request $request)
    {

        $shop_id = (int) $request->get('shop_id', 0);
        // 10 今日，20 昨日，30 近七天，40 本月，80 自定义
        $date_type = (int) $request->get('date_type', 0);
        if (!in_array($date_type, [10, 20, 30, 40, 80])) {
            return $this->error('日期类型不正确');
        }
        $date_range = $request->get('date_range', '');
        // 日期搜索判断
        $start_date = '';
        $end_date = '';
        if ($date_type === 10) {
            $start_date = date("Y-m-d");
            $end_date = date("Y-m-d");
        } elseif ($date_type === 20) {
            $start_date = date('Y-m-d', strtotime('-1 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        } elseif ($date_type === 30) {
            $start_date = date('Y-m-d', strtotime('-7 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        // } elseif ($date_type === 40) {
        //     $start_date = date("Y-m-01");
        //     $end_date = date("Y-m-d", strtotime("$start_date +1 month -1 day"));
        } elseif ($date_type === 40) {
            $start_date = date("Y-m-01");
            $end_date = date("Y-m-t");
        } elseif ($date_type === 80) {
            if (!$date_range) {
                return $this->error('日期范围不能为空');
            }
            $date_arr = explode(',', $date_range);
            if (count($date_arr) !== 2) {
                return $this->error('日期格式不正确');
            }
            $start_date = $date_arr[0];
            $end_date = $date_arr[1];
            if ($start_date !== date("Y-m-d", strtotime($start_date))) {
                return $this->error('日期格式不正确');
            }
            if ($end_date !== date("Y-m-d", strtotime($end_date))) {
                return $this->error('日期格式不正确');
            }
            if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 31) {
                return $this->error('时间范围不能超过31天');
            }
        }
        // $user_shop_id_map = $user_shops->pluck('id')->toArray();
        // 门店判断
        if ($shop_id) {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->where('user_id', $request->user()->id)->get();
            // $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->get();
        } else {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('user_id', $request->user()->id)->get();
        }
        if (empty($user_shops)) {
            return $this->success();
        }
        $user_shop_ids = $user_shops->pluck('id')->toArray();
        // 查询数据
        $query = Order::select('ps', DB::raw("sum(money) as total_money"), DB::raw("count(1) as deliver_count"))
            ->where('status', '=', 70)->where('ps', '>', 0);
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $user_shop_ids);
        }
        $query->where('created_at', '>', $start_date)->where('created_at', '<', date("Y-m-d", strtotime($end_date) + 86400));
        $orders = $query->groupBY('ps')->get();
        $result = [
            'total_money' => 0,
            'deliver_count' => 0,
            'unit_price' => 0,
            'tip' => 0,
            'deliveries' => []
        ];
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $result['total_money'] += $order->total_money;
                $result['deliver_count'] += $order->deliver_count;
                $tmp = [
                    'platform' => $order->ps,
                    'platform_text' => config('ps.delivery_map')[$order->ps],
                    'total_money' => $order->total_money,
                    'deliver_count' => $order->deliver_count,
                    'unit_price' => (float) sprintf("%.2f", $order->total_money / $order->deliver_count),
                    'tip' => 0,
                ];
                $result['deliveries'][] = $tmp;
            }
        }
        if ($result['deliver_count'] > 0) {
            $result['unit_price'] = (float) sprintf("%.2f", $result['total_money'] / $result['deliver_count']);
        }
        if (!empty($result['deliveries'])) {
            foreach ($result['deliveries'] as $key => $delivery) {
                $result['deliveries'][$key]['proportion'] = ceil(($delivery['deliver_count'] / $result['deliver_count'] * 100));
            }
        }
        $result['total_money'] = (float) sprintf("%.2f", $result['total_money']);
        return $this->success($result);
    }

    /**
     * 渠道
     * @data 2023/8/13 9:06 下午
     */
    public function channel(Request $request)
    {
        $shop_id = (int) $request->get('shop_id', 0);
        // 20 昨日，30 近七天，40 本月，80 自定义
        $date_type = (int) $request->get('date_type', 0);
        if (!in_array($date_type, [20, 30, 40, 80])) {
            return $this->error('日期类型不正确');
        }
        $date_range = $request->get('date_range', '');
        // 日期搜索判断
        $start_date = '';
        $end_date = '';
        if ($date_type === 20) {
            $start_date = date('Y-m-d', strtotime('-1 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        } elseif ($date_type === 30) {
            $start_date = date('Y-m-d', strtotime('-7 day'));
            $end_date = date('Y-m-d', strtotime('-1 day'));
        // } elseif ($date_type === 40) {
        //     $start_date = date("Y-m-01");
        //     $end_date = date("Y-m-d", strtotime("$start_date +1 month -1 day"));
        } elseif ($date_type === 40) {
            $start_date = date("Y-m-01");
            $end_date = date("Y-m-t");
        } elseif ($date_type === 80) {
            if (!$date_range) {
                return $this->error('日期范围不能为空');
            }
            $date_arr = explode(',', $date_range);
            if (count($date_arr) !== 2) {
                return $this->error('日期格式不正确');
            }
            $start_date = $date_arr[0];
            $end_date = $date_arr[1];
            if ($start_date !== date("Y-m-d", strtotime($start_date))) {
                return $this->error('日期格式不正确');
            }
            if ($end_date !== date("Y-m-d", strtotime($end_date))) {
                return $this->error('日期格式不正确');
            }
            if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 31) {
                return $this->error('时间范围不能超过31天');
            }
        }
        // 门店判断
        if ($shop_id) {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->where('user_id', $request->user()->id)->get();
            // $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('id', $shop_id)->get();
        } else {
            $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('user_id', $request->user()->id)->get();
            // $user_shops = Shop::select('id', 'shop_name', 'wm_shop_name')->whereIn('id', [6446,5359,6367])->get();
        }
        if (empty($user_shops)) {
            return $this->success();
        }
        $shop_name_map = [];
        foreach ($user_shops as $user_shop) {
            $shop_name_map[$user_shop->id] = $user_shop->wm_shop_name ?: $user_shop->shop_name;
        }
        $user_shop_ids = $user_shops->pluck('id')->toArray();
        // 查询数据
        $query = WmAnalysis::where('platform', '>', 0);
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $user_shop_ids);
        }
        $query->where('date', '>=', $start_date)->where('date', '<=', $end_date);
        $data = $query->get();
        $result = [];
        $total_order_number = 0;
        if (!empty($data)) {
            foreach ($data as $v) {
                $total_order_number += $v->order_effective_number;
                if (isset($result[$v->platform])) {
                    $result[$v->platform]['order_receipts'] += $v->order_receipts;
                    $result[$v->platform]['order_effective_number'] += $v->order_effective_number;
                    if (isset($result[$v->platform]['shops'][$v->shop_id])) {
                        $result[$v->platform]['shops'][$v->shop_id]['order_receipts'] = (float) sprintf("%.2f", $v->order_receipts + $result[$v->platform]['shops'][$v->shop_id]['order_receipts']);
                        $result[$v->platform]['shops'][$v->shop_id]['order_effective_number'] = (float) sprintf("%.2f", $v->order_effective_number + $result[$v->platform]['shops'][$v->shop_id]['order_effective_number']);
                        $result[$v->platform]['shops'][$v->shop_id]['sales_volume'] = (float) sprintf("%.2f", $v->sales_volume + $result[$v->platform]['shops'][$v->shop_id]['sales_volume']);
                        $result[$v->platform]['shops'][$v->shop_id]['service_fee'] = (float) sprintf("%.2f", $v->service_fee + $result[$v->platform]['shops'][$v->shop_id]['service_fee']);
                    } else {
                        $result[$v->platform]['shops'][$v->shop_id] = [
                            'shop_id' => $v->shop_id,
                            'shop_name' => $shop_name_map[$v->shop_id],
                            'order_receipts' => (float) $v->order_receipts,
                            'order_effective_number' => $v->order_effective_number,
                            'sales_volume' => (float) $v->sales_volume,
                            'service_fee' => (float) $v->service_fee,
                        ];
                    }
                } else {
                    $result[$v->platform] = [
                        'platform' => $v->platform,
                        'platform_text' => config('ps.takeout_map')[$v->platform],
                        'order_receipts' => $v->order_receipts,
                        'order_effective_number' => $v->order_effective_number,
                        'unit_price' => 0,
                        'proportion' => 0,
                        'shops' => [
                            [
                                'shop_id' => $v->shop_id,
                                'shop_name' => $shop_name_map[$v->shop_id],
                                'order_receipts' => (float) $v->order_receipts,
                                'order_effective_number' => $v->order_effective_number,
                                'sales_volume' => (float) $v->sales_volume,
                                'service_fee' => (float) $v->service_fee,
                            ]
                        ],
                    ];
                }
            }
        }
        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $result[$k]['proportion'] = ceil($v['order_effective_number'] / $total_order_number * 100);
                $result[$k]['order_receipts'] = (float) sprintf("%.2f", $v['order_receipts']);
                $result[$k]['unit_price'] = (float) sprintf("%.2f", $v['order_receipts'] / $v['order_effective_number']);
                if (!empty($v['shops'])) {
                    $result[$k]['shops'] = array_values($v['shops']);
                }
            }
        }
        return $this->success(array_values($result));
    }
}
