<?php

namespace App\Http\Controllers\Erp\V2;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function order_no(Request $request)
    {
        $mt_id = $request->get('shopIdMeiTuan');
        $ele_id = $request->get('shopIdEle');
        if (!$mt_id && !$ele_id) {
            return $this->error('美团ID和饿了么ID至少需要一个');
        }
        if (!$stime = $request->get('start_time')) {
            return $this->error('起始时间不能为空');
        }
        if (!$etime = $request->get('end_time')) {
            return $this->error('结束时间不能为空');
        }
        $s_int = strtotime($stime);
        $e_int = strtotime($etime);
        if ($e_int < $s_int) {
            return $this->error('起始时间不能大于结束时间');
        }
        $day = ($e_int - $s_int) / 86400;
        if ($day > 10) {
            return $this->error('只能查询10天内的订单');
        }
        $shop = null;
        if ($mt_id) {
            if (!$shop = Shop::select('id', 'waimai_mt')->where('waimai_mt', $mt_id)->first()) {
                return $this->error('美团ID对应门店不存在');
            }
        }
        if ($ele_id) {
            if (!$shop_ele = Shop::select('id', 'waimai_ele')->where('waimai_ele', $ele_id)->first()) {
                return $this->error('饿了么ID对应门店不存在');
            }
            if ($shop) {
                if ($shop->id != $shop_ele->id) {
                    return $this->error('美团ID和饿了么ID不是一个门店');
                }
            } else {
                $shop = $shop_ele;
            }
        }
        if (!$shop) {
            return $this->error('门店不存在');
        }

        $result = [];
        $orders = WmOrder::select('id','order_id','platform','created_at')->where('shop_id', $shop->id)
            ->where('created_at', '>=', $stime)->where('created_at', '<=', $etime)->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                if ($mt_id && $order->platform == 1) {
                    $result[] = [
                        'order_id' => $order->order_id,
                        'platform' => $order->platform,
                        'created_at' => date("Y-m-d H:i:s", strtotime($order->created_at)),
                    ];
                }
                if ($ele_id && $order->platform == 2) {
                    $result[] = [
                        'order_id' => $order->order_id,
                        'platform' => $order->platform,
                        'created_at' => date("Y-m-d H:i:s", strtotime($order->created_at)),
                    ];
                }
            }
        }

        return $this->success($result);
    }

    /**
     * 订单详情
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/7/26 3:34 下午
     */
    public function info(Request $request)
    {
        $mt_id = $request->get('shopIdMeiTuan');
        $ele_id = $request->get('shopIdEle');
        if (!$mt_id && !$ele_id) {
            return $this->error('美团ID和饿了么ID至少需要一个');
        }
        if (!$order_id = $request->get('orderId')) {
            return $this->error('订单号不能为空');
        }

        $res = [];
        if ($mt_id) {
            if (!$shop = Shop::select('id','meituan_bind_platform','waimai_mt','commission_mt')->where('waimai_mt', $mt_id)->first()) {
                return $this->error('门店不存在');
            }
            $meituan = null;
            if ($shop->meituan_bind_platform === 4) {
                $meituan = app('minkang');
            } elseif ($shop->meituan_bind_platform === 31) {
                $meituan = app('meiquan');
            }
            if ($meituan) {
                $params_mt = [
                    'order_id' => $order_id,
                ];
                $mt_res = $meituan->getOrderDetail($params_mt, $shop->meituan_bind_platform === 31 ? $mt_id : '');
                if (!empty($mt_res) && is_array($mt_res['data']) && !empty($mt_res['data'])) {
                    $mt_data = $mt_res['data'];
                    $poi_receive_detail_yuan = json_decode(urldecode($mt_data['poi_receive_detail_yuan']), true);
                    // 是否处方
                    $order_tag_list = $mt_data['order_tag_list'];
                    $prescription_fee = 0;
                    if (!is_array($order_tag_list)) {
                        $order_tag_list = json_decode(urldecode($mt_data['order_tag_list']), true);
                        $prescription_fee = 0.8;
                        $reconciliationExtras = json_decode($poi_receive_detail_yuan['reconciliationExtras'] ?? '', true);
                        $platformChargeFee2 = (float) $reconciliationExtras['platformChargeFee2'] ?? null;
                        if (!is_null($platformChargeFee2)) {
                            if ($platformChargeFee2 == 0.6) {
                                $prescription_fee = 0.2;
                            }
                        }
                    }
                    $is_prescription = 0;
                    if (in_array(8, $order_tag_list)) {
                        $is_prescription = 1;
                    }
                    $res = [
                        "orderId" => $mt_data['order_id'],
                        "isPrescription" => $is_prescription,
                        "recipientName" => $mt_data['recipient_name'],
                        "recipientPhone" => $mt_data['recipient_phone'],
                        "recipientAddress" => $mt_data['recipient_address'],
                        "shippingFee" => $mt_data['shipping_fee'],
                        "total" => $mt_data['total'],
                        "originalPrice" => $mt_data['original_price'],
                        "poiReceive" => $poi_receive_detail_yuan['poiReceive'] ?? 0,
                        "caution" => $mt_data['caution'],
                        "status" => $mt_data['status'],
                        "ctime" => $mt_data['ctime'],
                        "utime" => $mt_data['utime'],
                        "latitude" => $mt_data['latitude'],
                        "longitude" => $mt_data['longitude'],
                        "daySeq" => $mt_data['day_seq'],
                        "keepAccount" => 0,
                    ];
                    $products = json_decode($mt_data['detail'], true);
                    $detail = [];
                    // 所有商品价格总和
                    $product_total = 0;
                    if (!empty($products)) {
                        foreach ($products as $product) {
                            $product_total += $product['price'] * $product['quantity'];
                            $detail[] = [
                                "storeCode" => $product['app_food_code'],
                                "skuId" => $product['sku_id'],
                                "name" => $product['food_name'],
                                "upc" => $product['upc'] ?? '',
                                "quantity" => $product['quantity'],
                                "price" => $product['price'],
                                "unit" => $product['unit'],
                                "spec" => $product['spec'],
                                "total_price" => (float) sprintf("%.2f", $product['price'] * $product['quantity']),
                                "keepAccount" => 0,
                            ];
                        }
                    }
                    // ------------------下账金额----------------
                    $poi_receive_detail_yuan = json_decode($mt_data['poi_receive_detail_yuan'], true);
                    // 代运营服务费
                    $operate_service_fee = ($shop->commission_mt * $poi_receive_detail_yuan['poiReceive'] ?? 0) / 100;
                    // 佣金，单位为元
                    $foodShareFeeChargeByPoi = $poi_receive_detail_yuan['foodShareFeeChargeByPoi'];
                    // 配送费，单位为元
                    $logisticsFee = $poi_receive_detail_yuan['logisticsFee'];
                    // 美团承担
                    $actOrderChargeByMt = 0;
                    // 商家承担
                    $actOrderChargeByPoi = 0;
                    if (!empty($poi_receive_detail_yuan['actOrderChargeByMt'])) {
                        foreach ($poi_receive_detail_yuan['actOrderChargeByMt'] as $item) {
                            $actOrderChargeByMt += $item['money'];
                        }
                    }
                    if (!empty($poi_receive_detail_yuan['actOrderChargeByPoi'])) {
                        foreach ($poi_receive_detail_yuan['actOrderChargeByPoi'] as $item) {
                            $actOrderChargeByPoi += $item['money'];
                        }
                    }
                    // 订单下帐金额 = 所有商品原价总金额 - 佣金 - 代运营费 -处方费 - 商家活动承担 + 平台活动承担 + 平台返配送费
                    $keepAccount = $product_total - $foodShareFeeChargeByPoi - $operate_service_fee - $prescription_fee - $actOrderChargeByPoi + $actOrderChargeByMt + $logisticsFee;
                    $keepAccountText = "{$order_id}订单下帐金额 = 所有商品原价总金额($product_total) - 佣金($foodShareFeeChargeByPoi) - 代运营费($operate_service_fee) -处方费($prescription_fee) - 商家活动承担($actOrderChargeByPoi) + 平台活动承担($actOrderChargeByMt) + 平台返配送费($logisticsFee)";
                    // \Log::info($keepAccountText);
                    if (!empty($detail)) {
                        foreach ($detail as $k => $v) {
                            $ratio = $v['total_price'] / $product_total;
                            $productKeepAccount = round($keepAccount * $ratio, 2);
                            $productKeepAccountText = $v['name'] . ",商品下账金额 = 订单下帐金额($keepAccount) * 单个商品分摊比($ratio)";
                            $detail[$k]['keepAccount'] = (float) sprintf("%.2f", $productKeepAccount);
                            $detail[$k]['keepAccountText'] = $productKeepAccountText;
                        }
                    }
                    $res['keepAccount'] = (float) sprintf("%.2f", $keepAccount);
                    $res['keepAccountText'] = $keepAccountText;
                    $res['detail'] = $detail;
                }
            }
        } else {
            if (!$shop = Shop::select('id','waimai_ele','prescription_cost_ele','commission_mt')->where('waimai_ele', $ele_id)->first()) {
                return $this->error('门店不存在');
            }
            $ele = app('ele');
            $order_request = $ele->orderInfo($order_id);
            \Log::info('aa', $order_request);
            if (!empty($order_request) && isset($order_request['body']['data']) && !empty($order_request['body']['data'])) {
                // 订单数组
                $ele_data = $order_request['body']['data'];
                $products = $ele_data['products'];
                $is_prescription = $ele_data['order']['is_prescription'];
                $prescription_fee = $is_prescription ? $shop->prescription_cost_ele : 0;
                // 饿了么订单状态： 1 待确认，5 已确认，7 骑士已接单，8 骑士已取餐，快递已揽收（快递发货场景），9 已完成，10 已取消
                // 美团订单状态：返回订单当前的状态。目前平台的订单状态参考值有：1-用户已提交订单；2-向商家推送订单；4-商家已确认；8-订单已完成；9-订单已取消。
                $status_map = [1 => 1, 5 => 4, 7 => 4, 8 => 4, 9 => 8, 10 => 9];
                $res = [
                    "orderId" => $ele_data['order']['order_id'],
                    "isPrescription" => $is_prescription,
                    "recipientName" => empty($ele_data['user']['name']) ? "无名客人" : $ele_data['user']['name'],
                    "recipientPhone" => str_replace(',', '_', $ele_data['user']['phone']),
                    "recipientAddress" => $ele_data['user']['address'],
                    "shippingFee" => $ele_data['order']['send_fee'] / 100,
                    "total" => $ele_data['order']['user_fee'] / 100,
                    "originalPrice" => $ele_data['order']['total_fee'] / 100,
                    "poiReceive" => $ele_data['order']['shop_fee'] / 100,
                    "keepAccount" => 0,
                    "caution" => $ele_data['order']['remark'] ?: '',
                    "status" => $status_map[$ele_data['order']['status']],
                    "ctime" => $ele_data['order']['create_time'],
                    "utime" => $ele_data['order']['create_time'],
                    "latitude" => $ele_data['user']['coord_amap']['latitude'],
                    "longitude" => $ele_data['user']['coord_amap']['longitude'],
                    "daySeq" => $ele_data['order']['order_index'] ?? 0,
                ];
                $detail = [];
                $product_total = 0;
                if (!empty($products)) {
                    foreach ($products as $product_bag) {
                        foreach ($product_bag as $product) {
                            // $product_total += ($product['product_fee'] + $product['package_fee']) / 100;
                            $product_total += ($product['product_fee']) / 100;
                            $detail[] = [
                                "storeCode" => $product['custom_sku_id'],
                                "skuId" => $product['custom_sku_id'],
                                "name" => $product['product_name'],
                                "upc" => $product['upc'] ?? '',
                                "quantity" => $product['product_amount'],
                                "price" => $product['product_price'] / 100,
                                "unit" => '',
                                "spec" => '',
                                "total_price" => ($product['product_fee']) / 100,
                                "keepAccount" => 0,
                            ];
                        }
                    }
                }
                // 代运营服务费
                $operate_service_fee = ($shop->commission_ele * $ele_data['order']['shop_fee'] / 100) / 100;
                // 佣金
                $commission = $ele_data['order']['commission'] / 100;
                // 商家活动承担
                $shop_rate = 0;
                // 平台活动承担
                $baidu_rate = 0;
                // 活动
                $discounts = $ele_data['discount'];
                if (!empty($discounts)) {
                    foreach ($discounts as $discount) {
                        if ($discount['shop_rate']) {
                            $shop_rate += $discount['shop_rate'];
                        }
                        if ($discount['baidu_rate']) {
                            $baidu_rate += $discount['baidu_rate'];
                        }
                    }
                }
                $shop_rate /= 100;
                $baidu_rate /= 100;
                // 配送费
                $send_fee = $ele_data['order']['send_fee'] / 100;
                // 订单下帐金额 = 所有商品原价总金额 - 佣金 - 代运营费 -处方费 - 商家活动承担 + 平台活动承担 + 平台返配送费
                $keepAccount = $product_total - $commission - $operate_service_fee - $prescription_fee - $shop_rate + $baidu_rate + $send_fee;
                $keepAccountText = "{$order_id}订单下帐金额 = 所有商品原价总金额($product_total) - 佣金($commission) - 代运营费($operate_service_fee) -处方费($prescription_fee) - 商家活动承担($shop_rate) + 平台活动承担($baidu_rate) + 平台返配送费($send_fee)";
                if (!empty($detail)) {
                    foreach ($detail as $k => $v) {
                        $ratio = $v['total_price'] / $product_total;
                        $productKeepAccount = round($keepAccount * $ratio, 2);
                        $productKeepAccountText = $v['name'] . ",商品下账金额 = 订单下帐金额($keepAccount) * 单个商品分摊比($ratio)";
                        $detail[$k]['keepAccount'] = (float) sprintf("%.2f", $productKeepAccount);
                        $detail[$k]['keepAccountText'] = $productKeepAccountText;
                    }
                }
                $res['keepAccount'] = (float) sprintf("%.2f", $keepAccount);
                $res['keepAccountText'] = $keepAccountText;
                $res['detail'] = $detail;
            }
        }
        return $this->success($res);
    }

    public function orderStatus(Request $request)
    {
        $res = [
            "status" => 4
        ];
        return $this->success($res);
    }
}
