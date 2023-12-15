<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserMoneyBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yansongda\Pay\Pay;

class PaymentController extends Controller
{
    // use No
    /**
     * 支付方式
     * @data 2023/8/16 9:51 上午
     */
    public function pay_method(Request $request)
    {
        // 渠道（1 APP，2 PC网站）
        $channel = (int) $request->get("channel", 1);
        if ($channel === 2) {
            $result = [
                ['method' => 1, 'text' => '支付宝', 'checked' => 0],
                ['method' => 2, 'text' => '微信', 'checked' => 1],
            ];
        } else {
            $result = [
                ['method' => 1, 'text' => '支付宝', 'checked' => 1],
                // ['method' => 2, 'text' => '微信', 'checked' => 0],
            ];
        }
        return $this->success($result);
    }

    /**
     * 运营支付方式
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/17 2:57 下午
     */
    public function operate_pay_method(Request $request)
    {
        // 渠道（1 APP，2 PC网站）
        $channel = (int) $request->get("channel", 1);
        if ($channel === 2) {
            $result = [
                // ['method' => 1, 'text' => '支付宝', 'checked' => 0],
                ['method' => 2, 'text' => '微信', 'checked' => 1],
            ];
        } else {
            $result = [
                // ['method' => 1, 'text' => '支付宝', 'checked' => 1],
                // ['method' => 2, 'text' => '微信', 'checked' => 0],
            ];
        }
        return $this->success($result);
    }

    /**
     * 获取支付-订单信息
     * @data 2023/8/16 9:52 上午
     */
    public function pay(Request $request)
    {
        $user  = $request->user();
        $amount = (int) $request->get("amount", 0);
        $method = (int) $request->get("method", 0);
        // 渠道（1 APP，2 PC网站）
        $channel = (int) $request->get("channel", 1);
        if (!in_array($method, [1,2])) {
            return $this->error("支付方式不正确");
        }
        if ($amount < 1) {
            return $this->error("金额不正确");
        }
        if (!in_array($channel, [1,2])) {
            return $this->error("支付方式不正确");
        }

        $deposit = new Deposit([
            'pay_method' => 11,
            'type' => 1,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        $result = [];
        if ($channel === 1) {
            // APP充值
            if ($method === 1) {
                $order = [
                    'out_trade_no' => $deposit->no,
                    'total_amount' => $deposit->amount,
                    'subject' => '美全配送充值',
                ];
                $config = config("pay.alipay");
                $config['notify_url'] = $config['app_notify_url'];
                $order_info = Pay::alipay($config)->app($order)->getContent();
                $result = ['order_info' => $order_info];
            } elseif ($method === 2) {
                $order = [
                    'out_trade_no'  => $deposit->no,
                    'body'          => '美全配送充值',
                    'total_fee'     => $deposit->amount * 100
                ];
                $wechatOrder = Pay::wechat(config("pay.wechat"))->app($order)->getContent();
                // $result = [
                //     'appid' => '',
                //     'noncestr' => '',
                //     'package' => '',
                //     'partnerid' => '',
                //     'prepayid' => '',
                //     'timestamp' => '',
                //     'sign' => '',
                // ];
                return $this->success($wechatOrder);
            }
        } else {
            // PC充值
            if ($method == 1) {
                $order = [
                    'out_trade_no' => $deposit->no,
                    'total_amount' => $deposit->amount,
                    'subject' => '美全配送充值',
                ];
                return $this->success(['html' => Pay::alipay(config("pay.alipay"))->web($order)->getContent()]);
            } else if ($method == 2) {
                $order = [
                    'out_trade_no'  => $deposit->no,
                    'body'          => '美全配送充值',
                    'total_fee'     => $deposit->amount * 100
                ];
                $wechatOrder = Pay::wechat(config("pay.wechat"))->scan($order);
                $data = [
                    'code_url' => $wechatOrder->code_url,
                    'amount'  => $deposit->amount,
                    'out_trade_no'  => $deposit->no,
                ];
                return $this->success($data);
            }
        }
        return $this->success($result);
    }

    /**
     * 运营余额获取支付-订单信息
     * @data 2023/8/16 9:52 上午
     */
    public function operate_pay(Request $request)
    {
        $user  = $request->user();
        $amount = (int) $request->get("amount", 0);
        $method = (int) $request->get("method", 0);
        // 渠道（1 APP，2 PC网站）
        $channel = (int) $request->get("channel", 1);
        if (!in_array($method, [1,2])) {
            return $this->error("支付方式不正确");
        }
        if ($amount < 1) {
            return $this->error("金额不正确");
        }
        if (!in_array($channel, [1,2])) {
            return $this->error("支付方式不正确");
        }

        $deposit = new Deposit([
            'pay_method' => 2,
            'type' => 3,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        $result = [];
        if ($channel === 1) {
            // // APP充值
            // $order = [
            //     'out_trade_no' => $deposit->no,
            //     'total_amount' => $deposit->amount,
            //     'subject' => '美全运营充值',
            // ];
            // if ($method === 1) {
            //     $config = config("pay.alipay");
            //     $config['notify_url'] = $config['app_notify_url'];
            //     $order_info = Pay::alipay($config)->app($order)->getContent();
            //     $result = ['order_info' => $order_info];
            // } elseif ($method === 2) {
            //     $result = [
            //         'appid' => '',
            //         'noncestr' => '',
            //         'package' => '',
            //         'partnerid' => '',
            //         'prepayid' => '',
            //         'timestamp' => '',
            //         'sign' => '',
            //     ];
            // }
        } else {
            // PC充值
            if ($method == 1) {
                // $order = [
                //     'out_trade_no' => $deposit->no,
                //     'total_amount' => $deposit->amount,
                //     'subject' => '美全配送充值',
                // ];
                // return $this->success(['html' => Pay::alipay(config("pay.alipay"))->web($order)->getContent()]);
            } else if ($method == 2) {
                $order = [
                    'out_trade_no'  => $deposit->no,
                    'body'          => '美全运营充值',
                    'total_fee'     => $deposit->amount * 100
                ];
                $wechatOrder = Pay::wechat(config("pay.wechat_operate_money"))->scan($order);
                $data = [
                    'code_url' => $wechatOrder->code_url,
                    'amount'  => $deposit->amount,
                    'out_trade_no'  => $deposit->no,
                ];
                return $this->success($data);
            }
        }
        return $this->success($result);
    }

    /**
     * 支付宝-支付回调
     * @data 2023/8/16 9:52 上午
     */
    public function alipay_notify(Request $request)
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
            DB::table('users')->where("id", $order->user_id)->increment('money', $order->amount);
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
            return true;
        });

        if (!$status) {
            \Log::info("支付宝充值回调充值失败");
        }
        return $this->alipay();
    }
}
