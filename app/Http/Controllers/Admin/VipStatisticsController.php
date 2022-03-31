<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class VipStatisticsController extends Controller
{
    public function orderStatistics(Request $request)
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
        $order_cancel_query = WmOrder::where('is_vip', 1);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
            $order_cancel_query->where('cancel_at', '>=', $sdate)->where('cancel_at', '<', date("Y-m-d", strtotime($edate) + 86400));
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
                    $order_at = date('Y-m-d', strtotime($order->finish_at));
                    $order_total++;
                    $order_sale += ($order->poi_receive - $order->refund_fee);
                    $order_profit += $order->vip_total;
                    $data[$order_at]['有效订单']++;
                    $data[$order_at]['销售额'] += ($order->poi_receive - $order->refund_fee);
                    $data[$order_at]['总利润'] += $order->vip_total;
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
                'VIP门店' => 0,
            ];
            $sdate = date("Y-m-d", strtotime($sdate) + 86400);
        }

        return $data;
    }

    public function shopStatistics(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');
        $shop_id = $request->get('shop_id');
        $city = $request->get('city');

        $shop_query = Shop::with(['manager','operate','internal'])->where('vip_status', 1);
        if ($shop_id) {
            $shop_query->where('id', $shop_id);
        }
        if ($city) {
            $shop_query->where('city', $city);
        }
        $shop_ids = [];
        $res_data = [];
        $shops = $shop_query->paginate($request->get('page_size', 10));
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $shop_ids[] = $shop->id;
                $res_data[$shop->id] = [
                    'id' => $shop->id,
                    'shop_name' => $shop->shop_name,
                    'city' => $shop->city,
                    'manager' => $shop->manager->nickname ?? '',
                    'operate' => $shop->operate->nickname ?? '',
                    'internal' => $shop->internal->nickname ?? '',
                    'order' => 0,
                    'sale' => 0,
                    'profit' => 0,
                    'shop_profit' => 0,
                    'company_profit' => 0,
                    'manager_profit' => 0,
                    'operate_profit' => 0,
                    'internal_profit' => 0,
                ];
            }
        }
        $order_query = WmOrder::where('is_vip', 1)->whereIn('shop_id', $shop_ids);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $res_data[$order->shop_id]['order']++;
                $res_data[$order->shop_id]['sale'] += ($order->poi_receive - $order->refund_fee);
                $res_data[$order->shop_id]['profit'] += $order->vip_total;
                $res_data[$order->shop_id]['shop_profit'] += $order->vip_business;
                $res_data[$order->shop_id]['company_profit'] += $order->vip_company;
                $res_data[$order->shop_id]['manager_profit'] += $order->vip_city;
                $res_data[$order->shop_id]['operate_profit'] += $order->vip_operate;
                $res_data[$order->shop_id]['internal_profit'] += $order->vip_internal;
            }
        }

        if (!empty($res_data)) {
            foreach ($res_data as $k => $v) {
                $res_data[$k]['sale'] = (float) sprintf("%.2f", $v['sale']);
                $res_data[$k]['profit'] = (float) sprintf("%.2f", $v['profit']);
                $res_data[$k]['shop_profit'] = (float) sprintf("%.2f", $v['shop_profit']);
                $res_data[$k]['company_profit'] = (float) sprintf("%.2f", $v['company_profit']);
                $res_data[$k]['manager_profit'] = (float) sprintf("%.2f", $v['manager_profit']);
                $res_data[$k]['operate_profit'] = (float) sprintf("%.2f", $v['operate_profit']);
                $res_data[$k]['internal_profit'] = (float) sprintf("%.2f", $v['internal_profit']);
            }
        }

        return $this->success(array_values($res_data));
    }

    public function managerStatistics(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');

        $managers = User::select('id', 'nickname', 'name', 'phone')->whereHas('roles', function ($query)  {
            $query->where('name', 'city_manager');
        })->where('status', 1)->where('id', '>', 2000)->get();

        $manager_ids = [];
        $shop_ids = [];
        $mt_shop_ids = [];
        $mt_shop_id_keys = [];
        $res_data = [];
        if (!empty($managers)) {
            foreach ($managers as $manager) {
                $manager_ids[] = $manager->id;
                $res_data[$manager->id] = [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'nickname' => $manager->nickname,
                    'phone' => $manager->phone,
                    'shop' => 0,
                    'shop_online' => 0,
                    'shop_open' => 0,
                    'order' => 0,
                    'sale' => 0,
                    'profit' => 0,
                    'shop_profit' => 0,
                    'manager_profit' => 0,
                ];
            }
        }
        $order_query = WmOrder::with('shop')->where('is_vip', 1);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (isset($res_data[$order->shop->manager_id])) {
                    $res_data[$order->shop->manager_id]['order']++;
                    $res_data[$order->shop->manager_id]['sale'] += ($order->poi_receive - $order->refund_fee);
                    $res_data[$order->shop->manager_id]['profit'] += $order->vip_total;
                    $res_data[$order->shop->manager_id]['shop_profit'] += $order->vip_business;
                    $res_data[$order->shop->manager_id]['manager_profit'] += $order->vip_city;
                    if (!in_array($order->shop_id, $shop_ids)) {
                        $shop_ids[] = $order->shop_id;
                    }
                }
            }
        }

        $shops = Shop::where('vip_status', 1)->whereIn('manager_id', $manager_ids)->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $res_data[$shop->manager_id]['shop']++;
                $mt_shop_ids[] = $shop->mtwm;
                $mt_shop_id_keys[$shop->mtwm] = $shop->manager_id;
            }
        }

        if (!empty($mt_shop_ids)) {
            $mk = app('minkang');
            $tmp = array_chunk($mt_shop_ids, 200);
            foreach ($tmp as $item) {
                $res = $mk->getShopInfoByIds(['app_poi_codes' => implode(",", $item)]);
                \Log::info('aaa', [$res]);
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $re) {
                        $mt_id = $re['app_poi_code'];
                        \Log::info("$mt_id,{$re['is_online']},{$re['open_level']}");
                        if ($re['is_online'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_online']++;
                        }
                        if ($re['open_level'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_open']++;
                        }
                    }
                }
            }
        }

        if (!empty($res_data)) {
            foreach ($res_data as $k => $v) {
                $res_data[$k]['sale'] = (float) sprintf("%.2f", $v['sale']);
                $res_data[$k]['profit'] = (float) sprintf("%.2f", $v['profit']);
                $res_data[$k]['shop_profit'] = (float) sprintf("%.2f", $v['shop_profit']);
                $res_data[$k]['manager_profit'] = (float) sprintf("%.2f", $v['manager_profit']);
            }
        }

        return $this->success(array_values($res_data));
    }

    public function operateStatistics(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');

        $operates = User::select('id', 'nickname', 'name', 'phone')->where('is_operate', 1)->where('status', 1)->get();

        $operate_ids = [];
        $shop_ids = [];
        $mt_shop_ids = [];
        $mt_shop_id_keys = [];
        $res_data = [];
        if (!empty($operates)) {
            foreach ($operates as $manager) {
                $operate_ids[] = $manager->id;
                $res_data[$manager->id] = [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'nickname' => $manager->nickname,
                    'phone' => $manager->phone,
                    'shop' => 0,
                    'shop_online' => 0,
                    'shop_open' => 0,
                    'order' => 0,
                    'sale' => 0,
                    'profit' => 0,
                    'shop_profit' => 0,
                    'operate_profit' => 0,
                ];
            }
        }
        $order_query = WmOrder::with('shop')->where('is_vip', 1);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (isset($res_data[$order->shop->operate_id])) {
                    $res_data[$order->shop->operate_id]['order']++;
                    $res_data[$order->shop->operate_id]['sale'] += ($order->poi_receive - $order->refund_fee);
                    $res_data[$order->shop->operate_id]['profit'] += $order->vip_total;
                    $res_data[$order->shop->operate_id]['shop_profit'] += $order->vip_business;
                    $res_data[$order->shop->operate_id]['operate_profit'] += $order->vip_operate;
                    if (!in_array($order->shop_id, $shop_ids)) {
                        $shop_ids[] = $order->shop_id;
                    }
                }
            }
        }

        $shops = Shop::where('vip_status', 1)->whereIn('operate_id', $operate_ids)->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $res_data[$shop->operate_id]['shop']++;
                $mt_shop_ids[] = $shop->mtwm;
                $mt_shop_id_keys[$shop->mtwm] = $shop->operate_id;
            }
        }

        if (!empty($mt_shop_ids)) {
            $mk = app('minkang');
            $tmp = array_chunk($mt_shop_ids, 200);
            foreach ($tmp as $item) {
                $res = $mk->getShopInfoByIds(['app_poi_codes' => implode(",", $item)]);
                \Log::info('aaa', [$res]);
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $re) {
                        $mt_id = $re['app_poi_code'];
                        \Log::info("$mt_id,{$re['is_online']},{$re['open_level']}");
                        if ($re['is_online'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_online']++;
                        }
                        if ($re['open_level'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_open']++;
                        }
                    }
                }
            }
        }

        if (!empty($res_data)) {
            foreach ($res_data as $k => $v) {
                $res_data[$k]['sale'] = (float) sprintf("%.2f", $v['sale']);
                $res_data[$k]['profit'] = (float) sprintf("%.2f", $v['profit']);
                $res_data[$k]['shop_profit'] = (float) sprintf("%.2f", $v['shop_profit']);
                $res_data[$k]['operate_profit'] = (float) sprintf("%.2f", $v['operate_profit']);
            }
        }

        return $this->success(array_values($res_data));
    }

    public function internalStatistics(Request $request)
    {
        $sdate = $request->get('sdate');
        $edate = $request->get('edate');

        $internal = User::select('id', 'nickname', 'name', 'phone')->where('is_internal', 1)->where('status', 1)->get();

        $internal_ids = [];
        $shop_ids = [];
        $mt_shop_ids = [];
        $mt_shop_id_keys = [];
        $res_data = [];
        if (!empty($internal)) {
            foreach ($internal as $manager) {
                $internal_ids[] = $manager->id;
                $res_data[$manager->id] = [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'nickname' => $manager->nickname,
                    'phone' => $manager->phone,
                    'shop' => 0,
                    'shop_online' => 0,
                    'shop_open' => 0,
                    'order' => 0,
                    'sale' => 0,
                    'profit' => 0,
                    'shop_profit' => 0,
                    'internal_profit' => 0,
                ];
            }
        }
        $order_query = WmOrder::with('shop')->where('is_vip', 1);
        if ($sdate && $edate) {
            $order_query->where('finish_at', '>=', $sdate)->where('finish_at', '<', date("Y-m-d", strtotime($edate) + 86400));
        }
        $orders = $order_query->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (isset($res_data[$order->shop->internal_id])) {
                    $res_data[$order->shop->internal_id]['order']++;
                    $res_data[$order->shop->internal_id]['sale'] += ($order->poi_receive - $order->refund_fee);
                    $res_data[$order->shop->internal_id]['profit'] += $order->vip_total;
                    $res_data[$order->shop->internal_id]['shop_profit'] += $order->vip_business;
                    $res_data[$order->shop->internal_id]['internal_profit'] += $order->vip_internal;
                    if (!in_array($order->shop_id, $shop_ids)) {
                        $shop_ids[] = $order->shop_id;
                    }
                }
            }
        }

        $shops = Shop::where('vip_status', 1)->whereIn('internal_id', $internal_ids)->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $res_data[$shop->internal_id]['shop']++;
                $mt_shop_ids[] = $shop->mtwm;
                $mt_shop_id_keys[$shop->mtwm] = $shop->internal_id;
            }
        }

        if (!empty($mt_shop_ids)) {
            $mk = app('minkang');
            $tmp = array_chunk($mt_shop_ids, 200);
            foreach ($tmp as $item) {
                $res = $mk->getShopInfoByIds(['app_poi_codes' => implode(",", $item)]);
                \Log::info('aaa', [$res]);
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $re) {
                        $mt_id = $re['app_poi_code'];
                        \Log::info("$mt_id,{$re['is_online']},{$re['open_level']}");
                        if ($re['is_online'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_online']++;
                        }
                        if ($re['open_level'] == 1) {
                            $res_data[$mt_shop_id_keys[$mt_id]]['shop_open']++;
                        }
                    }
                }
            }
        }

        if (!empty($res_data)) {
            foreach ($res_data as $k => $v) {
                $res_data[$k]['sale'] = (float) sprintf("%.2f", $v['sale']);
                $res_data[$k]['profit'] = (float) sprintf("%.2f", $v['profit']);
                $res_data[$k]['shop_profit'] = (float) sprintf("%.2f", $v['shop_profit']);
                $res_data[$k]['internal_profit'] = (float) sprintf("%.2f", $v['internal_profit']);
            }
        }

        return $this->success(array_values($res_data));
    }
}
