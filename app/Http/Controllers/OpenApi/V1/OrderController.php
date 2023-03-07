<?php

namespace App\Http\Controllers\OpenApi\V1;

use App\Http\Controllers\Controller;
use App\Models\ErpAccessKey;
use App\Models\ErpAccessShop;
use App\Models\Order;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // 跑腿订单-外卖订单，开发接口

    /**
     * 创建外卖订单-跑腿订单
     * @author zhangzhen
     * @data 2023/3/6 2:06 下午
     */
    public function create(Request $request)
    {
        if (!$app_id = $request->get('app_id')) {
            return $this->error('app_id不能为空', 422);
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店ID不能为空', 422);
        }
        if (!$access = ErpAccessKey::where("access_key", $app_id)->first()) {
            return $this->error("app_id错误", 422);
        }
        if (!$access_shop = ErpAccessShop::where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
            return $this->error('门店不存在', 422);
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在', 422);
        }

        if (!$order_id = $request->get('order_id')) {
            return $this->error('订单号不能为空', 422);
        }
        if (!$day_seq = $request->get('day_seq')) {
            return $this->error('当日订单流水号不能为空', 422);
        }
        if (!$customer_name = $request->get('customer_name')) {
            return $this->error('送达客户名字不能为空', 422);
        }
        if (!$customer_tel = $request->get('customer_tel')) {
            return $this->error('送达客户电话不能为空', 422);
        }
        if (!$customer_address = $request->get('customer_address')) {
            return $this->error('送达客户地址不能为空', 422);
        }
        if (!$customer_lng = $request->get('customer_lng')) {
            return $this->error('送达客户经度不能为空', 422);
        }
        if (!$customer_lat = $request->get('customer_lat')) {
            return $this->error('送达客户纬度不能为空', 422);
        }
        // 判断订单号是否存在
        if (Order::where('order_id', $order_id)->first()) {
            return $this->error('该订单已存在', 422);
        }
        if (WmOrder::where('order_id', $order_id)->first()) {
            return $this->error('该订单已存在', 422);
        }
        $detail_data = [];
        $detail = $request->get('detail', '');
        if ($detail && $detail_arr = json_decode($detail, true)) {
            foreach ($detail_arr as $v) {
                $name = $v['name'] ?? '';
                $upc = $v['upc'] ?? '';
                $unit = $v['unit'] ?? '';
                $quantity = $v['quantity'] ?? 0;
                $price = $v['price'] ?? 0;
                if (!$name) {
                    return $this->error('商品名称不能为空', 422);
                }
                $detail_data[] = [
                    'food_name' => $name,
                    'upc' => $upc,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'price' => $price,
                ];
            }
        }
        $caution = $request->get('caution', '');
        $order_from = $request->get('order_from', 0);
        $price = $request->get('price', 0);
        $weight = $request->get('weight', 0);
        $order_wm_data = [
            'user_id' => $shop->user_id,
            "shop_id" => $shop->id,
            "order_id" => $order_id,
            "wm_order_id_view" => $order_id,
            // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达，21 开发平台）
            "platform" => 0,
            "wm_shop_name" => $shop->shop_name,
            "recipient_name" => $customer_name,
            "recipient_phone" => $customer_tel,
            "recipient_address" => $customer_address,
            "latitude" => $customer_lat,
            "longitude" => $customer_lng,
            "total" => $price,
            "caution" => $caution,
            "ctime" => time(),
            // "delivery_time" => $data['delivery_time'],
            "day_seq" => $day_seq,
        ];
        $order_pt_data = [
            // 'wm_id' => $order_wm->id,
            'delivery_id' => $order_id,
            'user_id' => $shop->user_id,
            'order_id' => $order_id,
            'shop_id' => $shop->id,
            "wm_shop_name" => $shop->shop_name,
            'delivery_service_code' => "4011",
            'receiver_name' => $customer_name,
            "receiver_address" => $customer_address,
            'receiver_phone' => $customer_tel,
            "receiver_lng" => $customer_lng,
            "receiver_lat" => $customer_lat,
            "caution" => $caution,
            'coordinate_type' => 0,
            "goods_value" => $price,
            'goods_weight' => 3,
            "day_seq" => $day_seq,
            // 订单平台（1 美团外卖，2 饿了么，3京东到家，4美全达，21 开发平台）
            'platform' => 0,
            'status' => 0,
            'order_type' => 0,
            "pick_type" => 0,
        ];
        try {
            $order = DB::transaction(function () use ($order_wm_data, $order_pt_data) {
                $order_wm = WmOrder::create($order_wm_data);
                $order_pt_data['wm_id'] = $order_wm->id;
                $order_pt = Order::create($order_pt_data);
                return $order_wm;
            });
        } catch (\Exception $e) {
            \Log::error('aa', [
                $e->getMessage(),
                $e->getLine(),
                $e->getFile(),
            ]);
            return $this->error('订单创建失败');
        }
        return $this->success(['order_id' => $order->order_id]);
    }

    /**
     * 外卖订单跑腿订单，详情
     * @author zhangzhen
     * @data 2023/3/6 2:06 下午
     */
    public function info(Request $request)
    {
        if (!$app_id = $request->get('app_id')) {
            return $this->error('app_id不能为空', 422);
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店ID不能为空', 422);
        }
        if (!$access = ErpAccessKey::where("access_key", $app_id)->first()) {
            return $this->error("app_id错误", 422);
        }
        if (!$access_shop = ErpAccessShop::where(['shop_id' => $shop_id, 'access_id' => $access->id])->first()) {
            return $this->error('门店不存在', 422);
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在', 422);
        }

        if (!$order_id = $request->get('order_id')) {
            return $this->error('订单号不能为空', 422);
        }
        if (!$order = WmOrder::where('order_id', $order_id)->where('shop_id', $shop_id)->first()) {
            return $this->error('订单不存在', 422);
        }
        if (!$order_pt = Order::where('order_id', $order_id)->where('shop_id', $shop_id)->first()) {
            return $this->error('订单不存在', 422);
        }
        $res = [
            'order_id' => $order->order_id,
            'customer_name' => $order->recipient_name,
            'customer_tel' => $order->recipient_phone,
            'customer_address' => $order->recipient_address,
            'customer_lng' => $order->longitude,
            'customer_lat' => $order->latitude,
            'price' => $order->total,
            'caution' => $order->caution,
            'courier_name' => $order_pt->courier_name,
            'courier_tel' => $order_pt->courier_phone,
            'create_time' => isset($order_pt->created_at) ? date("Y-m-d H:i:s", strtotime($order_pt->created_at)) : '',
            'send_time' => isset($order_pt->send_time) ? date("Y-m-d H:i:s", strtotime($order_pt->send_time)) : '',
            'receive_time' => isset($order_pt->receive_time) ? date("Y-m-d H:i:s", strtotime($order_pt->receive_time)) : '',
            'pickup_time' => isset($order_pt->pickup_time) ? date("Y-m-d H:i:s", strtotime($order_pt->pickup_time)) : '',
            'over_time' => isset($order_pt->over_time) ? date("Y-m-d H:i:s", strtotime($order_pt->over_time)) : '',
            'exception_msg' => '',
            'status' => 1
        ];
        if ($order_pt->status === 99) {
            $res['status'] = 7;
        } elseif ($order_pt->status === 70) {
            $res['status'] = 6;
        } elseif ($order_pt->status === 60) {
            $res['status'] = 5;
        } elseif ($order_pt->status === 40) {
            $res['status'] = 4;
        } elseif ($order_pt->status === 20) {
            $res['status'] = 3;
        } elseif ($order_pt->status === 5) {
            $res['status'] = 1;
            $res['exception_msg'] = '余额不足';
        } elseif ($order_pt->status === 0) {
            $res['status'] = 1;
        }
        return $this->success($res);
    }
}
