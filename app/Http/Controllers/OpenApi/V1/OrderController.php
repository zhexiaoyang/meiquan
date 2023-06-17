<?php

namespace App\Http\Controllers\OpenApi\V1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\ErpAccessKey;
use App\Models\ErpAccessShop;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
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
        \Log::info('开发接口订单创建', $request->all());
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
                dispatch(new CreateMtOrder($order_pt, 3));
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
        \Log::info('开发接口订单详情', $request->all());
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

    public function cancel(Request $request)
    {
        \Log::info('开发接口订单取消', $request->all());
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
        if (!$order_wm = WmOrder::where('order_id', $order_id)->where('shop_id', $shop_id)->first()) {
            return $this->error('订单不存在', 422);
        }
        if (!$order = Order::where('order_id', $order_id)->where('shop_id', $shop_id)->first()) {
            return $this->error('订单不存在', 422);
        }

        $ps = $order->ps;

        if ($order->status == 99) {
            // 已经是取消状态
            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-已经是取消状态");
            return $this->success();
        } elseif ($order->status == 80) {
            // 异常状态
            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-异常状态");
            return $this->success();
        } elseif ($order->status == 70) {
            // 已经完成
            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-订单已经完成，不能取消");
            return $this->error("订单已经完成，不能取消");
        } elseif (in_array($order->status, [40, 50, 60])) {
            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-已有平台接单，订单状态：{$order->status}");
            $dd = app("ding");
            if ($ps == 1) {
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] === 0) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = $order->money;
                            }
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "ERP接口操作取消美团跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "ERP接口操作取消美团跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mt_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【美团跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消美团订单返回失败",
                        "id" => $order->id,
                        "ps" => "美团",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消美团订单返回失败", $logs);
                }
            } elseif ($ps == 2) {
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->receive_at)) {
                                $jian = time() - strtotime($order->receive_at);
                                if ($jian <= 1200) {
                                    $jian_money = 2;
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                            }
                            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-扣款：{$jian_money}");
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "（ERP接口操作）取消蜂鸟跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            if ($jian_money > 0) {
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "（ERP接口操作）取消蜂鸟跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                            }
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'fn_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【蜂鸟跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "蜂鸟",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消蜂鸟订单返回失败", $logs);
                }
            } elseif ($ps == 3) {
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if (($result['status'] == 200) || ($result['msg'] = '订单已经取消')) {
                    try {
                        DB::transaction(function () use ($order, $result) {
                            if ($order->shipper_type_ss == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (isset($result['data']['deductAmount']) && is_numeric($result['data']['deductAmount'])) {
                                    $jian_money = $result['data']['deductAmount'] / 100;
                                    \Log::info("主动取消闪送订单，返款扣款金额：" . $jian_money);
                                } else {
                                    if (!empty($order->receive_at)) {
                                        $jian_money = 2;
                                        $jian = time() - strtotime($order->receive_at);
                                        if ($jian >= 480) {
                                            $jian_money = 5;
                                        }
                                        if (!empty($order->take_at)) {
                                            $jian_money = 5;
                                        }
                                    }
                                }
                                // if (!empty($order->receive_at)) {
                                //     $jian_money = 2;
                                //     $jian = time() - strtotime($order->receive_at);
                                //     if ($jian >= 480) {
                                //         $jian_money = 5;
                                //     }
                                //     if (!empty($order->take_at)) {
                                //         $jian_money = 5;
                                //     }
                                // }

                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "ERP接口操作取消闪送跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "ERP接口操作取消闪送跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【闪送跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消闪送订单返回失败",
                        "id" => $order->id,
                        "ps" => "闪送",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消闪送订单返回失败", $logs);
                }
            } elseif ($ps == 4) {
                $fengniao = app("meiquanda");
                $result = $fengniao->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "ERP接口操作取消美全达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mqd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', $order->money);
                            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【美全达跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消美全达订单返回失败",
                        "id" => $order->id,
                        "ps" => "美全达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消美全达订单返回失败", $logs);
                }
            } elseif ($ps == 5) {
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    try {
                        DB::transaction(function () use ($order) {
                            if ($order->shipper_type_dd == 0) {
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->receive_at)) {
                                    $jian = time() - strtotime($order->receive_at);
                                    if ($jian >= 60 && $jian <= 900) {
                                        $jian_money = 2;
                                    }
                                }
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "ERP接口操作取消达达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "ERP接口操作取消达达跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-自主注册，不扣款");
                            }
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【达达跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消达达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消达达订单返回失败",
                        "id" => $order->id,
                        "ps" => "达达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消达达订单返回失败", $logs);
                }
            } elseif ($ps == 6) {
                // 取消UU跑腿订单
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    try {
                        DB::transaction(function () use ($order) {
                            // 用户余额日志
                            // 计算扣款
                            $jian_money = 0;
                            if (!empty($order->take_at)) {
                                $jian_money = 3;
                            }
                            // 当前用户
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "ERP接口操作取消UU跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $jian_money,
                                "type" => 2,
                                "before_money" => ($current_user->money + $order->money),
                                "after_money" => ($current_user->money + $order->money - $jian_money),
                                "description" => "ERP接口操作取消UU跑腿订单扣款：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'uu_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户");
                            if ($jian_money > 0) {
                                $jian_data = [
                                    'order_id' => $order->id,
                                    'money' => $jian_money,
                                    'ps' => $order->ps
                                ];
                                OrderDeduction::create($jian_data);
                            }
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【UU跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "UU",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:UU]-取消UU订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消UU订单返回失败",
                        "id" => $order->id,
                        "ps" => "UU",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消UU订单返回失败", $logs);
                }
            } elseif ($ps == 7) {
                // 取消顺丰跑腿订单
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0 || $result['error_msg'] == '订单已取消, 不可以重复取消') {
                    try {
                        DB::transaction(function () use ($order, $result) {
                            // 用户余额日志
                            if ($order->shipper_type_sf == 0) {
                                // 计算扣款
                                $jian_money = isset($result['result']['deduction_detail']['deduction_fee']) ? ($result['result']['deduction_detail']['deduction_fee']/100) : 0;
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-扣款金额：{$jian_money}");
                                // 当前用户
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::query()->create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "ERP接口操作取消顺丰跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::query()->create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "ERP接口操作取消顺丰跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户");
                                if ($jian_money > 0) {
                                    $jian_data = [
                                        'order_id' => $order->id,
                                        'money' => $jian_money,
                                        'ps' => $order->ps
                                    ];
                                    OrderDeduction::create($jian_data);
                                }
                            } else {
                                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                            }
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'sf_status' => 99,
                                'cancel_at' => date("Y-m-d H:i:s")
                            ]);
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "ERP接口操作取消【顺丰跑腿】订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【ERP接口操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "顺丰",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("ERP接口操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-ERP接口操作取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-取消顺丰订单返回失败", [$result]);
                    $logs = [
                        "des" => "【ERP接口操作取消订单】取消顺丰订单返回失败",
                        "id" => $order->id,
                        "ps" => "顺丰",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("ERP接口操作取消订单，取消顺丰订单返回失败", $logs);
                }
            } elseif ($ps == 8) {
                $this->cancelRiderOrderMeiTuanZhongBao($order, 1, $request->user()->id);
            }
            return $this->success();
        } elseif (in_array($order->status, [20, 30])) {
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，订单状态：{$order->status}");
            // 没有骑手接单，取消订单
            if (in_array($order->mt_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消美团");
                $meituan = app("meituan");
                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->mt_order_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->mt_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【美团跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美团成功");
                }
            }
            if (in_array($order->fn_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消蜂鸟");
                $fengniao = app("fengniao");
                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);
                if ($result['code'] == 200) {
                    $order->status = 99;
                    $order->fn_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【蜂鸟跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，蜂鸟成功");
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消闪送");
                if ($order->shipper_type_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                } else {
                    $shansong = app("shansong");
                }
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【闪送跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，闪送成功");
                }
            }
            if (in_array($order->mqd_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消美全达");
                $meiquanda = app("meiquanda");
                $result = $meiquanda->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    $order->status = 99;
                    $order->mqd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【美全达跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美全达成功");
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消达达");
                if ($order->shipper_type_dd) {
                    $config = config('ps.dada');
                    $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                    $dada = new DaDaService($config);
                } else {
                    $dada = app("dada");
                }
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【达达跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，达达成功");
                }
            }
            if (in_array($order->uu_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消UU");
                $uu = app("uu");
                $result = $uu->cancelOrder($order);
                if ($result['return_code'] == 'ok') {
                    $order->status = 99;
                    $order->uu_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【UU跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，UU成功");
                }
            }
            if (in_array($order->sf_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消顺丰");
                if ($order->shipper_type_sf) {
                    $sf = app("shunfengservice");
                } else {
                    $sf = app("shunfeng");
                }
                $result = $sf->cancelOrder($order);
                if ($result['error_code'] == 0) {
                    $order->status = 99;
                    $order->sf_status = 99;
                    $order->cancel_at = date("Y-m-d H:i:s");
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "ERP接口操作取消【顺丰跑腿】订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，顺丰成功");
                }
            }
            if (in_array($order->zb_status, [20, 30])) {
                $this->cancelRiderOrderMeiTuanZhongBao($order, 1, $request->user()->id);
            }
            return $this->success();
        } else {
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-状态小于20，属于未发单，直接操作取消，状态：{$order->status}");
            // 状态小于20，属于未发单，直接操作取消
            if ($order->status < 0) {
                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                $order->status = -10;
            } else {
                $order->status = 99;
                $order->cancel_at = date("Y-m-d H:i:s");
            }
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "操作取消跑腿订单"
            ]);
            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-未配送");
            return $this->success();
        }
    }
}
