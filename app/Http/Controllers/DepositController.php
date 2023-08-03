<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        $data = $request->user()->deposit()->where("status", 1)->orderBy('id', 'desc')->paginate($page_size);
        return $this->success($data);
    }

    public function store(Request $request)
    {
        $user  = $request->user();
        $amount = $request->get("amount", 0);
        $pay_method = $request->get("pay_method", 0);
        $type = (int) $request->get("type", 1);

        if ($amount < 1) {
            return $this->error("金额不正确");
        }

        if ($pay_method != 1 && $pay_method != 2 && $pay_method != 3) {
            return $this->error("方式不正确");
        }

        $deposit = new Deposit([
            'pay_method' => $pay_method,
            'type' => $type,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        if ($pay_method == 1) {

            $order = [
                'out_trade_no' => $deposit->no,
                'total_amount' => $deposit->amount,
                'subject' => '美全配送充值',
            ];

            // $config = config('pay.alipay');

            return $this->success(['html' => Pay::alipay(config("pay.alipay"))->web($order)->getContent()]);

        } else if ($pay_method == 2) {

            $order = [
                'out_trade_no'  => $deposit->no,
                // 'body'          => '美全配送充值',
                'total_fee'     => $deposit->amount * 100
            ];

            if ($type === 1) {
                $order['body'] = '美全配送充值';
                $wechatOrder = Pay::wechat(config("pay.wechat"))->scan($order);
            } else if ($type === 2){
                $order['body'] = '美全商城充值';
                $wechatOrder = Pay::wechat(config("pay.wechat_supplier_money"))->scan($order);
            } else if ($type === 3){
                $order['body'] = '美全运营充值';
                $wechatOrder = Pay::wechat(config("pay.wechat_operate_money"))->scan($order);
            }

            $data = [
                'code_url' => $wechatOrder->code_url,
                'amount'  => $deposit->amount,
                'out_trade_no'  => $deposit->no,
            ];

            return $this->success($data);

        } else if ($pay_method == 3) {

            if (!$code = $request->get('code')) {
                return $this->error('微信未授权，无法使用支付');
            }

            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . config('pay.wechat_supplier.app_id') . "&secret=58beb50cbf852451d317d75b5c1f266e&code={$code}&grant_type=authorization_code";

            $auth_json = file_get_contents($url);

            \Log::info("auth", [$auth_json]);

            $auth = json_decode($auth_json, true);

            if (!isset($auth['openid'])) {
                return $this->error('微信未授权，无法使用支付');
            }

            $order = [
                'out_trade_no'  => $deposit->no,
                // 'body'          => '美全配送充值',
                'total_fee'     => $deposit->amount * 100,
                'openid'        => $auth['openid']
            ];

            if ($type === 1) {
                $order['body'] = '美全配送充值';
                $wechatOrder = Pay::wechat(config("pay.wechat"))->mp($order);
            } else if ($type === 2) {
                $order['body'] = '美全商城充值';
                $wechatOrder = Pay::wechat(config("pay.wechat_supplier_money"))->mp($order);
            } else if ($type === 3) {
                $order['body'] = '美全运营充值';
                $wechatOrder = Pay::wechat(config("pay.wechat_operate_money"))->mp($order);
            }

            \Log::info("公众号支付获取参数", [$wechatOrder]);

            return $this->success($wechatOrder);

        }
    }

    /**
     * 商城余额-微信支付-小程序
     */
    public function shopWechatMiniApp(Request $request)
    {
        $user  = $request->user();
        $amount = $request->get("amount", 0);

        if ($amount < 1) {
            return $this->error("金额不正确");
        }

        // 写入充值记录表
        $deposit = new Deposit([
            'pay_method' => 2,
            'type' => 2,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        // 获取 openid
        if (!$code = $request->get('code')) {
            return $this->error('微信未授权，无法使用支付');
        }
        // 获取授权缓存
        $auth = Cache::get($code);

        // 判断是否授权过了
        if (!$auth) {
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . config('pay.wechat_supplier.miniapp_id') . "&secret=386b2178ed640318807cca45e013659f&js_code={$code}&grant_type=authorization_code";
            $auth_json = file_get_contents($url);
            $auth = json_decode($auth_json, true);
            // Log::info($prefix . "openid不存在，微信未授权，无法使用支付", [$auth]);

            if (!isset($auth['openid'])) {
                // Log::info($prefix . "openid不存在，微信未授权，无法使用支付");
                return $this->error('微信未授权，无法使用支付');
            }

            // 将获取到的 auth 缓存1个小时
            $expiredAt = now()->addHours(1);
            Cache::put($code, $auth, $expiredAt);
        }

        // 充值数组
        $order = [
            'out_trade_no'  => $deposit->no,
            'body'          => '美全商城余额充值',
            'total_fee'     => $deposit->amount * 100,
            'openid'        => $auth['openid']
        ];

        // $suppllier_config = config('pay.wechat_supplier');
        //
        // $config = [
        //     'miniapp_id' => $suppllier_config['miniapp_id'],
        //     'mch_id' => $suppllier_config['mch_id'],
        //     'notify_url' => $suppllier_config['notify_url'],
        //     'key' => $suppllier_config['key'],
        //     'log' => $suppllier_config['log'],
        // ];

        $wechatOrder = Pay::wechat(config('pay.wechat_supplier'))->miniapp($order);

        Log::info("商城小程序支付获取参数", [$wechatOrder]);

        return $this->success($wechatOrder);

    }

    /**
     * 跑腿余额-微信支付-小程序
     */
    public function runningWechatMiniApp(Request $request)
    {
        $user  = $request->user();
        $amount = $request->get("amount", 0);

        if ($amount < 1) {
            return $this->error("金额不正确");
        }

        // 写入充值记录表
        $deposit = new Deposit([
            'pay_method' => 2,
            'type' => 1,
            'amount' => $amount,
        ]);
        $deposit->user()->associate($user);
        // 写入数据库
        $deposit->save();

        // 获取 openid
        if (!$code = $request->get('code')) {
            return $this->error('微信未授权，无法使用支付');
        }
        // 获取授权缓存
        $auth = Cache::get($code);

        // 判断是否授权过了
        if (!$auth) {
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . config('pay.wechat.miniapp_id') . "&secret=" . config('pay.wechat.miniapp_app_secret') . "&js_code={$code}&grant_type=authorization_code";
            $auth_json = file_get_contents($url);
            $auth = json_decode($auth_json, true);
            // Log::info($prefix . "openid不存在，微信未授权，无法使用支付", [$auth]);

            if (!isset($auth['openid'])) {
                // Log::info($prefix . "openid不存在，微信未授权，无法使用支付");
                return $this->error('微信未授权，无法使用支付');
            }

            // 将获取到的 auth 缓存1个小时
            $expiredAt = now()->addHours(1);
            Cache::put($code, $auth, $expiredAt);
        }

        // 充值数组
        $order = [
            'out_trade_no'  => $deposit->no,
            'body'          => '美全跑腿余额充值',
            'total_fee'     => $deposit->amount * 100,
            'openid'        => $auth['openid']
        ];

        // $suppllier_config = config('pay.wechat_supplier');
        //
        // $config = [
        //     'miniapp_id' => $suppllier_config['miniapp_id'],
        //     'mch_id' => $suppllier_config['mch_id'],
        //     'notify_url' => $suppllier_config['notify_url'],
        //     'key' => $suppllier_config['key'],
        //     'log' => $suppllier_config['log'],
        // ];

        $wechatOrder = Pay::wechat(config('pay.wechat'))->miniapp($order);

        Log::info("跑腿小程序支付获取参数", [$wechatOrder]);

        return $this->success($wechatOrder);

    }
}
