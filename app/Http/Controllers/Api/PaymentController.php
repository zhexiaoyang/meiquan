<?php

namespace App\Http\Controllers\Api;

use App\Jobs\StoreRestJob;
use App\Models\ContractOrder;
use App\Models\Deposit;
use App\Models\Shop;
use App\Models\ShopRestLog;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use App\Models\UserOperateBalance;
use Illuminate\Http\Request;
use Yansongda\Pay\Pay;
use DB;

class PaymentController
{
    public function wechatNotify(Request $request)
    {
        // 校验回调参数是否正确
        $data  = Pay::wechat(config("pay.wechat"))->verify($request->getContent());
        \Log::info("wechatNotify微信支付回调全部参数", [$data]);
        // 找到对应的订单
        $order = Deposit::where('no', $data->out_trade_no)->where("status", 0)->first();

        // 订单不存在
        if (!$order) {
            return $this->wechat();
        }

        // 订单已支付
        if ($order->status == 1) {
            return $this->wechat();
        }

        $status = DB::transaction(function () use ($data, $order) {

            // 将订单标记为已支付
            \Log::info("将订单标记为已支付开始");
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 2,
                'status'        => 1,
                'pay_no'        => $data->transaction_id,
                'amount'        => $data->total_fee / 100,
            ]);
            \Log::info("将订单标记为已支付结束");
            $user = User::find($order->user_id);

            if ($order->type === 3) {
                \Log::info("运营余额充值");
                \Log::info("增加运营余额");
                DB::table('users')->where("id", $order->user_id)->increment('operate_money', $order->amount);
                \Log::info("记录运营余额日志");
                $logs = new UserOperateBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->operate_money,
                    "after_money" => ($user->operate_money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            } else if ($order->type === 2) {
                \Log::info("冻结余额充值");
                \Log::info("增加冻结余额");
                DB::table('users')->where("id", $order->user_id)->increment('frozen_money', $order->amount);
                \Log::info("记录冻结余额日志");
                $logs = new UserFrozenBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->frozen_money,
                    "after_money" => ($user->frozen_money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            } else {
                \Log::info("余额充值");
                DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);
                \Log::info("余额充值结束");
                \Log::info("记录余额日志");
                UserMoneyBalance::create([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->money,
                    "after_money" => ($user->money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                \Log::info("日志保存结束");
            }


            // try {
            //     $user = DB::table('users')->where('id', $order->user_id)->first();
            //     app('easysms')->send('13843209606', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '微信',
            //             'number' => $data->total_fee / 100
            //         ],
            //     ]);
            //     app('easysms')->send('18611683889', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '微信',
            //             'number' => $data->total_fee / 100
            //         ],
            //     ]);
            // } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            //     $message = $exception->getException('aliyun')->getMessage();
            //     \Log::info('充值通知短信发送失败', [$message]);
            // }

            return true;

        });

        if ($status) {

            return $this->wechat();
        }

        return '';
    }
    public function wechatNotify2(Request $request)
    {
        // 校验回调参数是否正确
        $data  = Pay::wechat(config("pay.wechat_supplier"))->verify($request->getContent());
        \Log::info("wechatNotify2微信支付回调全部参数", [$data]);
        // 找到对应的订单
        $order = Deposit::where('no', $data->out_trade_no)->where("status", 0)->first();

        // 订单不存在
        if (!$order) {
            return $this->wechat();
        }

        // 订单已支付
        if ($order->status == 1) {
            return $this->wechat();
        }

        $status = DB::transaction(function () use ($data, $order) {

            // 将订单标记为已支付
            \Log::info("将订单标记为已支付开始");
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 2,
                'status'        => 1,
                'pay_no'        => $data->transaction_id,
                'amount'        => $data->total_fee / 100,
            ]);
            \Log::info("将订单标记为已支付结束");
            $user = User::find($order->user_id);

            if ($order->type === 3) {
                \Log::info("运营余额充值");
                \Log::info("增加运营余额");
                DB::table('users')->where("id", $order->user_id)->increment('operate_money', $order->amount);
                \Log::info("记录运营余额日志");
                $logs = new UserOperateBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->operate_money,
                    "after_money" => ($user->operate_money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            } else if ($order->type === 2) {
                \Log::info("冻结余额充值");
                \Log::info("增加冻结余额");
                DB::table('users')->where("id", $order->user_id)->increment('frozen_money', $order->amount);
                \Log::info("记录冻结余额日志");
                $logs = new UserFrozenBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->frozen_money,
                    "after_money" => ($user->frozen_money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            } else {
                \Log::info("余额充值");
                DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);
                \Log::info("余额充值结束");
                \Log::info("记录余额日志");
                UserMoneyBalance::create([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->money,
                    "after_money" => ($user->money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                \Log::info("日志保存结束");
            }


            // try {
            //     $user = DB::table('users')->where('id', $order->user_id)->first();
            //     app('easysms')->send('13843209606', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '微信',
            //             'number' => $data->total_fee / 100
            //         ],
            //     ]);
            //     app('easysms')->send('18611683889', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '微信',
            //             'number' => $data->total_fee / 100
            //         ],
            //     ]);
            // } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            //     $message = $exception->getException('aliyun')->getMessage();
            //     \Log::info('充值通知短信发送失败', [$message]);
            // }

            return true;

        });

        if ($status) {

            return $this->wechat();
        }

        return '';
    }

    /**
     * 微信运营充值回调
     * @data 2021/11/12 8:59 上午
     */
    public function wechatNotifyOperate(Request $request)
    {
        // 校验回调参数是否正确
        $data  = Pay::wechat(config("pay.wechat_operate_money"))->verify($request->getContent());
        \Log::info("微信运营充值回调全部参数", is_array($data) ? $data : [$data]);
        // 找到对应的订单
        $order = Deposit::where('no', $data->out_trade_no)->where("status", 0)->first();

        // 订单不存在
        if (!$order) {
            return $this->wechat();
        }

        // 订单已支付
        if ($order->status == 1) {
            return $this->wechat();
        }

        $status = DB::transaction(function () use ($data, $order) {
            // 将订单标记为已支付
            \Log::info("将订单标记为已支付开始");
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 2,
                'status'        => 1,
                'pay_no'        => $data->transaction_id,
                'amount'        => $data->total_fee / 100,
            ]);
            \Log::info("将订单标记为已支付结束");
            $user = User::find($order->user_id);
            if ($order->type === 3) {
                \Log::info("运营余额充值-增加运营余额");
                DB::table('users')->where("id", $order->user_id)->increment('operate_money', $order->amount);
                \Log::info("记录运营余额日志");
                $logs = new UserOperateBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->operate_money,
                    "after_money" => ($user->operate_money * 100 + $order->amount * 100) / 100,
                    "description" => "微信充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            }
            return true;
        });

        $user = DB::table('users')->find($order->user_id);
        if ($user && $user->operate_money >= config('ps.sms_operate_remind.max')) {
            DB::table('send_sms_logs')->where('phone', $user->phone)->where('type', 2)->limit(1)->delete();
        }
        if ($user && $user->operate_money > 0) {
            $shops = Shop::select('id', 'user_id', 'yunying_status')->where('user_id', $user->id)->get();
            if (!empty($shops)) {
                $date = date("Y-m-d H:i:s", time() - 86400);
                foreach ($shops as $shop) {
                    if ($shop->yunying_status && ShopRestLog::where('shop_id', $shop->id)->where('created_at', '>', $date)->count()) {
                        StoreRestJob::dispatch($shop->id, 2);
                    }
                }
            }
        }

        if ($status) {

            return $this->wechat();
        }

        return '';
    }

    public function wechat()
    {
        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }

    public function alipay()
    {
        return 'success';
    }

    public function alipayNotify(Request $request)
    {
        \Log::info("支付宝支付回调全部参数", $request->all());
        // 校验输入参数
        $data  = Pay::alipay(config("pay.alipay"))->verify($request->all());
        // 如果订单状态不是成功或者结束，则不走后续的逻辑
        if(!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return $this->alipay();
        }
        // $data->out_trade_no 拿到订单流水号，并在数据库中查询
        $order = Deposit::where('no', $data->out_trade_no)->where("status", 0)->first();

        // 订单不存在
        if (!$order) {
            return $this->alipay();
        }

        // 订单已支付
        if ($order->status == 1) {
            return $this->alipay();
        }

        $status = DB::transaction(function () use ($data, $order) {

            // 将订单标记为已支付
            \Log::info("将订单标记为已支付开始");
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 1,
                'status'        => 1,
                'pay_no'        => $data->trade_no,
                'amount'        => $data->total_amount,
            ]);
            \Log::info("将订单标记为已支付结束");

            $user = User::find($order->user_id);
            if ($order->type === 2) {
                \Log::info("冻结余额充值");
                \Log::info("增加冻结余额");
                DB::table('users')->where("id", $order->user_id)->increment('frozen_money', $order->amount);
                \Log::info("记录冻结余额日志");
                $logs = new UserFrozenBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->frozen_money,
                    "after_money" => ($user->frozen_money * 100 + $order->amount * 100) / 100,
                    "description" => "支付宝充值：{$data->trade_no}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("记录冻结余额日志结束");
            } else {
                \Log::info("余额充值");
                DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);
                \Log::info("余额充值结束");
                \Log::info("记录余额日志");
                UserMoneyBalance::create([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->money,
                    "after_money" => ($user->money * 100 + $order->amount * 100) / 100,
                    "description" => "支付宝充值：{$data->trade_no}",
                    "tid" => $order->id
                ]);
                \Log::info("日志保存结束");
            }

            // try {
            //     $user = DB::table('users')->where('id', $order->user_id)->first();
            //     app('easysms')->send('13843209606', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '支付宝',
            //             'number' => $data->total_amount
            //         ],
            //     ]);
            //     app('easysms')->send('18611683889', [
            //         'template' => 'SMS_186360326',
            //         'data' => [
            //             'name' => $user->phone ?? '',
            //             'type' => '支付宝',
            //             'number' => $data->total_amount
            //         ],
            //     ]);
            // } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            //     $message = $exception->getException('aliyun')->getMessage();
            //     \Log::info('充值通知短信发送失败', [$message]);
            // }

            return true;

        });

        if ($status) {

            return $this->alipay();
        }

        return '';
    }

