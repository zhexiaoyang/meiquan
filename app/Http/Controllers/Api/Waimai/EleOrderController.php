<?php

namespace App\Http\Controllers\Api\Waimai;

use App\Events\OrderCreate;
use App\Http\Controllers\Controller;
use App\Jobs\CreateMtOrder;
use App\Jobs\PrescriptionFeeDeductionJob;
use App\Jobs\PrintWaiMaiOrder;
use App\Jobs\PushDeliveryOrder;
use App\Jobs\TakeoutMedicineStockSync;
use App\Jobs\VipOrderSettlement;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\Ele\Api\Tool;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderDeduction;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryTrack;
use App\Models\OrderLog;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\UserMoneyBalance;
use App\Models\VipProduct;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use App\Models\WmOrderReceive;
use App\Models\WmPrinter;
use App\Task\TakeoutOrderVoiceNoticeTask;
use App\Traits\LogTool;
use App\Traits\NoticeTool;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EleOrderController extends Controller
{
    use NoticeTool, LogTool;

    public $prefix_title = '[饿了么回调&###]';

    /**
     * 订单回调
     * @param Request $request
     * @return false|string
     * @author zhangzhen
     * @data 2021/6/5 8:41 下午
     */
    public function order(Request $request)
    {
        \Log::info("[饿了么回调]，全部参数", $request->all());
        $cmd = $request->get("cmd", "");

        if ($cmd === "order.status.push") {
            $body = json_decode($request->get("body"), true);
            if (is_array($body) && isset($body['status'])) {
                $status = $body['status'] ?? 0;
                $order_id = $body['order_id'];
                if ($status === 5) {
                    // $this->ding_error('饿了么创建订单了:' . $order_id);
                    $this->log_info("创建订单通知|订单号:{$order_id}|全部参数：", $request->all());
                    return $this->createOrder($order_id);
                } elseif ($status === 1) {
                    $this->log_info("确认订单通知|订单号:{$order_id}|全部参数：", $request->all());
                    return $this->confirmOrder($order_id);
                } elseif ($status === 10) {
                    $this->log_info("取消订单通知|订单号:{$order_id}|全部参数：", $request->all());
                    return $this->cancelOrder($order_id);
                } elseif ($status === 9) {
                    $this->log_info("完成订单通知|订单号:{$order_id}|全部参数：", $request->all());
                    return $this->finishOrder($order_id);
                }
            }
        } elseif ($cmd === 'order.create') {
            $body = json_decode($request->get("body"), true);
            if (is_array($body) && isset($body['order_id'])) {
                \Log::info("[饿了么]-[订单回调-创建订单]，订单号：{$body['order_id']}");
                return $this->confirmOrder($body['order_id']);
            }
        } elseif ($cmd === 'shop.bind.msg') {
            $body = json_decode($request->get("body"), true);
            if (!empty($body['shop_list'])) {
                return $this->shop_bind($body['shop_list']);
            } else {
                $this->log_info('饿了么绑定门店，列表为空，全部参数', $request->all());
                $this->ding_error('饿了么绑定门店，列表为空异常');
            }
        } elseif ($cmd === 'shop.unbind.msg') {
            $body = json_decode($request->get("body"), true);
            if (!empty($body['shop_list'])) {
                return $this->shop_unbind($body['shop_list']);
            } else {
                $this->log_info('饿了么绑定门店，列表为空，全部参数', $request->all());
                $this->ding_error('饿了么绑定门店，列表为空异常');
            }
        } elseif ($cmd === 'shop.msg.push') {
            $body = json_decode($request->get("body"), true);
            if (isset($body['baidu_shop_id']) && isset($body['msg_type'])) {
                return $this->shop_status($body['baidu_shop_id'], $body['msg_type']);
            } else {
                $this->ding_error('饿了么绑定门店，列表为空异常');
            }
        }

        \Log::info("[饿了么]-[订单回调]，错误请求");
        return $this->res("order.status.success");
    }

    /**
     * 门店绑定
     * @data 2022/5/8 10:41 上午
     */
    public function shop_status($ele_id, $type)
    {
        $this->prefix = str_replace('###', "饿了么门店状态修改|ID:{$ele_id}|type:{$type}", $this->prefix_title);
        // 查询门店个数
        $shops = Shop::where("ele", $ele_id)->get();
        if ($shop = $shops->first()) {
            if ($shops->count() > 1) {
                $this->ding_error("饿了么门店状态修改|ID:{$ele_id}，数量大于1");
                return '';
            }
            // 营业
            if ($type == 'shop_open') {
                $shop->ele_open = 3;
                $shop->save();
            } elseif ($type == 'shop_close') {
                $shop->ele_open = 1;
                $shop->save();
            }
        } else {
            $this->log_info("饿了么门店状态修改|ID:{$ele_id}，没有找到门店");
        }
        return $this->res("order.status.success");
    }

    /**
     * 门店绑定
     * @data 2022/5/8 10:41 上午
     */
    public function shop_bind($shops)
    {
        $this->prefix = str_replace('###', "门店绑定", $this->prefix_title);
        foreach ($shops as $shop) {
            $ele_id = $shop['shop_id'];
            // 查询门店个数
            $shops = Shop::where("ele", $ele_id)->get();
            if ($shop = $shops->first()) {
                if ($shops->count() > 1) {
                    $this->ding_error("饿了么绑定门店ID:{$ele_id}，数量大于1");
                    return '';
                }
                // 绑定
                if ($shop->waimai_ele) {
                    $this->ding_error("该门店已经绑定|shop_id:{$shop->id}:ele_id:{$ele_id}");
                    return $this->res("order.status.success");
                } else {
                    $shop->waimai_ele = $ele_id;
                    $shop->bind_ele_date = date("Y-m-d H:i:s");
                    $shop->save();
                    $this->log_info("饿了么绑定门店|门店ID:{$shop->id}|饿了么门店ID:{$ele_id}，绑定成功");
                }
            } else {
                $this->log_info("饿了么绑定门店ID:{$ele_id}，没有找到门店");
            }
        }
        return $this->res("order.status.success");
    }

    /**
     * 门店解绑
     * @data 2022/5/8 10:41 上午
     */
    public function shop_unbind($shops)
    {
        $this->prefix = str_replace('###', "门店解绑", $this->prefix_title);
        foreach ($shops as $shop) {
            $ele_id = $shop['baidu_shop_id'];
            // 查询门店个数
            $shops = Shop::where("ele", $ele_id)->get();
            if ($shop = $shops->first()) {
                if ($shops->count() > 1) {
                    $this->ding_error("饿了么解绑门店ID:{$ele_id}，数量大于1");
                    return '';
                }
                // 解绑
                if ($shop->waimai_ele) {
                    $shop->waimai_ele = '';
                    $shop->unbind_ele_date = date("Y-m-d H:i:s");
                    $shop->save();
                    $this->log_info("饿了么解绑门店|门店ID:{$shop->id}|饿了么门店ID:{$ele_id}，解绑成功");
                }
            }
        }
        return $this->res("order.status.success");
    }

    public function confirmOrder($order_id)
    {
        \Log::info("[饿了么]-[订单回调-确认订单]-订单号：{$order_id}");
        $ele = app("ele");
        $res = $ele->confirmOrder($order_id);
        \Log::info("[饿了么]-[订单回调-确认订单]-结果", [$res]);
        return $this->res("order.status.success");
    }

    public function finishOrder($order_id)
    {
        $this->prefix = str_replace('###', "完成订单|订单号:{$order_id}", $this->prefix_title);
        if ($order = WmOrder::select('id','status','is_prescription','is_vip','bill_date','finish_at')->where('order_id', $order_id)->first()) {
            if ($order->status < 18) {
                $bill_date = date("Y-m-d");
                // if (($order->ctime < strtotime($bill_date)) && (time() < strtotime(date("Y-m-d 09:00:00")))) {
                //     $bill_date = date("Y-m-d", time() - 86400);
                // }
                $order->bill_date = $bill_date;
                $order->status = 18;
                $order->finish_at = date("Y-m-d H:i:s");
                $order->save();
                $this->log_info("订单号：{$order_id}|操作完成");
                if ($order->is_vip) {
                    // 如果是VIP订单，触发JOB
                    dispatch(new VipOrderSettlement($order));
                }
            } else {
                $this->log_info("订单号：{$order_id}|操作失败|系统订单状态：{$order->status}");
            }
            if ($order->is_prescription) {
                PrescriptionFeeDeductionJob::dispatch($order->id);
            }
        } else {
            $this->log_info("订单号：{$order_id}|外卖订单不存在");
        }
        if ($order_pt = Order::where('order_id', $order_id)->first()) {
            if ($order_pt->status == 0) {
                $order_pt->status = 75;
                $order_pt->over_at = date("Y-m-d H:i:s");
                $order_pt->save();
                OrderLog::create([
                    "order_id" => $order_pt->id,
                    "des" => "「饿了么」完成订单"
                ]);
            }
        }
    }

    public function cancelOrder($order_id)
    {
        $this->prefix = str_replace('###', "饿了么接口取消订单&订单号:{$order_id}", $this->prefix_title);
        // 查找外卖订单-更改外卖订单状态
        if ($wmOrder = WmOrder::where('order_id', $order_id)->first()) {
            if ($wmOrder->status < 18) {
                $wmOrder->status = 30;
                $wmOrder->cancel_at = date("Y-m-d H:i:s");
                $wmOrder->save();
                $this->log_info("取消外卖订单-成功");
            } else {
                $this->log_info("外卖订单取消失败,外卖订单状态:{$wmOrder->status}");
            }
        } else {
            $this->log_info("外卖订单不存在");
        }
        if ($order = Order::where("order_id", $order_id)->first()) {
            // $order = Order::where('order_id', $order_id)->first();
            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-开始");

            if (!$order) {
                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-订单不存在");
                // \Log::info('[订单-饿了么接口取消订单]-订单未找到', ['请求参数' => $request->all()]);
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
                                // 计算扣款
                                $jian_money = 0;
                                if (!empty($order->take_at)) {
                                    $jian_money = $order->money;
                                }
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（饿了么）取消美团跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "（饿了么）取消美团跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                // 将配送费返回
                                // DB::table('users')->where('id', $order->user_id)->increment('money', $order->money_mt);
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mt_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【美团】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美团",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美团]-取消美团订单返回失败", [$result]);
                        $this->ding_error("取消已接单美团跑腿订单失败");
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
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（饿了么）取消蜂鸟跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                if ($jian_money > 0) {
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "（饿了么）取消蜂鸟跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                }
                                // 将配送费返回
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'fn_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【蜂鸟】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "蜂鸟",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:蜂鸟]-取消蜂鸟订单返回失败", [$result]);
                        $this->ding_error("取消已接单蜂鸟跑腿订单失败");
                    }
                } elseif ($ps == 3) {
                    if ($order->shipper_type_ss) {
                        $shansong = new ShanSongService(config('ps.shansongservice'));
                    } else {
                        $shansong = app("shansong");
                    }
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] == 200) {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 3, '饿了么');
                        try {
                            DB::transaction(function () use ($order) {
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
                                    $current_user = DB::table('users')->find($order->user_id);
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $order->money,
                                        "type" => 1,
                                        "before_money" => $current_user->money,
                                        "after_money" => ($current_user->money + $order->money),
                                        "description" => "（饿了么）取消闪送跑腿订单：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $jian_money,
                                        "type" => 2,
                                        "before_money" => ($current_user->money + $order->money),
                                        "after_money" => ($current_user->money + $order->money - $jian_money),
                                        "description" => "取消闪送跑腿订单扣款：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                    // $current_user->increment('money', ($order->money - $jian_money));
                                    DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户");
                                    if ($jian_money > 0) {
                                        $jian_data = [
                                            'order_id' => $order->id,
                                            'money' => $jian_money,
                                            'ps' => $order->ps
                                        ];
                                        OrderDeduction::create($jian_data);
                                    }
                                } else {
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-自主注册闪送，取消不扣款");
                                }
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'ss_status' => 99,
                                ]);
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【闪送】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "闪送",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:闪送]-取消闪送订单返回失败", [$result]);
                        $this->ding_error("取消已接单闪送跑腿订单失败");
                    }
                } elseif ($ps == 4) {
                    $fengniao = app("meiquanda");
                    $result = $fengniao->repealOrder($order->mqd_order_id);
                    if ($result['code'] == 100) {
                        try {
                            DB::transaction(function () use ($order) {
                                // 用户余额日志
                                $current_user = DB::table('users')->find($order->user_id);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（饿了么）取消美全达跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'mqd_status' => 99,
                                ]);
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【美全达】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "美全达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:美全达]-取消美全达订单返回失败", [$result]);
                        $this->ding_error("取消已接单美全达跑腿订单失败");
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
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 5, '饿了么');
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
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $order->money,
                                        "type" => 1,
                                        "before_money" => $current_user->money,
                                        "after_money" => ($current_user->money + $order->money),
                                        "description" => "[饿了么]取消[达达]订单：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                    if ($jian_money > 0) {
                                        UserMoneyBalance::create([
                                            "user_id" => $order->user_id,
                                            "money" => $jian_money,
                                            "type" => 2,
                                            "before_money" => ($current_user->money + $order->money),
                                            "after_money" => ($current_user->money + $order->money - $jian_money),
                                            "description" => "[饿了么]取消[达达]订单扣款：" . $order->order_id,
                                            "tid" => $order->id
                                        ]);
                                    }
                                    DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                    $this->log_info("取消已接单达达跑腿订单成功,将钱返回给用户成功,退款金额:{$order->money},扣款金额:{$jian_money}");
                                    if ($jian_money > 0) {
                                        $jian_data = [
                                            'order_id' => $order->id,
                                            'money' => $jian_money,
                                            'ps' => $order->ps
                                        ];
                                        OrderDeduction::create($jian_data);
                                    }
                                } else {
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-自主注册不扣款");
                                }
                                // 更改订单信息
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'dd_status' => 99,
                                    'cancel_at' => date("Y-m-d H:i:s")
                                ]);
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "[饿了么]取消[达达]订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            $this->log_info("取消已接单达达跑腿订单成功,将钱返回给用户失败,退款金额:{$order->money}");
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "达达",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消美全达订单返回失败", [$result]);
                        $this->ding_error("取消已接单达达跑腿订单失败");
                    }
                } elseif ($ps == 6) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] == 'ok') {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 6, '饿了么');
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
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $order->money,
                                    "type" => 1,
                                    "before_money" => $current_user->money,
                                    "after_money" => ($current_user->money + $order->money),
                                    "description" => "（饿了么）取消UU跑腿订单：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                UserMoneyBalance::create([
                                    "user_id" => $order->user_id,
                                    "money" => $jian_money,
                                    "type" => 2,
                                    "before_money" => ($current_user->money + $order->money),
                                    "after_money" => ($current_user->money + $order->money - $jian_money),
                                    "description" => "取消UU跑腿订单扣款：" . $order->order_id,
                                    "tid" => $order->id
                                ]);
                                DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                    'status' => 99,
                                    'uu_status' => 99,
                                    'cancel_at' => date("Y-m-d H:i:s")
                                ]);
                                DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户");
                                OrderLog::create([
                                    "order_id" => $order->id,
                                    "des" => "（饿了么）取消【UU跑腿】跑腿订单"
                                ]);
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:UU]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "UU",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:达达]-取消UU订单返回失败", [$result]);
                        $this->ding_error("取消已接单UU跑腿订单失败");
                    }
                } elseif ($ps == 7) {
                    if ($order->shipper_type_sf) {
                        $sf = app("shunfengservice");
                    } else {
                        $sf = app("shunfeng");
                    }
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] == 0) {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 7, '饿了么');
                        // // 顺丰跑腿运力
                        // $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                        // // 写入顺丰取消足迹
                        // if ($sf_delivery) {
                        //     try {
                        //         $sf_delivery->update([
                        //             'status' => 99,
                        //             'cancel_at' => date("Y-m-d H:i:s"),
                        //             'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //         ]);
                        //         OrderDeliveryTrack::firstOrCreate(
                        //             [
                        //                 'delivery_id' => $sf_delivery->id,
                        //                 'status' => 99,
                        //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //             ], [
                        //                 'order_id' => $sf_delivery->order_id,
                        //                 'wm_id' => $sf_delivery->wm_id,
                        //                 'delivery_id' => $sf_delivery->id,
                        //                 'status' => 99,
                        //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //             ]
                        //         );
                        //     } catch (\Exception $exception) {
                        //         Log::info("饿了么取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        //         $this->ding_error("饿了么取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                        //     }
                        // }
                        try {
                            DB::transaction(function () use ($order, $result) {
                                if ($order->shipper_type_sf == 0) {
                                    // 用户余额日志
                                    // 计算扣款
                                    $jian_money = isset($result['result']['deduction_detail']['deduction_fee']) ? ($result['result']['deduction_detail']['deduction_fee']/100) : 0;
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-扣款金额：{$jian_money}");
                                    // 当前用户
                                    $current_user = DB::table('users')->find($order->user_id);
                                    UserMoneyBalance::create([
                                        "user_id" => $order->user_id,
                                        "money" => $order->money,
                                        "type" => 1,
                                        "before_money" => $current_user->money,
                                        "after_money" => ($current_user->money + $order->money),
                                        "description" => "（饿了么）取消顺丰跑腿订单：" . $order->order_id,
                                        "tid" => $order->id
                                    ]);
                                    if ($jian_money > 0) {
                                        UserMoneyBalance::create([
                                            "user_id" => $order->user_id,
                                            "money" => $jian_money,
                                            "type" => 2,
                                            "before_money" => ($current_user->money + $order->money),
                                            "after_money" => ($current_user->money + $order->money - $jian_money),
                                            "description" => "（饿了么）取消顺丰跑腿订单扣款：" . $order->order_id,
                                            "tid" => $order->id
                                        ]);
                                    }
                                    DB::table('users')->where('id', $order->user_id)->increment('money', ($order->money - $jian_money));
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户");
                                    if ($jian_money > 0) {
                                        $jian_data = [
                                            'order_id' => $order->id,
                                            'money' => $jian_money,
                                            'ps' => $order->ps
                                        ];
                                        OrderDeduction::create($jian_data);
                                    }
                                    DB::table('orders')->where("id", $order->id)->whereIn("status", [40, 50, 60])->update([
                                        'status' => 99,
                                        'sf_status' => 99,
                                        'cancel_at' => date("Y-m-d H:i:s")
                                    ]);
                                    OrderLog::create([
                                        "order_id" => $order->id,
                                        "des" => "（饿了么）取消【顺丰跑腿】跑腿订单"
                                    ]);
                                } else {
                                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-自主注册顺丰，取消不扣款");
                                }
                            });
                        } catch (\Exception $e) {
                            $message = [
                                $e->getCode(),
                                $e->getFile(),
                                $e->getLine(),
                                $e->getMessage()
                            ];
                            \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-将钱返回给用户失败", $message);
                            $logs = [
                                "des" => "【饿了么接口取消订单】更改信息、将钱返回给用户失败",
                                "id" => $order->id,
                                "ps" => "顺丰",
                                "order_id" => $order->order_id
                            ];
                            $dd->sendMarkdownMsgArray("饿了么接口取消订单将钱返回给用户失败", $logs);
                        }
                    } else {
                        \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order->order_id}]-[ps:顺丰]-取消顺丰订单返回失败", [$result]);
                        $this->ding_error("取消已接单顺丰跑腿订单失败");
                    }
                }
                return $this->res("order.status.success");
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
                            "des" => "（饿了么）取消【美团】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消美团订单失败");
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
                            "des" => "（饿了么）取消【蜂鸟】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消蜂鸟订单失败");
                    }
                }
                if (in_array($order->ss_status, [20, 30])) {
                    if ($order->shipper_type_ss) {
                        $shansong = new ShanSongService(config('ps.shansongservice'));
                    } else {
                        $shansong = app("shansong");
                    }
                    $result = $shansong->cancelOrder($order->ss_order_id);
                    if ($result['status'] == 200) {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 3, '饿了么');
                        $order->status = 99;
                        $order->ss_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（饿了么）取消【闪送】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消闪送订单失败");
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
                            "des" => "（饿了么）取消【美全达】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消美全达订单失败");
                    }
                }
                if (in_array($order->dd_status, [20, 30])) {
                    if ($order->shipper_type_dd) {
                        $config = config('ps.dada');
                        $config['source_id'] = get_dada_source_by_shop($order->warehouse_id ?: $order->shop_id);
                        $dada = new DaDaService($config);
                    } else {
                        $dada = app("dada");
                    }
                    $result = $dada->orderCancel($order->order_id);
                    if ($result['code'] == 0) {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 5, '饿了么');
                        $order->status = 99;
                        $order->dd_status = 99;
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（饿了么）取消【达达】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消达达订单失败");
                    }
                }
                if (in_array($order->uu_status, [20, 30])) {
                    $uu = app("uu");
                    $result = $uu->cancelOrder($order);
                    if ($result['return_code'] == 'ok') {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 6, '饿了么');
                        $order->status = 99;
                        $order->uu_status = 99;
                        $order->cancel_at = date("Y-m-d H:i:s");
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（饿了么）取消【UU】跑腿订单"
                        ]);
                    } else {
                        $this->ding_error("取消UU订单失败");
                    }
                }
                if (in_array($order->sf_status, [20, 30])) {
                    if ($order->shipper_type_sf) {
                        $sf = app("shunfengservice");
                    } else {
                        $sf = app("shunfeng");
                    }
                    $result = $sf->cancelOrder($order);
                    if ($result['error_code'] == 0) {
                        // 跑腿运力取消
                        OrderDelivery::cancel_log($order->id, 7, '饿了么');
                        $order->status = 99;
                        $order->sf_status = 99;
                        $order->cancel_at = date("Y-m-d H:i:s");
                        $order->save();
                        OrderLog::create([
                            "order_id" => $order->id,
                            "des" => "（饿了么）取消【顺丰】跑腿订单"
                        ]);
                        // // 顺丰跑腿运力
                        // $sf_delivery = OrderDelivery::where('order_id', $order->id)->where('platform', 7)->where('status', '<=', 70)->orderByDesc('id')->first();
                        // // 写入顺丰取消足迹
                        // if ($sf_delivery) {
                        //     try {
                        //         $sf_delivery->update([
                        //             'status' => 99,
                        //             'cancel_at' => date("Y-m-d H:i:s"),
                        //             'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //         ]);
                        //         OrderDeliveryTrack::firstOrCreate(
                        //             [
                        //                 'delivery_id' => $sf_delivery->id,
                        //                 'status' => 99,
                        //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //             ], [
                        //                 'order_id' => $sf_delivery->order_id,
                        //                 'wm_id' => $sf_delivery->wm_id,
                        //                 'delivery_id' => $sf_delivery->id,
                        //                 'status' => 99,
                        //                 'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                        //             ]
                        //         );
                        //     } catch (\Exception $exception) {
                        //         Log::info("饿了么取消顺丰-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                        //         $this->ding_error("饿了么取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
                        //     }
                        // }
                    } else {
                        $this->ding_error("取消顺丰订单失败");
                    }
                }
                return $this->res("order.status.success");
            } else {
                // 状态小于20，属于未发单，直接操作取消
                if ($order->status < 0) {
                    \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-[订单状态：{$order->status}]-订单状态小于0");
                    $order->status = -10;
                } else {
                    $order->status = 99;
                }
                $order->save();
                OrderLog::create([
                    "order_id" => $order->id,
                    "des" => "（饿了么）取消跑腿订单"
                ]);
                \Log::info("[跑腿订单-饿了么接口取消订单]-[订单号: {$order_id}]-未配送");
                return $this->res("order.status.success");
            }
        }
    }

    /**
     * 创建订单
     * @param $order_id
     * @return false|mixed|string
     * @author zhangzhen
     * @data 2021/6/6 8:46 下午
     */
    public function createOrder($order_id)
    {
        $this->prefix = str_replace('###', "创建订单|订单号:{$order_id}", $this->prefix_title);
        $ele = app("ele");
        $order_request = $ele->orderInfo($order_id);
        if (!empty($order_request) && isset($order_request['body']['data']) && !empty($order_request['body']['data'])) {
            // 订单数组
            $order = $order_request['body']['data'];
            $this->log_info("订单全部信息", $order);
            // return $order;
            // 订单ID
            $order_id = $order['order']['order_id'];
            // 订单状态
            $status = $order['order']['status'];
            // 饿了么门店ID
            $ele_shop_id = $order['shop']['id'];
            /********************* 判断订单是否存在 *********************/
            if (Order::where("order_id", $order_id)->first()) {
                $this->log_info('订单已存在');
                return $this->res("order.status.success");
            }

            // 判断是否是接单状态
            if ($status !== 5) {
                $this->log_info("订单状态不是5，不能创建订单");
                $this->ding_error("订单状态不是5，不能创建订单");
                return $this->error("不是接单状态");
            }

            // 寻找门店
            if (!$shop = Shop::where("waimai_ele", $ele_shop_id)->first()) {
                $this->log_info("门店不存在，不能创建订单");
                // $this->ding_error("门店不存在，不能创建订单");
                return $this->res("order.status.success");
            }
            if ($shop->print_auto == 1) {
                $redis_key = 'print_order_' . $shop->account_id ?: $shop->user_id;
                Redis::incr($redis_key);
            }

            // 创建订单
            $order_wm = DB::transaction(function () use ($shop, $order_id, $order) {
                // 重量，商品列表里面有字段累加就行，但是数据中没个重量都是 1，好像有问题，先写成1
                $weight = 2;
                // 取货类型，枚举值：0-外卖到家，1-用户到店自提
                $pick_type = $order['order']['business_type'];
                // 是否处方药订单，枚举值： 0 不是 ，1 是
                $is_prescription = $order['order']['is_prescription'];
                // 订单类型（1 即时单，2 预约单）
                $order_type = $order['order']['send_immediately'];
                // 送达时间
                $delivery_time = 0;
                if ($order_type === 2) {
                    $delivery_time = $order['order']['latest_send_time'];
                }

                $operate_service_fee = ($shop->commission_ele * $order['order']['shop_fee'] / 100) / 100;
                $order_wm_data = [
                    'user_id' => $shop->user_id,
                    "shop_id" => $shop->id ?? 0,
                    "order_id" => $order_id,
                    "wm_order_id_view" => $order_id,
                    // 订单平台（1 美团外卖，2 饿了么，3 京东到家，4 美全达）
                    "platform" => 2,
                    // 订单来源（3 洁爱眼，4 民康，5 寝趣，6 闪购，7 餐饮）
                    // "from_type" => $platform,
                    "app_poi_code" => $order['shop']['baidu_shop_id'],
                    "wm_shop_name" => $order['shop']['name'],
                    'recipient_name' => empty($order['user']['name']) ? "无名客人" : $order['user']['name'],
                    'recipient_phone' => str_replace(',', '_', $order['user']['phone']),
                    'recipient_address' => $order['user']['address'],
                    // 'recipient_address_detail' => $order['user']['address'],
                    'longitude' => $order['user']['coord_amap']['longitude'],
                    'latitude' => $order['user']['coord_amap']['latitude'],
                    "shipping_fee" => $order['order']['send_fee'] / 100,
                    "total" => $order['order']['user_fee'] / 100,
                    "original_price" => $order['order']['total_fee'] / 100,
                    "package_bag_money_yuan" => $order['order']['merchant_total_fee'] / 100,
                    "service_fee" => $order['order']['commission'] / 100,
                    "logistics_fee" => $order['order']['send_fee'] / 100,
                    "online_payment" => $order['order']['user_fee'] / 100,
                    "poi_receive" => $order['order']['shop_fee'] / 100,
                    // "rebate_fee" => $poi_receive_detail_yuan['agreementCommissionRebateAmount'] ?? 0,
                    "caution" => $order['order']['remark'] ?: '',
                    "shipper_phone" => $order['order']['delivery_phone'] ?? "",
                    "status" => 4,
                    "estimate_arrival_time" => $order['order']['latest_send_time'] ?? 0,
                    "ctime" => $order['order']['create_time'],
                    "utime" => $order['order']['create_time'],
                    "delivery_time" => $delivery_time,
                    "pick_type" => $pick_type,
                    "day_seq" => $order['order']['order_index'] ?? 0,
                    "invoice_title" => $order['order']['invoice_title'] ?? '',
                    "taxpayer_id" => $order['order']['taxer_id'] ?? '',
                    "is_prescription" =>  $is_prescription,
                    // "is_favorites" => intval($data['is_favorites'] ?? 0),
                    // "is_poi_first_order" => intval($data['is_poi_first_order'] ?? 0),
                    // "logistics_code" => $logistics_code,
                    "logistics_code" => 0,
                    "is_vip" => $shop->vip_ele,
                    // "prescription_fee" => $is_prescription ? 1.5 : 0,
                    "prescription_fee" => $is_prescription ? $shop->prescription_cost_ele : 0,
                    "operate_service_rate" => $shop->commission_ele,
                    "operate_service_fee" => $operate_service_fee > 0 ? $operate_service_fee : 0,
                ];
                // 创建外卖订单
                $order_wm = WmOrder::create($order_wm_data);
                $this->log_info("-外卖订单创建成功，ID:{$order_wm->id}");
                // 商品信息
                $items = [];
                // VIP成本价
                $cost_money = 0;
                // 组合商品数组，计算成本价
                $products = $order['products'];
                if (!empty($products)) {
                    foreach ($products as $product_bag) {
                        foreach ($product_bag as $product) {
                            $quantity = $product['product_amount'] ?? 0;
                            $_tmp = [
                                'order_id' => $order_wm->id,
                                'mt_spu_id' => $product['baidu_product_id'] ?? '',
                                'app_food_code' => $product['custom_sku_id'] ?? '',
                                'food_name' => $product['product_name'] ?? '',
                                // 'unit' => $product['unit'] ?? '',
                                'upc' => $product['upc'] ?? '',
                                'quantity' => $quantity,
                                'price' => $product['product_price'] / 100,
                                'product_fee' => $product['product_fee'] / 100,
                                // 'spec' => $product['spec'] ?? '',
                                'vip_cost' => 0,
                                'total_weight' => $product['total_weight'],
                                'is_free_gift' => $product['is_free_gift'] == 1 ? 1 : 0,
                            ];

                            // if ($shop->vip_status && (strtotime($shop->vip_at) < strtotime('2022-11-25'))) {
                            //     $upc = $product['upc'] ?? '';
                            //     if ($upc) {
                            //         $cost = VipProduct::select('cost')->where(['upc' => $upc, 'shop_id' => $shop->id])->first();
                            //         if (isset($cost->cost)) {
                            //             $cost = $cost->cost;
                            //             if ($cost > 0) {
                            //                 $cost_money += ($cost * $quantity);
                            //                 $_tmp['vip_cost'] = $cost;
                            //                 // $cost_data[] = ['upc' => $product['upc'], 'cost' => $cost->cost];
                            //                 $this->log_info("-VIP订单成本价,upc:{$upc},价格:{$cost}");
                            //             } else {
                            //                 $this->log_info("-VIP订单成本价小于等于0,upc:{$upc},价格:{$cost}");
                            //             }
                            //         } else {
                            //             $this->log_info("-成本价不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            //         }
                            //     } else {
                            //         $this->log_info("-UPC不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            //     }
                            // } else {
                            //     $upc = $product['upc'] ?? '';
                            //     if ($upc) {
                            //         $cost = Medicine::select('guidance_price')->where(['upc' => $upc, 'shop_id' => $shop->id])->first();
                            //         if (isset($cost->guidance_price)) {
                            //             $cost = $cost->guidance_price;
                            //             if ($cost > 0) {
                            //                 $cost_money += ($cost * $quantity);
                            //                 $_tmp['vip_cost'] = $cost;
                            //                 // $cost_data[] = ['upc' => $product['upc'], 'cost' => $cost->cost];
                            //                 $this->log_info("-普通订单成本价,upc:{$upc},价格:{$cost}");
                            //             } else {
                            //                 $this->log_info("-普通订单成本价小于等于0,upc:{$upc},价格:{$cost}");
                            //             }
                            //         } else {
                            //             $this->log_info("-普通成本价不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            //         }
                            //     } else {
                            //         $this->log_info("-UPC不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            //     }
                            // }
                            // 成本价获取
                            $upc = $product['upc'] ?? '';
                            if ($upc) {
                                $cost = Medicine::select('guidance_price')->where(['upc' => $upc, 'shop_id' => $shop->id])->first();
                                if (isset($cost->guidance_price)) {
                                    $cost = $cost->guidance_price;
                                    if ($cost > 0) {
                                        $cost_money += ($cost * $quantity);
                                        $_tmp['vip_cost'] = $cost;
                                        // $cost_data[] = ['upc' => $product['upc'], 'cost' => $cost->cost];
                                        $this->log_info("-普通订单成本价,upc:{$upc},价格:{$cost}");
                                    } else {
                                        $this->log_info("-普通订单成本价小于等于0,upc:{$upc},价格:{$cost}");
                                    }
                                } else {
                                    $this->log_info("-普通成本价不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                                }
                            } else {
                                $this->log_info("-UPC不存在|门店ID：{$shop->id},门店名称：{$shop->shop_name},upc：{$upc}");
                            }
                            $items[] = $_tmp;
                            // 药品管理-同步药品库存到其它平台
                            if ($medicine = Medicine::where('shop_id', $shop->id)->where('upc', $upc)->first()) {
                                if ($medicine->ele_status === 1) {
                                    $this->log_info("药品管理同步逻辑-中台减库存,原库存:{$medicine->stock}");
                                    $medicine_stock = $medicine->stock - $quantity;
                                    if ($medicine_stock < 0) {
                                        $medicine_stock = 0;
                                    }
                                    $medicine->update(['stock' => $medicine_stock]);
                                    $this->log_info("药品管理同步逻辑-中台减库存,现库存:{$medicine_stock}");
                                    if ($shop->waimai_mt && ($medicine->mt_status === 1)) {
                                        $this->log_info("药品管理同步逻辑-将库存同步到美团");
                                        TakeoutMedicineStockSync::dispatch(1, $shop->waimai_mt, $upc, $medicine_stock, $shop->meituan_bind_platform)->onQueue('medicine');
                                    }
                                }
                            }
                        }
                    }
                }
                if (!empty($items)) {
                    // if ($shop->vip_status) {
                    $this->log_info("-成本价计算：{$cost_money}|shop_id：{$shop->id},order_id：{$order_wm->order_id}");
                    $order_wm->vip_cost = $cost_money;
                    // $order->vip_cost_info = json_encode($cost_data, JSON_UNESCAPED_UNICODE);
                    $order_wm->save();
                    $this->log_info("-外卖订单,VIP商家成本价更新成功");
                    // }
                    WmOrderItem::insert($items);
                    $this->log_info("-外卖订单「商品」保存成功");
                }
                // 商家活动信息
                $receives = [];
                $discounts = $order['discount'];
                if (!empty($discounts)) {
                    foreach ($discounts as $discount) {
                        if ($discount['shop_rate']) {
                            $receives[] = [
                                'type' => 2,
                                'order_id' => $order_wm->id,
                                'comment' => $discount['desc'],
                                'fee_desc' => '活动款',
                                'money' => $discount['shop_rate'] / 100,
                            ];
                        }
                        if ($discount['baidu_rate']) {
                            $receives[] = [
                                'type' => 1,
                                'order_id' => $order_wm->id,
                                'comment' => $discount['desc'],
                                'fee_desc' => '活动款',
                                'money' => $discount['baidu_rate'] / 100,
                            ];
                        }
                    }
                }
                if (!empty($receives)) {
                    $this->log_info("-外卖订单「活动」保存成功");
                    WmOrderReceive::insert($receives);
                }
                // 商家活动信息
                /********************* 创建跑腿订单数组 *********************/
                // $pick_type = $data['pick_type'] ?? 0;
                // $weight = $data['total_weight'] ?? 0;
                // 创建订单数组
                $order_pt_data = [
                    'delivery_id' => $order_id,
                    'user_id' => $shop->user_id,
                    'order_id' => $order_id,
                    'shop_id' => $shop->id,
                    'wm_poi_name' => $order['shop']['name'],
                    'delivery_service_code' => "4011",
                    'receiver_name' => empty($order['user']['name']) ? "无名客人" : $order['user']['name'],
                    'receiver_address' => $order['user']['address'],
                    'receiver_phone' => str_replace(',', '_', $order['user']['phone']),
                    'receiver_lng' => $order['user']['coord_amap']['longitude'],
                    'receiver_lat' => $order['user']['coord_amap']['latitude'],
                    "caution" => $order['order']['remark'] ?: '',
                    'coordinate_type' => 0,
                    'goods_value' => $order['order']['total_fee'] / 100,
                    'goods_weight' => $weight,
                    'day_seq' => $order['order']['order_index'],
                    'platform' => 2,
                    // 订单来源（3 洁爱眼，4 民康，5 寝趣，6 闪购，7 餐饮）
                    'type' => 21,
                    'status' => 0,
                    'order_type' => 0,
                    "estimate_arrival_time" => $order['order']['latest_send_time'] ?? 0,
                    "poi_receive" => $order['order']['shop_fee'] / 100,
                ];
                // 判断是否预约单
                if ($delivery_time > 0) {
                    $this->log_info("-跑腿订单,预约单,送达时间:" . date("Y-m-d H:i:s", $delivery_time));
                    // [预约单]待发送
                    // $order_pt_data['status'] = 3;
                    $order_pt_data['order_type'] = 1;
                    $order_pt_data['expected_pickup_time'] = $delivery_time - 3600;
                    $order_pt_data['expected_delivery_time'] = $delivery_time;
                }
                // 判断是否自动发单
                // if (!$shop->mt_shop_id) {
                //     $order_pt_data['status'] = 7;
                // }
                // 创建跑腿订单
                $order_pt_data['wm_id'] = $order_wm->id;
                $order_pt = Order::create($order_pt_data);
                $this->log_info("-跑腿订单创建成功，ID:{$order_pt->id}");
                OrderLog::create([
                    "order_id" => $order_pt->id,
                    "des" => "「饿了么」创建订单"
                ]);
                // 获取发单设置
                $setting = OrderSetting::where('shop_id', $shop->id)->first();
                // 判断是否发单
                $this->log_info("-开始派单");
                if ($shop->ele_shop_id) {
                    if ($pick_type == 0) {
                        if ($order_pt->order_type) {
                            $this->log_info("-预约单");
                            $qu = 3000;
                            if ($order_pt->distance <= 2 && $order_pt->distance > 0) {
                                $qu = 2400;
                            }
                            $order_pt->status = 3;
                            $order_pt->expected_send_time = $order_pt->expected_delivery_time - $qu;
                            $order_pt->save();
                            dispatch(new PushDeliveryOrder($order_pt->id, ($order_pt->expected_delivery_time - time() - $qu)));
                            $this->log_info("-预约单派单成功，{$qu}秒后发单");
                        } else {
                            $order_pt->send_at = date("Y-m-d H:i:s");
                            $order_pt->status = 8;
                            $order_pt->save();
                            $delay = $setting->delay_send ?? 0;
                            $delay = $delay > 60 ? $delay : config("ps.order_delay_ttl");
                            dispatch(new CreateMtOrder($order_pt, $delay));
                            $this->log_info("-派单成功，{$delay}秒后发单");
                        }
                    } else {
                        // 到店自取 ？？？ ， 更改状态，不在新订单列表里面显示
                        $this->log_info('-到店自取，不发单');
                    }
                } else {
                    // $this->ding_error("饿了么未开通自动派单,shop_id:{$shop->id},shop_ele_id:{$shop->ele_shop_id},order_id:{$order_id}");
                    $this->log_info("饿了么未开通自动派单,shop_id:{$shop->id},shop_ele_id:{$shop->ele_shop_id},order_id:{$order_id}");
                    // $this->log_info('-未开通自动派单');
                }
                // 打印订单
                if ($print = WmPrinter::where('shop_id', $shop->id)->first()) {
                    $this->log_info('-打印订单，触发任务');
                    dispatch(new PrintWaiMaiOrder($order_wm->id, $print));
                }
                // 转仓库打印
                if ($setting) {
                    if ($setting->warehouse && $setting->warehouse_time && $setting->warehouse_print) {
                        $this->log_info("-转单打印[setting：{$setting->id}", [$setting]);
                        $time_data = explode('-', $setting->warehouse_time);
                        $this->log_info("-转单打印-[time_data", [$time_data]);
                        if (!empty($time_data) && (count($time_data) === 2)) {
                            if (in_time_status($time_data[0], $time_data[1])) {
                                $this->log_info("-转单打印-[仓库ID：{$setting->warehouse}");
                                if ($print = WmPrinter::where('shop_id', $setting->warehouse)->first()) {
                                    $this->log_info("-转单打印-[订单ID：{$order_wm->id}，订单号：{$order_wm->order_id}，门店ID：{$order_wm->shop_id}，仓库ID：{$setting->warehouse}]");
                                    dispatch(new PrintWaiMaiOrder($order_wm->id, $print));
                                }
                            }
                        }
                    }
                }
                // 推送ERP
                return $order_wm;
            });
            if ($order_wm->is_prescription) {
                event(new OrderCreate($order_wm));
                \Log::info("饿了么处方单获取处方信息{$order_wm->order_id}");
            }
            if ($shop) {
                // 订单类型（1 即时单，2 预约单）
                $order_type = $order['order']['send_immediately'];
                // 送达时间
                $delivery_time = 0;
                if ($order_type === 2) {
                    $delivery_time = $order['order']['latest_send_time'];
                }
                if ($delivery_time > 0) {
                    Task::deliver(new TakeoutOrderVoiceNoticeTask(2, $shop->account_id ?: $shop->user_id), true);
                } else {
                    Task::deliver(new TakeoutOrderVoiceNoticeTask(1, $shop->account_id ?: $shop->user_id), true);
                }
            }
        }
        return $this->res("order.status.success");
    }

    public function auth(Request $request)
    {
        \Log::info("[饿了么]-[授权回调]，全部参数", $request->all());
    }

    public function res($cmd)
    {
        $data = [
            'body' => json_encode([
                'errno' => 0,
                'error' => 'success'
            ]),
            'cmd' => $cmd,
            'source' => config("ps.ele.app_key"),
            'ticket' => Tool::ticket(),
            'timestamp' => time(),
            'version' => 3
        ];

        $data['sign'] = Tool::getSign($data, config("ele.secret"));

        return json_encode($data);
    }
}
