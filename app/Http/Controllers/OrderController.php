<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except('sync', 'sync2', 'cancel');
    }

    /**
     * 订单列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $shop_id = $request->get('shop_id', 0);
        $search_key = $request->get('search_key', '');
        $status = $request->get('status');
        $query = Order::with(['shop' => function($query) {
            $query->select('id', 'shop_id', 'shop_name');
        }])->select('id','shop_id','order_id','peisong_id','receiver_name','receiver_phone','money','failed',
            'receiver_address','tool','ps',
            'mt_status','money_mt','fail_mt',
            'fn_status','money_fn','fail_fn',
            'ss_status','money_ss','fail_ss',
            'mqd_status','money_mqd','fail_mqd',
            'dd_status','money_dd','fail_dd',
            'send_at','created_at','over_at','cancel_at','receive_at','take_at',
            'platform','receiver_lng','expected_delivery_time','receiver_lat','status');

        // 关键字搜索
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('delivery_id', 'like', "%{$search_key}%")
                    ->orWhere('order_id', 'like', "%{$search_key}%")
                    ->orWhere('peisong_id', 'like', "%{$search_key}%")
                    ->orWhere('receiver_name', 'like', "%{$search_key}%")
                    ->orWhere('receiver_phone', 'like', "%{$search_key}%");
            });
        }

        if ($shop_id) {
            $query->where("shop_id", $shop_id);
        }

        // 判断可以查询的药店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        // 状态查询
        if (!is_null($status)) {
            $query->where('status', $status);
        }

        // 查询订单
        $orders = $query->where('status', '>', -3)->orderBy('id', 'desc')->paginate($page_size);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (in_array($order->status, [3,8,20 ,30 ,40 ,50 ,60])) {
                    $order->is_cancel = 1;
                } else {
                    $order->is_cancel = 0;
                }
                // $order->status_code = $order->status;
                // $order->status = $order->status_label;
                if (isset($order->shop->shop_name)) {
                    $order->shop_name = $order->shop->shop_name;
                } else {
                    $order->shop_name = "";
                }
                $order->delivery = $order->expected_delivery_time > 0 ? date("m-d H:i", $order->expected_delivery_time) : "";
                $number = 0;
                if (!empty($order->send_at) && ($second = strtotime($order->send_at)) > 0) {
                    if ($setting = OrderSetting::query()->where("shop_id", $order->shop_id)->first()) {
                        $ttl = $setting->delay_send;
                    } else {
                        $ttl = config("ps.shop_setting.delay_send");
                    }
                    $number = $second - time() + $ttl > 0 ? $second - time() + $ttl : 0;
                }
                if ($order->status == 8 && $number == 0 ) {
                    $order->status = 0;
                }
                $order->number = $number;
                unset($order->shop);
            }
        }
        return $this->success($orders);
    }

    /**
     * 创建订单
     * @param Request $request
     * @param Order $order
     * @return mixed
     */
    public function store(Request $request, Order $order)
    {
        // 状态（-3取消发送，-2等待发送，-1:发送失败，0：未发送，3：余额不足，5：暂无运力，10：待接单，20：已接单，30：已取货，50：已送达，99：已取消）

        $shop_id = $request->get('shop_id', 0);

        \Log::info("[跑腿订单-手动创建订单]-[门店ID: {$shop_id}]]");

        if (!$shop = Shop::query()->find($shop_id)) {
            $shop = Shop::query()->where('shop_id', $shop_id)->first();
        }
        if (!$shop) {
            return $this->error('门店不存在');
        }

        if (!Redis::setnx("create_shop_id_" . $shop_id, $shop_id)) {
            \Log::info("[跑腿订单-手动创建订单]-[门店ID: {$shop_id}]]-重复提交");
            return $this->error("已经创建订单了，请刷新试下");
        }
        Redis::expire("create_shop_id_" . $shop_id, 6);

        $order->fill($request->all());
        $order->shop_id = $shop->id;
        $order->user_id = $shop->user_id;
        $order->tool = $shop->tool;
        // 订单未发送状态
        // $order->status = 0;
        // 订单倒计时参考时间
        // $order->send_at = date("Y-m-d H:i:s");
        // 订单即将发送
        $order->status = 8;
        // 保存数据
        if ($order->save()) {
            \Log::info("[跑腿订单-手动创建订单]-[订单ID: {$order->id}，订单号: {$order->order_id}]");
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "创建跑腿订单"
            ]);
            // $ding_notice = app("ding");
            // $res = $ding_notice->sendMarkdownMsgArray("用户手动创建订单了", ["datetime" => date("Y-m-d H:i:s"), "order_id" => $order->order_id]);
            // \Log::info('钉钉日志发送状态-用户手动创建订单了', [$res]);
            // 订单倒计时参考时间
            // $order->send_at = date("Y-m-d H:i:s");
            // 订单即将发送
            // $order->status = 8;
            // $order->save();
            // $setting = OrderSetting::where("shop_id", $shop->id)->first();
            // if ($setting) {
            //     $delay_send_ttl = $setting->delay_send;
            // } else {
            //     $delay_send_ttl = config("ps.shop_setting.delay_send");
            // }
            // dispatch(new CreateMtOrder($order, $delay_send_ttl));
            dispatch(new CreateMtOrder($order));
            \Log::info("[跑腿订单-手动创建订单]-[订单ID: {$order->id}，订单号: {$order->order_id}]，立即发单");

            return $this->success([]);
        }
        return $this->error("创建失败");
    }

    /**
     * 重新发送订单-新
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2020/10/26 10:04 上午
     */
    public function resend(Request $request)
    {
        $order_id = $request->get("order_id", 0);

        if (!Redis::setnx("reset_order_id_" . $order_id, $order_id)) {
            \Log::info("[跑腿订单-重新发送]-[订单ID: {$order_id}]]-重复提交");
            return $this->error("已经重新发送了，请刷新试下");
        }
        Redis::expire("reset_order_id_" . $order_id, 6);

        if (!$order = Order::find($order_id)) {
            return $this->error("订单不存在");
        }

        \Log::info("[跑腿订单-重新发送]-[订单ID: {$order_id}]-[订单号: {$order->order_id}]");

        if (!$shop = Shop::query()->where(['status' => 40, 'id' => $order->shop_id])->first()) {
            return $this->error("该门店不能发单");
        }

        if (($order->status >= 20) && ($order->status <= 70)) {
            return $this->error("订单状态不正确，请先取消订单在重新发送");
        }

        if ($request->get("mt", 0) === 0) {
            if (!$order->fail_mt) {
                $order->fail_mt = '重新发送订单-不选择';
            }
        } else {
            $order->mt_status = 0;
            $order->fail_mt = '';
        }

        if ($request->get("fn", 0) === 0) {
            if (!$order->fail_fn) {
                $order->fail_fn = '重新发送订单-不选择';
            }
        } else {
            $order->fn_status = 0;
            $order->fail_fn = '';
        }

        if ($request->get("ss", 0) === 0) {
            if (!$order->fail_ss) {
                $order->fail_ss = '重新发送订单-不选择';
            }
        } else {
            $order->ss_status = 0;
            $order->fail_ss = '';
        }

        if ($request->get("sf", 0) === 0) {
            if (!$order->fail_sf) {
                $order->fail_sf = '重新发送订单-不选择';
            }
        } else {
            $order->sf_status = 0;
            $order->fail_sf = '';
        }

        if ($request->get("mqd", 0) === 0) {
            if (!$order->fail_mqd) {
                $order->fail_mqd = '重新发送订单-不选择';
            }
        } else {
            $order->mqd_status = 0;
            $order->fail_mqd = '';
        }

        if ($request->get("dd", 0) === 0) {
            if (!$order->fail_dd) {
                $order->fail_dd = '重新发送订单-不选择';
            }
        } else {
            $order->dd_status = 0;
            $order->fail_dd = '';
        }

        $order->status = 0;
        $order->ps = 0;

        $order->save();

        $order = Order::find($order_id);

        dispatch(new CreateMtOrder($order));

        OrderLog::create([
            "order_id" => $order->id,
            "des" => "重新发送跑腿订单"
        ]);

        return $this->success("发送成功");
    }

    /**
     * 重新发送订单-旧
     * @param Order $order
     * @return mixed
     */
    public function send( Order $order)
    {
        if (!$shop = Shop::query()->where(['status' => 40, 'id' => $order->shop_id])->first()) {
            return $this->error("该门店不能发单");
        }

        dispatch(new CreateMtOrder($order));

        return $this->success("提交成功");
    }

    public function destroy(Order $order)
    {
        $meituan = app("meituan");
        $result = $meituan->delete([
            'delivery_id' => $order->delivery_id,
            'peisong_id' => $order->peisong_id,
            'cancel_reason_id' => 399,
            'cancel_reason' => '其他原因',
        ]);

        if ($result['code'] === 0 && $order->update(['status' => 99])) {
            return $this->success([]);
        }

        return $this->error("取消失败");
    }

    public function show(Order $order)
    {
        // $order->status = $order->status_label;
        $order->load("deduction");
        $order->load(['shop' => function($query) {
            $query->select('id', 'shop_id', 'shop_name');
        }]);
        $order->load("logs");
        return $this->success($order);
    }

    public function checkStatus(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->queryStatus([
            'delivery_id' => $order->delivery_id,
            'peisong_id' => $order->peisong_id,
        ]);

        return $this->success($result);
    }

    public function location(Order $order)
    {

        $meituan = app("meituan");
        $result = $meituan->location([
            'delivery_id' => $order->delivery_id,
            'peisong_id' => $order->peisong_id,
        ]);

        return $this->success($result);
    }

    /**
     * 同步订单-旧
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function sync(Request $request)
    {
        \Log::info('同步订单-旧sync');
        $type = intval($request->get('type', 0));
        $order_id = $request->get('order_id', 0);

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif ($type === 2) {
            $meituan = app("mrx");
        } elseif ($type === 3) {
            $meituan = app("jay");
        } elseif ($type === 4) {
            $meituan = app("minkang");
        } elseif ($type === 5) {
            $meituan = app("qinqu");
        } else {
            return $this->error('参数错误');
        }

        $res = $meituan->getOrderDetail(['order_id' => $order_id]);
        if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
            $data = $res['data'];
            if (Order::where('order_id', $data['wm_order_id_view'])->first()) {
                return $this->error('订单已存在');
            }

            $shop_id = isset($data['app_poi_code']) ? $data['app_poi_code'] : 0;

            if (!$shop = Shop::where('shop_id', $shop_id)->first()) {
                return $this->error('药店不存在');
            }

            // 设置状态
            $status = -1;
            if ($data['status'] < 4) {
                $status = -2;
            }
            if ($data['status'] > 4) {
                $status = -3;
            }

            // 设置重量
            $weight = isset($data['total_weight']) ? $data['total_weight'] : 0;

            // 创建订单信息
            $order_data = [
                'delivery_id' => $data['wm_order_id_view'],
                'order_id' => $data['wm_order_id_view'],
                'shop_id' => $shop_id,
                'delivery_service_code' => "4011",
                'receiver_name' => $data['recipient_name'],
                'receiver_address' => $data['recipient_address'],
                'receiver_phone' => $data['recipient_phone'],
                'receiver_lng' => $data['longitude'],
                'receiver_lat' => $data['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['total'],
                'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                'type' => $type,
                'status' => $status,
                'tool' => $shop->tool
            ];

            // 判断是否预约单
            if (isset($data['delivery_time']) && $data['delivery_time'] > 0) {
                $order_data['order_type'] = 1;
                $order_data['expected_pickup_time'] = $data['delivery_time'] - 3600;
                $order_data['expected_delivery_time'] = $data['delivery_time'];
            }

            // 创建订单
            $order = new Order($order_data);

            // 保存订单
            if ($order->save()) {
                if ($status === -1) {
                    dispatch(new CreateMtOrder($order));
                }
            }
            return $this->success([]);
        }
        return $this->error('未获取到订单');
    }

    /**
     * 同步订单
     * @param Request $request
     * @return mixed
     */
    public function sync2(Request $request)
    {
        $type = intval($request->get('type', 0));
        $order_id = $request->get('order_id', 0);

        \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]");
        // \Log::info('同步订单参数', ['type' => $type, 'order_id' => $order_id]);

        // if (!$type || !in_array($type, [1,2,3,4]) || !$order_id) {
        //     return $this->error('参数错误');
        // }

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif($type === 2) {
            $meituan = app("mrx");
        } elseif($type === 3) {
            $meituan = app("jay");
        } elseif($type === 4) {
            $meituan = app("minkang");
        } elseif($type === 5) {
            $meituan = app("qinqu");
        } elseif($type === 6) {
            $meituan = app("jilin");
        } else {
            return $this->error('参数错误');
        }

        $res = $meituan->getOrderDetail(['order_id' => $order_id]);

        // \Log::info('获取订单信息', [$res]);
        \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-获取订单信息");

        if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
            $data = $res['data'];

            if ($data['recipient_address'] == "到店自取") {
                \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-到店自取订单不创建订单");
                // \Log::info('到店自取订单-不创建订单', ['order_id' => $order_id]);
                return $this->error('到店自取订单');
            }

            if ($data['recipient_address'] == "到店自取@#到店自取") {
                \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-到店自取订单不创建订单");
                // \Log::info('到店自取订单-不创建订单', ['order_id' => $order_id]);
                return $this->error('到店自取订单');
            }

            if ($data['pick_type'] === 1) {
                \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-到店自取订单不创建订单");
                // \Log::info('到店自取订单-不创建订单', ['order_id' => $order_id]);
                return $this->error('到店自取订单');
            }

            if (Order::where('order_id', $data['wm_order_id_view'])->first()) {
                \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-订单已存在");
                // \Log::info('订单已存在', compact("type", "order_id"));
                return $this->error('订单已存在');
            }

            $shop_id = isset($data['app_poi_code']) ? $data['app_poi_code'] : 0;

            if (!$shop = Shop::where('mt_shop_id', $shop_id)->first()) {
                \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-药店不存在");
                // \Log::info('药店不存在', compact("type", "order_id"));
                return $this->error('药店不存在');
            }

            // 设置状态
            // 1 用户已提交订单 ，2 向商家推送订单 ，3 商家已收到 ，4 商家已确认 ，6 订单配送中 ，7 订单已送达 ，8 订单已完成 ，9 订单已取消
            // -30 未付款， ，-20 等待发送， ，-10 发送失败， ，0 订单未发送， ，5：余额不足， ，10 暂无运力， ，20 待接单， ，30 平台已接单，
            // 40 已分配骑手， ，50 取货中， ，60 已取货， ，70 已送达， ，80 异常， ，99 已取消，
            $status = 0;
            if ($data['status'] < 4) {
                $status = -30;
            }
            if ($data['status'] > 4) {
                $status = -10;
            }
            if ($data['status'] == 4) {
                $status = 0;
            }

            // 设置重量
            $weight = isset($data['total_weight']) ? $data['total_weight'] : 0;

            // 创建订单信息
            $order_data = [
                'delivery_id' => $data['wm_order_id_view'],
                'order_id' => $data['wm_order_id_view'],
                'user_id' => $shop->user_id,
                'shop_id' => $shop->id,
                'delivery_service_code' => "4011",
                'receiver_name' => trim($data['recipient_name']) ? trim($data['recipient_name']) : '无名',
                'receiver_address' => $data['recipient_address'],
                'receiver_phone' => $data['recipient_phone'],
                'receiver_lng' => $data['longitude'],
                'receiver_lat' => $data['latitude'],
                'coordinate_type' => 0,
                'goods_value' => $data['total'],
                // 'goods_weight' => $weight <= 0 ? rand(10, 50) / 10 : $weight/1000,
                'goods_weight' => 3,
                'day_seq' => $data['day_seq'] ?? 0,
                'platform' => 1,
                'type' => $type,
                'status' => $status,
                'order_type' => 0,
                'tool' => $shop->tool
            ];

            // 判断是否预约单
            if (isset($data['delivery_time']) && $data['delivery_time'] > 0) {
                if ($status === 0) {
                    $order_data['status'] = 3;
                }
                $order_data['order_type'] = 1;
                $order_data['expected_pickup_time'] = $data['delivery_time'] - 3600;
                $order_data['expected_delivery_time'] = $data['delivery_time'];
            }

            // 创建订单
            $order = new Order($order_data);

            // 保存订单
            if ($order->save()) {
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（美团外卖）自动创建跑腿订单"
                ]);
                if ($status === 0) {
                    if ($order->order_type) {
                        $qu = 2400;
                        if ($order->distance <= 2) {
                            $qu = 1800;
                        }

                        dispatch(new PushDeliveryOrder($order, ($order->expected_delivery_time - time() - $qu)));

                        // \Log::info('美团创建预约订单成功', $order->toArray());
                        \Log::info("[跑腿订单-同步订单]-[订单号: {$order_id}]-[来源: {$type}]-美团创建预约订单成功");

                        $ding_notice = app("ding");

                        $logs = [
                            "des" => "接到预订单：" . $qu,
                            "datetime" => date("Y-m-d H:i:s"),
                            "order_id" => $order->order_id,
                            "status" => $order->status,
                            "ps" => $order->ps
                        ];

                        $ding_notice->sendMarkdownMsgArray("接到美团预订单", $logs);
                    } else {
                        $order->send_at = date("Y-m-d H:i:s");
                        $order->status = 8;
                        $order->save();
                        $setting = OrderSetting::where("shop_id", $shop->id)->first();
                        if ($setting) {
                            $delay_send_ttl = $setting->delay_send;
                        } else {
                            $delay_send_ttl = config("ps.shop_setting.delay_send");
                        }
                        dispatch(new CreateMtOrder($order, $delay_send_ttl));
                        // dispatch(new CreateMtOrder($order));
                    }
                }
            }
            return $this->success([]);
        }
        return $this->error('未获取到订单');
    }

    /**
     * 美团接口取消订单
     * @param Request $request
     * @return mixed
     */
    public function cancel(Request $request)
    {
        $order_id = $request->get('order_id', 0);
        $order = Order::query()->where('order_id', $order_id)->first();
        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order_id}]-开始");

        if (!$order) {
            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order_id}]-订单不存在");
            // \Log::info('[订单-美团外卖接口取消订单]-订单未找到', ['请求参数' => $request->all()]);
            return $this->error("订单不存在");
        }

        $ps = $order->ps;

        if ($order->status == 99) {
            // 已经是取消状态
            return $this->success();
        } elseif ($order->status == 80) {
            // 异常状态
            return $this->success();
        } elseif ($order->status == 70) {
            // 已经完成
            return $this->error("订单已经完成，不能取消");
        } elseif (in_array($order->status, [40, 50, 60])) {
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
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "（美团外卖）取消美团跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mt_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【美团】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消美团订单返回失败",
                        "id" => $order->id,
                        "ps" => "美团",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消美团订单返回失败", $logs);
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
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "（美团外卖）取消蜂鸟跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'fn_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【蜂鸟】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "蜂鸟",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消蜂鸟订单返回失败", $logs);
                }
            } elseif ($ps == 3) {
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
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

                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "（美团外卖）取消闪送跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $jian_money,
                                "type" => 2,
                                "before_money" => ($current_user->money + $order->money),
                                "after_money" => ($current_user->money + $order->money - $jian_money),
                                "description" => "取消闪送跑腿订单扣款：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                            ]);
                            // $current_user->increment('money', ($order->money - $jian_money));
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
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
                                "des" => "（美团外卖）取消【闪送】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "闪送",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消闪送订单返回失败", $logs);
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
                                "description" => "（美团外卖）取消美全达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mqd_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【美全达】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消美全达订单返回失败",
                        "id" => $order->id,
                        "ps" => "美全达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消美全达订单返回失败", $logs);
                }
            } elseif ($ps == 5) {
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
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
                                "description" => "（美团外卖）取消达达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【达达】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消美全达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消达达订单返回失败",
                        "id" => $order->id,
                        "ps" => "达达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消达达订单返回失败", $logs);
                }
            }
            return $this->success();
        } elseif (in_array($order->status, [20, 30])) {
            // 没有骑手接单，取消订单
            if (in_array($order->mt_status, [20, 30])) {
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
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）取消【美团】跑腿订单"
                    ]);
                }
            }
            if (in_array($order->fn_status, [20, 30])) {
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
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）取消【蜂鸟】跑腿订单"
                    ]);
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）取消【闪送】跑腿订单"
                    ]);
                }
            }
            if (in_array($order->mqd_status, [20, 30])) {
                $meiquanda = app("meiquanda");
                $result = $meiquanda->repealOrder($order->mqd_order_id);
                if ($result['code'] == 100) {
                    $order->status = 99;
                    $order->mqd_status = 99;
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）取消【美全达】跑腿订单"
                    ]);
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "（美团外卖）取消【达达】跑腿订单"
                    ]);
                }
            }
        } else {
            // 状态小于20，属于未发单，直接操作取消
            if ($order->status < 0) {
                \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                $order->status = -10;
            } else {
                $order->status = 99;
            }
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "（美团外卖）取消跑腿订单"
            ]);
            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order_id}]-未配送");
            return $this->success();
        }

        return $this->error("取消失败");
    }

    /**
     * 后台取消订单
     * @param Order $order
     * @return mixed
     */
    public function cancel2(Order $order)
    {
        \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-开始");
        $ps = $order->ps;

        if ($order->status == 99) {
            // 已经是取消状态
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-已经是取消状态");
            return $this->success();
        } elseif ($order->status == 80) {
            // 异常状态
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-异常状态");
            return $this->success();
        } elseif ($order->status == 70) {
            // 已经完成
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-订单已经完成，不能取消");
            return $this->error("订单已经完成，不能取消");
        } elseif (in_array($order->status, [40, 50, 60])) {
            \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-已有平台接单，订单状态：{$order->status}");
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
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "操作取消美团跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 将配送费返回
                            DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mt_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "操作取消【美团】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美团",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                    $logs = [
                        "des" => "【操作取消订单】取消美团订单返回失败",
                        "id" => $order->id,
                        "ps" => "美团",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("操作取消订单，取消美团订单返回失败", $logs);
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
                            // 用户余额日志
                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "操作取消蜂鸟跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'fn_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "操作取消【蜂鸟】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "蜂鸟",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                    $logs = [
                        "des" => "【操作取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "蜂鸟",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("操作取消订单，取消蜂鸟订单返回失败", $logs);
                }
            } elseif ($ps == 3) {
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    try {
                        DB::transaction(function () use ($order) {
                            // 计算扣款
                            $jian_money = 0;
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

                            $current_user = DB::table('users')->find($order->user_id);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $order->money,
                                "type" => 1,
                                "before_money" => $current_user->money,
                                "after_money" => ($current_user->money + $order->money),
                                "description" => "操作取消闪送跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            UserMoneyBalance::query()->create([
                                "user_id" => $order->user_id,
                                "money" => $jian_money,
                                "type" => 2,
                                "before_money" => ($current_user->money + $order->money),
                                "after_money" => ($current_user->money + $order->money - $jian_money),
                                "description" => "操作取消闪送跑腿订单扣款：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'ss_status' => 99,
                            ]);
                            // $current_user->increment('money', ($order->money - $jian_money));
                            DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                            \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
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
                                "des" => "操作取消【闪送】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【操作取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "闪送",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("操作取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-操作取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                    $logs = [
                        "des" => "【操作取消订单】取消蜂鸟订单返回失败",
                        "id" => $order->id,
                        "ps" => "闪送",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("操作取消订单，取消闪送订单返回失败", $logs);
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
                                "description" => "（美团外卖）取消美全达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'mqd_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【美全达】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "美全达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消美全达订单返回失败",
                        "id" => $order->id,
                        "ps" => "美全达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消美全达订单返回失败", $logs);
                }
            } elseif ($ps == 5) {
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
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
                                "description" => "（美团外卖）取消达达跑腿订单：" . $order->order_id,
                                "tid" => $order->id
                            ]);
                            // 更改订单信息
                            DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                'status' => 99,
                                'dd_status' => 99,
                            ]);
                            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户");
                            OrderLog::create([
                                "order_id" => $order->id,
                                "des" => "（美团外卖）取消【达达】跑腿订单"
                            ]);
                        });
                    } catch (\Exception $e) {
                        $message = [
                            $e->getCode(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getMessage()
                        ];
                        \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-将钱返回给用户失败", $message);
                        $logs = [
                            "des" => "【美团外卖接口取消订单】更改信息、将钱返回给用户失败",
                            "id" => $order->id,
                            "ps" => "达达",
                            "order_id" => $order->order_id
                        ];
                        $dd->sendMarkdownMsgArray("美团外卖接口取消订单将钱返回给用户失败", $logs);
                    }
                } else {
                    \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消达达订单返回失败", [$result]);
                    $logs = [
                        "des" => "【美团外卖接口取消订单】取消达达订单返回失败",
                        "id" => $order->id,
                        "ps" => "达达",
                        "order_id" => $order->order_id
                    ];
                    $dd->sendMarkdownMsgArray("美团外卖接口取消订单，取消达达订单返回失败", $logs);
                }
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
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "操作取消【美团】跑腿订单"
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
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "操作取消【蜂鸟】跑腿订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，蜂鸟成功");
                }
            }
            if (in_array($order->ss_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消闪送");
                $shansong = app("shansong");
                $result = $shansong->cancelOrder($order->ss_order_id);
                if ($result['status'] == 200) {
                    $order->status = 99;
                    $order->ss_status = 99;
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "操作取消【闪送】跑腿订单"
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
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "操作取消【美全达】跑腿订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，美全达成功");
                }
            }
            if (in_array($order->dd_status, [20, 30])) {
                \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，取消达达");
                $dada = app("dada");
                $result = $dada->orderCancel($order->order_id);
                if ($result['code'] == 0) {
                    $order->status = 99;
                    $order->dd_status = 99;
                    $order->save();
                    OrderLog::create([
                        "order_id" => $order->id,
                        "des" => "操作取消【达达】跑腿订单"
                    ]);
                    \Log::info("[跑腿订单-后台取消订单]-[订单号: {$order->order_id}]-没有骑手接单，取消订单，达达成功");
                }
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
            }
            $order->save();
            OrderLog::create([
                "order_id" => $order->id,
                "des" => "操作取消跑腿订单"
            ]);
            \Log::info("[跑腿订单-美团外卖接口取消订单]-[订单号: {$order->order_id}]-未配送");
            return $this->success();
        }

        return $this->error("取消失败");
        // $ps = $order->ps;
        // $shop = Shop::query()->find($order->shop_id);
        // \Log::info("[跑腿订单]-[后台取消订单]-[订单ID：{$order->id}]-[订单号：{$order->order_id}]");
        //
        // if ($order->status < 3) {
        //     \Log::info("订单未发送-不用取消", [$order->id, $order->order_id]);
        //     return $this->error("订单状态不正确");
        // }
        //
        // if ($order->status >= 70) {
        //     \Log::info("订单完成或取消-不用取消", [$order->id, $order->order_id]);
        //     return $this->error("订单状态不正确");
        // }
        //
        // if ($ps == 1) {
        //     $meituan = app("meituan");
        //
        //     $result = $meituan->delete([
        //         'delivery_id' => $order->delivery_id,
        //         'mt_peisong_id' => $order->peisong_id,
        //         'cancel_reason_id' => 399,
        //         'cancel_reason' => '其他原因',
        //     ]);
        //
        //     if ($result['code'] === 0 && ($order->status < 99)) {
        //         if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
        //             \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
        //             \Log::info('美团取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         } else {
        //             \Log::info('美团取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         }
        //         return $this->success([]);
        //     } else {
        //         \Log::info('美团取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         return $this->success([]);
        //     }
        // } elseif ($ps == 2) {
        //
        //     $fengniao = app("fengniao");
        //
        //     $result = $fengniao->cancelOrder([
        //         'partner_order_code' => $order->order_id,
        //         'order_cancel_reason_code' => 2,
        //         'order_cancel_code' => 9,
        //         'order_cancel_time' => time() * 1000,
        //     ]);
        //
        //     if ($result['code'] == 200 && ($order->status < 99)) {
        //         if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
        //             \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
        //             \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         } else {
        //             \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         }
        //         return $this->success([]);
        //     } else {
        //         \Log::info('蜂鸟取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         return $this->success([]);
        //     }
        // } elseif ($ps == 3) {
        //
        //     $shansong = app("shansong");
        //
        //     $result = $shansong->cancelOrder($order->peisong_id);
        //
        //     if ($result['status'] == 200 && ($order->status < 99)) {
        //         if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
        //             // 计算扣款
        //             $jian_money = 0;
        //             if (!empty($order->receive_at)) {
        //                 $jian_money = 2;
        //                 $jian = time() - strtotime($order->receive_at);
        //                 if ($jian >= 480) {
        //                     $jian_money = 5;
        //                 }
        //                 if (!empty($order->take_at)) {
        //                     $jian_money = 5;
        //                 }
        //             }
        //             // 返钱
        //             \DB::table('users')->where('id', $shop->user_id)->increment('money', ($order->money - $jian_money));
        //             if ($jian_money > 0) {
        //                 $jian_data = [
        //                     'order_id' => $order->id,
        //                     'money' => $jian_money,
        //                     'ps' => $order->ps
        //                 ];
        //                 OrderDeduction::query()->create($jian_data);
        //             }
        //             \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => ($order->money - $jian_money), 'jian_money' => $jian_money, 'order_money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         } else {
        //             \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         }
        //         return $this->success([]);
        //     } else {
        //         \Log::info('闪送取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
        //         return $this->success([]);
        //     }
        // } else {
        //     $order->status = 99;
        //     \Log::info('后台取消订单-未配送');
        //     $order->save();
        //     return $this->success();
        // }
        //
        // return $this->error("取消失败");
    }

    /**
     * 获取美团配送价格
     * @param Request $request
     * @param Shop $shop
     * @return mixed
     */
    public function money(Request $request, Shop $shop)
    {
        $lng = $request->get('lng', 0);
        $lat = $request->get('lat', 0);
        $weight = $request->get('weight', 0);
        $address = $request->get('address', "");
        $send = 0;


        $pt = 0;
        $distance = 0;
        $base = 0;
        $time = 0;
        $date_money = 0;
        $weight_money = 0;

        if (!$lng || !$lat || !$weight) {
            return $this->error('参数错误');
        }

        $order = new Order();
        $order->receiver_lng = $lng;
        $order->receiver_lat = $lat;
        $order->receiver_address = $address;

        // 美团
        if ($shop->shop_id && !$send) {

            $meituan = app("meituan");

            $res_mt = $meituan->check($shop, $order);

            if ($res_mt['code'] === 0) {
                $send = 1;
                $distance = distanceMoney(getJuli($shop, $lng, $lat));
                $base = baseMoney($shop->city_level ?: 9);
                $time = timeMoney();
                $date_money = dateMoney();
                $weight_money = weightMoney($weight);
                $pt = '美团';
            }
        }

        // 蜂鸟
        if ($shop->shop_id_fn && !$send) {

            $fengniao = app("fengniao");

            $res_fn = $fengniao->delivery($shop, $order);

            if ($res_fn['code'] == 200) {
                $send = 1;
                $distance = distanceMoneyFn(getJuli($shop, $lng, $lat));
                $base = baseMoneyFn($shop->city_level_fn ?: 'G');
                $time = timeMoneyFn();
                $date_money = 0;
                $weight_money = weightMoneyFn($weight);
                $pt = '蜂鸟';
            }
        }

        $total = $base + $time + $date_money + $distance + $weight_money;

        // 闪送
        if ($shop->shop_id_ss && !$send) {
            $order->order_id = time();
            $order->receiver_name = "客户";
            $order->receiver_phone = "15578995421";
            $order->goods_weight = $weight;

            $shansong = app("shansong");

            $res_ss = $shansong->orderCalculate($shop, $order);


            if ($res_ss['status'] === 200) {

                if (isset($res_ss['data']['feeInfoList']) && !empty($res_ss['data']['feeInfoList'])) {
                    foreach ($res_ss['data']['feeInfoList'] as $v) {
                        if ($v['type'] == 1) {
                            $base = $v['fee'] / 100 ?? 0;
                        }
                        if ($v['type'] == 2) {
                            $weight_money = $v['fee'] / 100 ?? 0;
                        }
                        if ($v['type'] == 7) {
                            $time = $v['fee'] / 100 ?? 0;
                        }
                    }
                    $total = ($res_ss['data']['totalAmount'] ?? 0) /100;
                }
                $pt = '闪送';
            }
        }

        return $this->success([
            'pt' => $pt,
            'base' => $base,
            'time' => $time,
            'date_money' => $date_money,
            'weight' => $weight_money,
            'distance' => $distance,
            'total' => $total
        ]);
    }

    public function returned(Request $request)
    {
        if (!$order = Order::query()->find( $request->get('order_id', 0))) {
            return $this->error("订单不存在");
        }

        $shansong = app("shansong");

        $res_ss = $shansong->confirmGoodsReturn($order->peisong_id);

        if ($res_ss['status'] === 200) {
            return $this->success("成功");
        }

        return $this->error($res_ss['msg'] ?? "成功");
    }

    /**
     * 通过订单获取门店详情（配送平台）
     * @param Request $request
     * @author zhangzhen
     * @data 2020/11/4 10:05 上午
     */
    public function getShopInfoByOrder(Request $request)
    {
        if (!$order = Order::query()->find($request->get("order_id", 0))) {
            return $this->error("订单不存在");
        }
        if (!$shop = Shop::query()->find($order->shop_id)) {
            return $this->error("门店不存在");
        }

        $result = [
            'mt' => $shop->shop_id ?? 0,
            'fn' => $shop->shop_id_fn ?? 0,
            'ss' => $shop->shop_id_ss ?? 0,
            'sf' => $shop->shop_id_sf ?? 0,
            'dd' => $shop->shop_id_dd ?? 0,
            'mqd' => $shop->shop_id_mqd ?? 0
        ];

        return $this->success($result);
    }

    public function tool(Order $order)
    {
        $order->tool = $order->tool === 8 ? 0 : 8;
        $order->save();

        return $this->success();
    }

    /**
     * 取消平台订单-----取消平台订单
     * 取消平台订单-----取消平台订单
     * 取消平台订单-----取消平台订单
     * 取消平台订单-----取消平台订单
     */

    /**
     * 获取今日订单统计
     * @author zhangzhen
     * @data 2021/6/25 8:21 下午
     */
    public function todayCount()
    {
        $data = [
            "all" => Order::query()->where('created_at', '>', date("Y-m-d"))->where("status", ">=", 0)->count(),
            "dai" => Order::query()->where('created_at', '>', date("Y-m-d"))->whereIn("status", [0,3,5,7,8,10])->count(),
            "jin" => Order::query()->where('created_at', '>', date("Y-m-d"))->whereIn("status", [20,30,40,50,60])->count(),
            "wan" => Order::query()->where('created_at', '>', date("Y-m-d"))->where("status", 70)->count(),
            "qu" => Order::query()->where('created_at', '>', date("Y-m-d"))->whereIn("status", [80,99])->count(),
        ];

        return $this->success($data);
    }
}

