<?php

namespace App\Http\Controllers\Api\Callback;

use App\Http\Controllers\Controller;
use App\Models\ExpressOrder;
use App\Models\ExpressOrderLog;
use App\Models\User;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KuaiDiController extends Controller
{
    public $prefix = '快递100订单回调';

    public function order(Request $request)
    {
        $this->log("全部参数", $request->all());
        $res = ['result' => true, 'returnCode' => '200', 'message' => '成功'];

        $param = json_decode($request->get('param'), true);
        $data = $param['data'];
        $order_id = $data['orderId'];
        $this->prefix = "[快递100订单回调|订单号{$order_id}]";
        if (!$order = ExpressOrder::where('order_id', $order_id)->first()) {
            $this->log("订单不存在");
            return $this->success($res);
        }

        // 获取参数
        $status = intval($data['status']);
        $freight = $data['freight'] ?? '';
        $courier_name = $data['courierName'] ?? '';
        $courier_mobile = $data['courierMobile'] ?? '';
        $weight = $data['weight'] ?? '';
        $kuaidinum = $param['kuaidinum'] ?? '';
        // 判断日志是否存在
        if (ExpressOrderLog::query()->where(['name' => $courier_name, 'status' => $status])->exists()) {
            $this->log("日志已存在");
            return $this->success($res);
        }
        // 添加日志
        ExpressOrderLog::create([
            'order_id' => $order->id,
            'name' => $courier_name,
            'phone' => $courier_mobile,
            'status' => $status
        ]);
        // 参数赋值
        if ($courier_name) {
            $order->courier_name = $courier_name;
        }
        if ($courier_mobile) {
            $order->courier_mobile = $courier_mobile;
        }
        if ($weight) {
            $order->weight = $weight;
        }
        if ($kuaidinum) {
            $order->three_order_id = $kuaidinum;
        }
        $order->status = $status;
        if ($status == 15) {
            if ($freight != null) {
                $order->freight = $freight;
                User::where('id', $order->user_id)->decrement('money', $freight);
                $this->log("减运费：{$freight} 元");
                // 查找扣款用户，为了记录余额日志
                $current_user = DB::table('users')->find($order->user_id);
                // 用户余额日志
                UserMoneyBalance::create([
                    "user_id" => $order->user_id,
                    "money" => $freight,
                    "type" => 2,
                    "before_money" => $current_user->money,
                    "after_money" => ($current_user->money - $order->money_mqd),
                    "description" => "快递订单：" . ($kuaidinum ?: $order->order_id),
                    "tid" => $order->id
                ]);
            } else {
                $this->log("运费为null");
            }
        }
        $order->save();

        return $this->status($res);
    }
}
