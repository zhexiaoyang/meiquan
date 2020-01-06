<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use Illuminate\Http\Request;
use Yansongda\Pay\Pay;

class DepositController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $data = $request->user()->deposit()->where("status", 1)->paginate($page_size);
        return $this->success($data);
    }

    public function store(Request $request)
    {
        $user  = $request->user();
        $amount = $request->get("amount", 0);
        $pay_method = $request->get("pay_method", 0);

        if ($amount < 1) {
            return $this->error("金额不正确");
        }

        if ($pay_method !== 1 || $pay_method !== 2) {
            return $this->error("方式不正确");
        }

        $deposit = new Deposit([
            'pay_method' => $pay_method,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        $order = [
            'out_trade_no' => $deposit->no,
            'total_amount' => $deposit->amount,
            'subject' => '美全配送充值',
        ];

        $config = config('pay.alipay');

        return $this->success(['html' => Pay::alipay($config)->web($order)->getContent()]);
    }
}
