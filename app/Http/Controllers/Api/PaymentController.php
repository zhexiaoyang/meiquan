<?php

namespace App\Http\Controllers\Api;

use App\Models\Deposit;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Models\UserFrozenBalance;
use Illuminate\Http\Request;
use Pay;
use DB;

class PaymentController
{
    public function wechatNotify(Request $request)
    {
        // 校验回调参数是否正确
        $data  = Pay::wechat()->verify($request->getContent());
        // 找到对应的订单
        $order = Deposit::where('no', $data->out_trade_no)->first();

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
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 2,
                'status'        => 1,
                'pay_no'        => $data->transaction_id,
                'amount'        => $data->total_fee / 100,
            ]);

            if ($order->type === 2) {
                $user = User::query()->find($order->user_id);
                DB::table('users')->where("id", $order->user_id)->increment('frozen_money', $order->amount);
                $logs = new UserFrozenBalance([
                    "user_id" => $user->id,
                    "money" => $order->amount,
                    "type" => 1,
                    "before_money" => $user->frozen_money,
                    "after_money" => ($user->frozen_money * 100 - $order->amount * 100) / 100,
                    "description" => "微信充值：{$order->pay_no}",
                    "tid" => $order->id
                ]);
                $logs->save();
            } else {
                DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);
            }


            try {
                $user = DB::table('users')->where('id', $order->user_id)->first();
                app('easysms')->send('13843209606', [
                    'template' => 'SMS_186360326',
                    'data' => [
                        'name' => $user->phone ?? '',
                        'type' => '微信',
                        'number' => $data->total_fee / 100
                    ],
                ]);
                app('easysms')->send('18611683889', [
                    'template' => 'SMS_186360326',
                    'data' => [
                        'name' => $user->phone ?? '',
                        'type' => '微信',
                        'number' => $data->total_fee / 100
                    ],
                ]);
            } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                $message = $exception->getException('aliyun')->getMessage();
                \Log::info('充值通知短信发送失败', [$message]);
            }

            return true;

        });

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
        // 校验输入参数
        $data  = Pay::alipay()->verify($request->all());
        // 如果订单状态不是成功或者结束，则不走后续的逻辑
        if(!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return $this->alipay();
        }
        // $data->out_trade_no 拿到订单流水号，并在数据库中查询
        $order = Deposit::where('no', $data->out_trade_no)->first();

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
            DB::table('deposits')->where("id", $order->id)->update([
                'paid_at'       => date('Y-m-d H:i:s'),
                'pay_method'    => 1,
                'status'        => 1,
                'pay_no'        => $data->trade_no,
                'amount'        => $data->total_amount,
            ]);

            DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);

            try {
                $user = DB::table('users')->where('id', $order->user_id)->first();
                app('easysms')->send('13843209606', [
                    'template' => 'SMS_186360326',
                    'data' => [
                        'name' => $user->phone ?? '',
                        'type' => '支付宝',
                        'number' => $data->total_amount
                    ],
                ]);
                app('easysms')->send('18611683889', [
                    'template' => 'SMS_186360326',
                    'data' => [
                        'name' => $user->phone ?? '',
                        'type' => '支付宝',
                        'number' => $data->total_amount
                    ],
                ]);
            } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                $message = $exception->getException('aliyun')->getMessage();
                \Log::info('充值通知短信发送失败', [$message]);
            }

            return true;

        });

        if ($status) {

            return $this->alipay();
        }

        return '';
    }

    public function wechatSupplierNotify(Request $request)
    {
        \Log::info('订单支付回调', $request->all());
        // 校验回调参数是否正确
        $data  = Pay::wechat()->verify($request->getContent());
        // 找到对应的订单
        $orders = SupplierOrder::query()->where('no', $data->out_trade_no)->get();

        // 订单不存在
        if ($orders->isEmpty()) {
            \Log::info('支付-订单号为空', [ $data ]);
            $orders = SupplierOrder::query()->where('pay_no', $data->out_trade_no)->get();
            if ($orders->isEmpty()) {
                \Log::info('支付-交易单号为空', [ $data ]);
                return $this->wechat();
            }
        }

        // 订单已支付
        // if ($orders->status > 0) {
        //     return $this->wechat();
        // }

        $amount = 0;

        foreach ($orders as $order) {
            $amount += $order->total_fee * 100;
        }

        // 订单金额判断
        if ($amount != $data->total_fee) {
            \Log::info('支付订单金额不符', [ $data, $orders ]);
            return $this->wechat();
        }

        $status = DB::transaction(function () use ($data, $orders) {

            foreach ($orders as $order) {
                if ($order->status == 0) {
                    \Log::info('将订单标记为已支付', [ $data, $order ]);
                    // 将订单标记为已支付
                    DB::table('supplier_orders')->where("id", $order->id)->update([
                        'paid_at'           => date('Y-m-d H:i:s'),
                        'payment_no'        => $data->transaction_id,
                        'payment_method'    => 2,
                        'status'            => 30
                    ]);
                }
            }

            return true;

        });

        if ($status) {

            return $this->wechat();
        }

        return '';
    }
}
