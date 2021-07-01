<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Yansongda\Pay\Pay;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * 商城订单微信支付
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/2/6 6:11 下午
     */
    public function pay(Request $request)
    {
        \Log::info("调用支付", [$request->all()]);
        $user = Auth::user();
        $pay_method = $request->get("method", 0);
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);

        // if ($pay_method != 1 && $pay_method != 2 && $pay_method != 3) {
        // if ($pay_method != 2 && $pay_method != 3 && $pay_method != 8) {
        if ($pay_method != 2 && $pay_method != 3) {
            return $this->error("支付方式不正确");
        }

        $pay_no = '';
        $total_fee = 0;
        $pay_orders = [];

        if ($id) {
            $supplier_order = SupplierOrder::query()
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->find($id);
            if (!$supplier_order) {
                return $this->error("订单不存在或已支付");
            }
            $supplier_order->pay_no = $supplier_order->no;
            $supplier_order->save();
            $pay_no = $supplier_order->pay_no;
            $total_fee = (($supplier_order->total_fee * 100) - ($supplier_order->frozen_fee * 100)) / 100;
            $pay_orders[] = $supplier_order;
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
                $total_fee += $v->total_fee * 100 - $v->frozen_fee * 100;
            }

            $total_fee = $total_fee / 100;
        }

        if ($pay_method == 1) {
            // 支付宝支付
        } else if ($pay_method == 2) {
            // 微信支付-扫码支付
            \Log::info("微信支付：{$total_fee}");

            $order = [
                'out_trade_no'  => $pay_no,
                'body'          => '订单支付-' . $pay_no,
                'total_fee'     => intval($total_fee * 100),
            ];

            $wechatOrder = app('pay.wechat_supplier')->scan($order);

            $data = [
                'code_url' => $wechatOrder->code_url,
                'amount'  => $total_fee,
                'out_trade_no'  => $pay_no,
            ];

            return $this->success($data);

        } else if ($pay_method == 3) {
            // 微信支付-公众号支付
            if (!$code = $request->get('code')) {
                \Log::info("[商城订单支付-微信]-[method: {$pay_method}, code: {$code}]-[code不存在]-[微信未授权，无法使用支付]");
                return $this->error('微信未授权，无法使用支付');
            }

            $auth = Cache::get($code);

            if (!$auth) {
                $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxd0ea0008a2364d9f&secret=58beb50cbf852451d317d75b5c1f266e&code={$code}&grant_type=authorization_code";

                $auth_json = file_get_contents($url);

                \Log::info("auth", [$auth_json]);

                $auth = json_decode($auth_json, true);

                if (!isset($auth['openid'])) {
                    \Log::info("[商城订单支付-微信]-[method: {$pay_method}, code: {$code}]-[openid不存在]-[微信未授权，无法使用支付]");
                    return $this->error('微信未授权，无法使用支付');
                }

                // 将获取到的 auth 缓存1个小时
                $expiredAt = now()->addHours(1);
                Cache::put($code, $auth, $expiredAt);
            }

            $order = [
                'out_trade_no'  => $pay_no,
                'body'          => '订单支付-' . $pay_no,
                'total_fee'     => intval($total_fee * 100),
                'openid'        => $auth['openid']
            ];

            // $wechatOrder = app('pay.wechat_supplier')->mp($order);
            $wechatOrder = Pay::wechat(config("pay.wechat_supplier"))->mp($order);

            \Log::info("公众号支付获取参数", [$wechatOrder]);

            return $this->success($wechatOrder);

        // } else if ($pay_method == 8) {
        //     // 余额支付
        //     $current_user = User::query()->find($user->id);
        //
        //     if ($current_user->money < $total_fee) {
        //         return $this->error("余额不足");
        //     }
        //
        //     try {
        //         \DB::transaction(function () use ($current_user, $total_fee, $pay_orders) {
        //             \DB::table('users')->where(["id" => $current_user->id, "money" => $current_user->money])
        //                 ->update(["money" => ($current_user->money - $total_fee)]);
        //             $order_ids = [];
        //             $order_nos = [];
        //             foreach ($pay_orders as $pay_order) {
        //                 $order_ids[] = $pay_order->id;
        //                 $order_nos[] = $pay_order->no;
        //                 $pay_order->status = 30;
        //                 $pay_order->paid_at = date("Y-m-d");
        //                 $pay_order->payment_method = 30;
        //                 $pay_order->save();
        //             }
        //             $logs = new UserMoneyBalance([
        //                 "user_id" => $current_user->id,
        //                 "money" => $total_fee,
        //                 "type" => 2,
        //                 "before_money" => $current_user->money,
        //                 "after_money" => ($current_user->money - $total_fee),
        //                 "description" => "商城订单：" . implode(",", $order_nos),
        //                 "tid" => 0
        //             ]);
        //             $logs->save();
        //         });
        //     } catch (\Exception $exception) {
        //         return $this->error("支付失败，请稍后再试");
        //     }
        //
        //     return $this->success();
        }
    }

    /**
     * 商城订单，微信扫码支付
     */
    public function supplierOrderByWeChatScan(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);

        // 日志前缀
        $prefix = "[商城订单|微信|扫码支付|用户ID：{$user->id}|订单ID：{$id}|订单号：{$no}]";
        \Log::info($prefix . "全部参数：", $request->all());

        // 支付信息定义
        $pay_no = '';
        $total_fee = 0;

        // 判断是否多个订单一起支付
        if ($id) {
            $supplier_order = SupplierOrder::query()
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->find($id);
            if (!$supplier_order) {
                return $this->error("订单不存在或已支付");
            }
            $supplier_order->pay_no = $supplier_order->no;
            $supplier_order->save();
            $pay_no = $supplier_order->pay_no;
            $total_fee = (($supplier_order->total_fee * 100) - ($supplier_order->frozen_fee * 100)) / 100;
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
                $total_fee += $v->total_fee * 100 - $v->frozen_fee * 100;
            }
            $total_fee = $total_fee / 100;
        }

        $order = [
            'out_trade_no'  => $pay_no,
            'body'          => '商城订单：' . $pay_no,
            'total_fee'     => intval($total_fee * 100),
        ];

        $wechatOrder = app('pay.wechat_supplier')->scan($order);

        $data = [
            'code_url' => $wechatOrder->code_url,
            'amount'  => $total_fee,
            'out_trade_no'  => $pay_no,
        ];

        return $this->success($data);
    }

    /**
     * 商城订单，微信公众支付
     */
    public function supplierOrderByWeChatMp(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);

        // 日志前缀
        $prefix = "[商城订单|微信|公众号支付|用户ID：{$user->id}|订单ID：{$id}|订单号：{$no}]";
        \Log::info($prefix . "全部参数：", $request->all());

        // 获取 code
        if (!$code = $request->get('code')) {
            \Log::info($prefix . "code不存在，微信未授权，无法使用支付");
            return $this->error('微信未授权，无法使用支付');
        }

        // 支付信息定义
        $pay_no = '';
        $total_fee = 0;

        // 判断是否多个订单一起支付
        if ($id) {
            $supplier_order = SupplierOrder::query()
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->find($id);
            if (!$supplier_order) {
                return $this->error("订单不存在或已支付");
            }
            $supplier_order->pay_no = $supplier_order->no;
            $supplier_order->save();
            $pay_no = $supplier_order->pay_no;
            $total_fee = (($supplier_order->total_fee * 100) - ($supplier_order->frozen_fee * 100)) / 100;
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
                $total_fee += $v->total_fee * 100 - $v->frozen_fee * 100;
            }
            $total_fee = $total_fee / 100;
        }

        // 获取授权缓存
        $auth = Cache::get($code);

        // 判断是否授权过了
        if (!$auth) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxd0ea0008a2364d9f&secret=58beb50cbf852451d317d75b5c1f266e&code={$code}&grant_type=authorization_code";
            $auth_json = file_get_contents($url);
            $auth = json_decode($auth_json, true);

            if (!isset($auth['openid'])) {
                \Log::info($prefix . "openid不存在，微信未授权，无法使用支付");
                return $this->error('微信未授权，无法使用支付');
            }

            // 将获取到的 auth 缓存1个小时
            $expiredAt = now()->addHours(1);
            Cache::put($code, $auth, $expiredAt);
        }

        $order = [
            'out_trade_no'  => $pay_no,
            'body'          => '商城订单：' . $pay_no,
            'total_fee'     => intval($total_fee * 100),
            'openid'        => $auth['openid']
        ];

        $wechatOrder = app('pay.wechat_supplier')->mp($order);

        \Log::info("公众号支付获取参数", [$wechatOrder]);

        return $this->success($wechatOrder);
    }

    /**
     * 商城订单，微信小程序支付
     */
    public function supplierOrderByWeChatMiniApp(Request $request)
    {
        $user = Auth::user();
        $id = $request->get("id", 0);
        $no = $request->get("no", 0);

        // 日志前缀
        $prefix = "[商城订单|微信|公众号支付|用户ID：{$user->id}|订单ID：{$id}|订单号：{$no}]";
        Log::info($prefix . "全部参数：", $request->all());

        // 获取 code
        if (!$code = $request->get('code')) {
            Log::info($prefix . "code不存在，微信未授权，无法使用支付");
            return $this->error('微信未授权，无法使用支付');
        }

        // 支付信息定义
        $pay_no = '';
        $total_fee = 0;

        // 判断是否多个订单一起支付
        if ($id) {
            $supplier_order = SupplierOrder::query()
                ->where('user_id', $user->id)
                ->where('status', 0)
                ->find($id);
            if (!$supplier_order) {
                return $this->error("订单不存在或已支付");
            }
            $supplier_order->pay_no = $supplier_order->no;
            $supplier_order->save();
            $pay_no = $supplier_order->pay_no;
            $total_fee = (($supplier_order->total_fee * 100) - ($supplier_order->frozen_fee * 100)) / 100;
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
                $total_fee += $v->total_fee * 100 - $v->frozen_fee * 100;
            }
            $total_fee = $total_fee / 100;
        }

        // 获取授权缓存
        $auth = Cache::get($code);

        // 判断是否授权过了
        if (!$auth) {
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid=wxeb5269cc785be36a&secret=386b2178ed640318807cca45e013659f&js_code={$code}&grant_type=authorization_code";
            $auth_json = file_get_contents($url);
            $auth = json_decode($auth_json, true);
            Log::info($prefix . "获取到的openid", [$auth]);

            if (!isset($auth['openid'])) {
                Log::info($prefix . "openid不存在，微信未授权，无法使用支付");
                return $this->error('微信未授权，无法使用支付');
            }

            // 将获取到的 auth 缓存1个小时
            $expiredAt = now()->addHours(1);
            Cache::put($code, $auth, $expiredAt);
        }

        $order = [
            'out_trade_no'  => $pay_no,
            'body'          => '商城订单：' . $pay_no,
            'total_fee'     => intval($total_fee * 100),
            'openid'        => $auth['openid']
        ];

        $wechatOrder = Pay::wechat(config('pay.wechat_supplier'))->miniapp($order);

        return $this->success($wechatOrder);
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
            'total_fee'     => intval($supplier_order->total_fee * 100),
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