    /**
     * 支付宝运营充值回调
     * @data 2024/1/5 3:50 下午
     */
    public function alipayNotifyOperate(Request $request)
    {
        \Log::info("支付宝运营支付回调全部参数", $request->all());
        // 校验输入参数
        $data  = Pay::alipay(config("pay.mqjk_alipay"))->verify($request->all());
        // 如果订单状态不是成功或者结束，则不走后续的逻辑
        if(!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return $this->alipay();
        }
        // $data->out_trade_no 拿到订单流水号，并在数据库中查询
        $order = Deposit::where('no', $data->out_trade_no)->where("status", 0)->first();

        // 订单不存在
        if (!$order) {
            return $this->alipay();
        }

        // 订单已支付
        if ($order->status == 1) {
            return $this->alipay();
        }

        $status = DB::transaction(function () use ($data, $order) {
            // 将订单标记为已支付
            \Log::info("将订单标记为已支付开始");
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 1,
                'status'        => 1,
                'pay_no'        => $data->trade_no,
                'amount'        => $data->total_amount,
            ]);
            \Log::info("将订单标记为已支付结束");
            $user = User::find($order->user_id);
            if ($order->type === 3) {
                \Log::info("运营余额充值-增加运营余额");
                DB::table('users')->where("id", $order->user_id)->increment('operate_money', $order->amount);
                \Log::info("记录运营余额日志");
                $logs = new UserOperateBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->operate_money,
                    "after_money" => ($user->operate_money * 100 + $order->amount * 100) / 100,
                    "description" => "支付宝充值：{$data->transaction_id}",
                    "tid" => $order->id
                ]);
                $logs->save();
                \Log::info("日志保存结束");
            }
            return true;
        });

        $user = DB::table('users')->find($order->user_id);
        if ($user && $user->operate_money >= config('ps.sms_operate_remind.max')) {
            DB::table('send_sms_logs')->where('phone', $user->phone)->where('type', 2)->limit(1)->delete();
        }
        if ($user && $user->operate_money > 0) {
            $shops = Shop::select('id', 'user_id', 'yunying_status')->where('user_id', $user->id)->get();
            if (!empty($shops)) {
                $date = date("Y-m-d H:i:s", time() - 86400);
                foreach ($shops as $shop) {
                    if ($shop->yunying_status && ShopRestLog::where('shop_id', $shop->id)->where('created_at', '>', $date)->count()) {
                        StoreRestJob::dispatch($shop->id, 2);
                    }
                }
            }
        }

        if ($status) {

            return $this->alipay();
        }

        return '';
    }

    public function wechatSupplierNotify(Request $request)
    {
        // \Log::info('订单支付回调', $request->all());
        // 校验回调参数是否正确
        $data  = Pay::wechat(config("pay.wechat_supplier"))->verify($request->getContent());
        \Log::info('[商城订单-微信支付回调-全部参数]', [$data]);
        // 找到对应的订单
        $orders = SupplierOrder::where('no', $data->out_trade_no)->get();

        // 订单不存在
        if ($orders->isEmpty()) {
            \Log::info('商城订单-微信支付回调-订单号为空', [ $data ]);
            $orders = SupplierOrder::where('pay_no', $data->out_trade_no)->get();
            if ($orders->isEmpty()) {
                \Log::info('商城订单-微信支付回调-交易单号为空', [ $data ]);
                return $this->wechat();
            }
        }

        // 订单已支付
        // if ($orders->status > 0) {
        //     return $this->wechat();
        // }

        $amount = 0;

        foreach ($orders as $order) {
            $amount += ($order->total_fee * 100 - $order->frozen_fee * 100);
        }

        $pay_amount = intval($amount);
        $notify_amount = intval($data->total_fee);
        \Log::info('商城订单-微信支付回调-订单号为空', [ $data->out_trade_no, $pay_amount, $notify_amount ]);

        // 订单金额判断
        if ($pay_amount != $notify_amount) {
            \Log::info('商城订单-微信支付回调-支付订单金额不符', [ $data, $orders, $pay_amount, $notify_amount ]);
            return $this->wechat();
        }

        $status = DB::transaction(function () use ($data, $orders) {

            foreach ($orders as $order) {
                if ($order->status == 0) {
                    \Log::info('商城订单-微信支付回调-将订单标记为已支付', [ $data, $order ]);
                    // 将订单标记为已支付
                    $items = $order->items;
                    foreach ($items as $item) {
                        \Log::info('商城订单-微信支付回调-合同订单', [ $items ]);
                        if ($item->product_id === 739) {
                            $insert_data = [];
                            for ($i = 0; $i < $item->amount; $i++) {
                                $insert_data[] = [
                                    'user_id' => $order->user_id,
                                    'order_id' => $order->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                ];
                            }
                            ContractOrder::insert($insert_data);
                        }
                    }
                    if (count($items) === 1 && $items[0]->product_id === 739) {
                        \Log::info('商城订单-微信支付回调-只有合同订单', [ $items ]);
                        $update_data = [
                            'paid_at'           => date('Y-m-d H:i:s'),
                            'deliver_at'           => date('Y-m-d H:i:s'),
                            'completion_at'           => date('Y-m-d H:i:s'),
                            'payment_no'        => $data->transaction_id,
                            'payment_method'    => 2,
                            'status'            => 70
                        ];
                    } else {
                        $update_data = [
                            'paid_at'           => date('Y-m-d H:i:s'),
                            'payment_no'        => $data->transaction_id,
                            'payment_method'    => 2,
                            'status'            => 30
                        ];
                    }
                    DB::table('supplier_orders')->where("id", $order->id)->update($update_data);
                }
            }

            return true;

        });

        if ($status) {

            return $this->wechat();
        }

        return '';
    }

    public function supplierRefund(Request $request)
    {
        \Log::info("微信支付-退款回调-全部参数", $request->all());
        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }
}
