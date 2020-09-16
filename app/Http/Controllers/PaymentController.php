<?php

namespace App\Http\Controllers;

use Pay;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
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
            // 'total_fee'     => $supplier_order->total_amount * 100,
            'total_fee'     => 1
        ];

        $wechatOrder = app('pay.wechat_supplier')->scan($order);

        $data = [
            'code_url' => $wechatOrder->code_url,
            'amount'  => $supplier_order->total_amount,
            'out_trade_no'  => $supplier_order->no,
        ];

        return $this->success($data);
    }
}
