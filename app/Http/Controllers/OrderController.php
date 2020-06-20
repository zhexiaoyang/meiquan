<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtOrder;
use App\Models\MoneyLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;

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
        $search_key = $request->get('search_key', '');
        $status = $request->get('status');
        $query = Order::with(['shop' => function($query) {
            $query->select('id', 'shop_id', 'shop_name');
        }])->select('id','shop_id','order_id','peisong_id','receiver_name','receiver_phone','money','failed','ps','receiver_lng','receiver_lat','status','created_at');

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
                if (in_array($order->status, [20 ,30 ,40 ,50 ,60])) {
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
    // public function store(Request $request, Order $order)
    // {
    //     $shop_id = $request->get('shop_id', 0);
    //     if (!$shop = Shop::query()->find($shop_id)) {
    //         $shop = Shop::query()->where('shop_id', $shop_id)->first();
    //     }
    //     if (!$shop) {
    //         return $this->error('门店不存在');
    //     }
    //     $order->fill($request->all());
    //     $order->shop_id = $shop->shop_id;
    //     if ($order->save()) {
    //         dispatch(new CreateMtOrder($order));
    //         return $this->success([]);
    //     }
    //     return $this->error("创建失败");
    // }

    public function store(Request $request, Order $order)
    {
        // 状态（-3取消发送，-2等待发送，-1:发送失败，0：未发送，3：余额不足，5：暂无运力，10：待接单，20：已接单，30：已取货，50：已送达，99：已取消）

        $shop_id = $request->get('shop_id', 0);
        if (!$shop = Shop::query()->find($shop_id)) {
            $shop = Shop::query()->where('shop_id', $shop_id)->first();
        }
        if (!$shop) {
            return $this->error('门店不存在');
        }
        $order->fill($request->all());
        $order->shop_id = $shop->id;
        // 订单未发送状态
        $order->status = 0;

        if ($order->save()) {

            dispatch(new CreateMtOrder($order));

            // $user = User::query()->find($shop->user_id);

            // if ($user->money > $order->money && User::query()->where('id', $user->id)->where('money', '>', $order->money)->update(['money' => $user->money - $order->money])) {
            //     MoneyLog::query()->create([
            //        'order_id' => $order->id,
            //        'amount' => $order->money,
            //     ]);
            //     dispatch(new CreateMtOrder($order));
            //     $user = User::query()->find($shop->user_id);
            //     if ($user->money < 20) {

                    // dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));

                    // try {
                    //     app('easysms')->send($user->phone, [
                    //         'template' => 'SMS_186380293',
                    //         'data' => [
                    //             'name' => $user->phone ?? '',
                    //             'number' => 20
                    //         ],
                    //     ]);
                    // } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                    //     $message = $exception->getException('aliyun')->getMessage();
                    //     \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                    // }
                // }
            // } else {

                // dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 20]));

                // try {
                //     app('easysms')->send($user->phone, [
                //         'template' => 'SMS_186380293',
                //         'data' => [
                //             'name' => $user->phone ?? '',
                //             'number' => 20
                //         ],
                //     ]);
                // } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                //     $message = $exception->getException('aliyun')->getMessage();
                //     \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                // }
            // }
            return $this->success([]);
        }
        return $this->error("创建失败");
    }

    /**
     * 重新发送订单
     * @param Order $order
     * @return mixed
     */
    public function send( Order $order)
    {
        if (!$shop = Shop::query()->where(['status' => 40, 'id' => $order->shop_id])->first()) {
            return $this->error("该门店不能发单");
        }

        // $time = timeMoney();
        // $date_money = dateMoney();
        //
        // $order->time_money = $time;
        // $order->date_money = $date_money;
        // $order->money = $order->base_money + $time + $date_money + $order->distance_money + $order->weight_money;
        // $order->save();

        // \Log::info('message', [$order->money]);

        dispatch(new CreateMtOrder($order));
        //
        // $user = User::query()->find($shop->user_id);
        //
        // if ($user->money > $order->money && User::query()->where('id', $user->id)->where('money', '>', $order->money)->update(['money' => $user->money - $order->money])) {
        //     MoneyLog::query()->create([
        //         'order_id' => $order->id,
        //         'amount' => $order->money,
        //     ]);
        //     dispatch(new CreateMtOrder($order));
        //     $user = User::query()->find($shop->user_id);
        //     if ($user->money < 20) {
        //         try {
        //             app('easysms')->send($user->phone, [
        //                 'template' => 'SMS_186380293',
        //                 'data' => [
        //                     'name' => $user->phone ?? '',
        //                     'number' => 20
        //                 ],
        //             ]);
        //         } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
        //             $message = $exception->getException('aliyun')->getMessage();
        //             \Log::info('余额不足发送短信失败', [$user->phone, $message]);
        //         }
        //     }
        //     return $this->success([]);
        // } else {
        //     try {
        //         app('easysms')->send($user->phone, [
        //             'template' => 'SMS_186380293',
        //             'data' => [
        //                 'name' => $user->phone ?? '',
        //                 'number' => 20
        //             ],
        //         ]);
        //     } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
        //         $message = $exception->getException('aliyun')->getMessage();
        //         \Log::info('余额不足发送短信失败', [$user->phone, $message]);
        //     }
        // }
        // return $this->error("余额不足，请充值后再发送");
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

    public function sync(Request $request)
    {
        $type = intval($request->get('type', 0));
        $order_id = $request->get('order_id', 0);

        if (!$type || !in_array($type, [1,2,3]) || !$order_id) {
            return $this->error('参数错误');
        }

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif($type === 2) {
            $meituan = app("mrx");
        } else {
            $meituan = app("jay");
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

        \Log::info('同步订单参数', ['type' => $type, 'order_id' => $order_id]);

        if (!$type || !in_array($type, [1,2,3]) || !$order_id) {
            return $this->error('参数错误');
        }

        if ($type === 1) {
            $meituan = app("yaojite");
        } elseif($type === 2) {
            $meituan = app("mrx");
        } else {
            $meituan = app("jay");
        }

        $res = $meituan->getOrderDetail(['order_id' => $order_id]);

        \Log::info('获取订单信息', [$res]);

        if (!empty($res) && is_array($res['data']) && !empty($res['data'])) {
            $data = $res['data'];
            if (Order::where('order_id', $data['wm_order_id_view'])->first()) {
                return $this->error('订单已存在');
            }

            $shop_id = isset($data['app_poi_code']) ? $data['app_poi_code'] : 0;

            if (!$shop = Shop::where('mt_shop_id', $shop_id)->first()) {
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
                'shop_id' => $shop->id,
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
                if ($status === 0) {

                    dispatch(new CreateMtOrder($order));

                    // $user = User::query()->find($shop->user_id);
                    //
                    // if ($user->money > $order->money && User::query()->where('id', $user->id)->where('money', '>', $order->money)->update(['money' => $user->money - $order->money])) {
                    //     MoneyLog::query()->create([
                    //         'order_id' => $order->id,
                    //         'amount' => $order->money,
                    //     ]);
                    //     dispatch(new CreateMtOrder($order));
                    //     $user = User::query()->find($shop->user_id);
                    //     if ($user->money < 20) {
                    //         try {
                    //             app('easysms')->send($user->phone, [
                    //                 'template' => 'SMS_186380293',
                    //                 'data' => [
                    //                     'name' => $user->phone ?? '',
                    //                     'number' => 20
                    //                 ],
                    //             ]);
                    //         } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                    //             $message = $exception->getException('aliyun')->getMessage();
                    //             \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                    //         }
                    //     }
                    // } else {
                    //     try {
                    //         app('easysms')->send($user->phone, [
                    //             'template' => 'SMS_186380293',
                    //             'data' => [
                    //                 'name' => $user->phone ?? '',
                    //                 'number' => 20
                    //             ],
                    //         ]);
                    //     } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                    //         $message = $exception->getException('aliyun')->getMessage();
                    //         \Log::info('余额不足发送短信失败', [$user->phone, $message]);
                    //     }
                    // }
                }
            }
            return $this->success([]);
        }
        return $this->error('未获取到订单');
    }

    /**
     * 接口取消订单
     * @param Request $request
     * @return mixed
     */
    public function cancel(Request $request)
    {
        $order = Order::query()->where('order_id', $request->get('order_id', 0))->first();

        if (!$order) {
            \Log::info('接口取消订单-订单未找到', ['请求参数' => $request->all()]);
        }

        \Log::info('接口取消订单-信息', ['请求参数' => $request->all(), '订单信息' => $order->toArray()]);

        $ps = $order->ps;
        $shop = Shop::query()->find($order->shop_id);

        if ($ps == 1) {
            $meituan = app("meituan");

            $result = $meituan->delete([
                'delivery_id' => $order->delivery_id,
                'mt_peisong_id' => $order->peisong_id,
                'cancel_reason_id' => 399,
                'cancel_reason' => '其他原因',
            ]);

            if ($result['code'] === 0 && ($order->status < 99)) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('美团取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('美团取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('美团取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
        } elseif ($ps == 2) {

            $fengniao = app("fengniao");

            $result = $fengniao->cancelOrder([
                'partner_order_code' => $order->order_id,
                'order_cancel_reason_code' => 2,
                'order_cancel_code' => 9,
                'order_cancel_time' => time() * 1000,
            ]);

            if ($result['code'] == 200 && ($order->status < 99)) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('蜂鸟取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
        } elseif ($ps == 3 && ($order->status < 99)) {

            $shansong = app("shansong");

            $result = $shansong->cancelOrder($order->peisong_id);

            if ($result['status'] == 200) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('闪送取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
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
        $ps = $order->ps;
        $shop = Shop::query()->find($order->shop_id);

        if ($ps == 1) {
            $meituan = app("meituan");

            $result = $meituan->delete([
                'delivery_id' => $order->delivery_id,
                'mt_peisong_id' => $order->peisong_id,
                'cancel_reason_id' => 399,
                'cancel_reason' => '其他原因',
            ]);

            if ($result['code'] === 0 && ($order->status < 99)) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('美团取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('美团取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('美团取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
        } elseif ($ps == 2) {

            $fengniao = app("fengniao");

            $result = $fengniao->cancelOrder([
                'partner_order_code' => $order->order_id,
                'order_cancel_reason_code' => 2,
                'order_cancel_code' => 9,
                'order_cancel_time' => time() * 1000,
            ]);

            if ($result['code'] == 200 && ($order->status < 99)) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('蜂鸟取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
        } elseif ($ps == 3) {

            $shansong = app("shansong");

            $result = $shansong->cancelOrder($order->peisong_id);

            if ($result['status'] == 200 && ($order->status < 99)) {
                if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                    \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                    \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                } else {
                    \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                }
                return $this->success([]);
            } else {
                \Log::info('闪送取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                return $this->success([]);
            }
        }

        return $this->error("取消失败");
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
                $distance = distanceMoney($shop, $lng, $lat);
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
                $distance = distanceMoney($shop, $lng, $lat);
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
}

