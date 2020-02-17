<?php

namespace App\Http\Controllers\Api;

use App\Models\Deposit;
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

            DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);

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

            return true;

        });

        if ($status) {

            return $this->alipay();
        }

        return '';
    }
}