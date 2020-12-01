<?php

namespace App\Http\Controllers;

use Pay;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function pay(Request $request)
    {
        \Log::info("调用支付", [$request->all()]);
        $user = Auth::user();
        $pay_method = $request->get("method", 0);
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);

        // if ($pay_method != 1 && $pay_method != 2 && $pay_method != 3) {
        if ($pay_method != 2) {
            return $this->error("支付方式不正确");
        }

        $pay_no = '';
        $total_fee = 0;

        if ($id) {
            $supplier_order = SupplierOrder::query()
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->find($id);
            if (!$supplier_order) {
                return $this->error("订单不存在");
            }
            $supplier_order->pay_no = $supplier_order->no;
            $supplier_order->save();
            $pay_no = $supplier_order->pay_no;
            $total_fee = $supplier_order->total_fee;
        } elseif ($no) {
            $supplier_orders = SupplierOrder::query()
                ->where('pay_no', $no)
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->get();
            if ($supplier_orders->isEmpty()) {
                return $this->error("订单不存在");
            }

            $pay_no = $no;

            foreach ($supplier_orders as $v) {
                $total_fee += $v->total_fee * 100;
            }

            $total_fee = $total_fee / 100;
        }

        if ($pay_method == 1) {
            // 支付宝支付
        } else if ($pay_method == 2) {
            // 微信支付

            $order = [
                'out_trade_no'  => $pay_no,
                'body'          => '订单支付-' . $pay_no,
                'total_fee'     => $total_fee * 100,
            ];

            $wechatOrder = app('pay.wechat_supplier')->scan($order);

            $data = [
                'code_url' => $wechatOrder->code_url,
                'amount'  => $total_fee,
                'out_trade_no'  => $pay_no,
            ];

            return $this->success($data);

        } else if ($pay_method == 3) {

            if (!$code = $request->get('code')) {
                return $this->error('微信未授权，无法使用支付');
            }

            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxd0ea0008a2364d9f&secret=1d3436d84cc39862aff5ef7f46f41e2e&code={$code}&grant_type=authorization_code";

            $auth_json = file_get_contents($url);

            \Log::info("auth", [$auth_json]);

            $auth = json_decode($auth_json, true);

            if (!isset($auth['openid'])) {
                return $this->error('微信未授权，无法使用支付');
            }

            $order = [
                'out_trade_no'  => $pay_no,
                'body'          => '订单支付-' . $pay_no,
                'total_fee'     => $total_fee * 100,
                'openid'        => $auth['openid']
            ];

            $wechatOrder = app('pay.wechat_supplier')->mp($order);

            \Log::info("公众号支付获取参数", [$wechatOrder]);

            return $this->success($wechatOrder);

        }

    }

    public function payByWechat(Request $request) {

        $user = Auth::user();

        if (!$supplier_order = SupplierOrder::query()->find($request->get("order_id", 0))) {
            return $this->error("订单不存在");
        }


        if ($supplier_order->user_id !== $user->id) {
            return $this->error("订单不存在");
        }

        if ($supplier_order->status > 10 ) {
            return $this->error("订单状态不正确");
        }

        $order = [
            'out_trade_no'  => $supplier_order->no,
            'body'          => '订单支付-' . $supplier_order->no,
            'total_fee'     => $supplier_order->total_fee * 100,
            // 'total_fee'     => 1
        ];

        $wechatOrder = app('pay.wechat_supplier')->scan($order);

        $data = [
            'code_url' => $wechatOrder->code_url,
            'amount'  => $supplier_order->total_fee,
            'out_trade_no'  => $supplier_order->no,
        ];

        return $this->success($data);
    }
}
