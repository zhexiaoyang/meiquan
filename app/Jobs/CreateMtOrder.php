<?php

namespace App\Jobs;

use App\Libraries\ShanSongService\ShanSongService;
use App\Models\Order;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CreateMtOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $dingding;
    protected $services = [];
    protected $base = 0;
    protected $weight_money = 0;
    protected $time_money = 0;
    // 美团
    protected $mt_order_id = '';
    protected $money_mt = 0;
    // 蜂鸟
    protected $fn_order_id = '';
    protected $money_fn = 0;
    // 闪送
    protected $ss_order_id = '';
    protected $money_ss = 0;
    // 美全达
    protected $mqd_order_id = '';
    protected $money_mqd = 0;
    // 达达
    protected $dada_order_id = '';
    protected $money_dd = 0;
    // UU
    protected $uu_order_id = '';
    protected $money_uu = 0;
    // 顺丰
    protected $sf_order_id = '';
    protected $money_sf = 0;
    // 日志格式
    protected $log = "";
    // 仓库ID
    protected $warehouse = 0;
    // 加价金额
    protected $add_money = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order, $ttl = 0)
    {
        $this->delay = $ttl;
        $this->order = $order;
        $this->dingding = app("ding");
        $this->log = "[JOB-发单|id:{$order->id},order_id:{$order->order_id}]-";
    }

    public function log($message, $data = [])
    {
        $message = "[JOB:发单|id:{$this->order->id},order_id:{$this->order->order_id},status:{$this->order->status}] {$message}";
        Log::info($message, $data);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->log("开始");

        $order = Order::find($this->order->id);

        if ((strtotime($order->created_at) + 86400 * 2) < time()) {
            $this->log("订单创建时间超出两天，停止派单");
            return;
        }

        if ($order->status > 30) {
            $this->log("订单状态:{$order->status}，大于30，停止派单");
            return;
        }

        // 判断50秒内是否发过订单
        $lock = Cache::lock("send_order_job:{$order->id}", 30);
        if (!$lock->get()) {
            // 获取锁定6秒...
            $this->log("30秒内发过订单，被锁住，停止派单");
            return;
        }

        // 判断是否接单了
        // $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 1);
        // if (!$jiedan_lock->get()) {
        //     // 获取锁定5秒...
        //     $this->log("已经操作接单，停止派单");
        //     return;
        // }

        // 相关信息
        // 判断用户和门店是否存在
        if (!$shop = Shop::find($order->shop_id)) {
            $this->log("门店不存在，停止派单|shop_id:{$order->shop_id}");
            return;
        }
        // if (!$user = User::find($shop->user_id ?? 0)) {
        //     $this->log("用户不存在，停止派单|user_id:{$shop->user_id}");
        //     return;
        // }

        // 默认设置
        $default_settimg = config("ps.shop_setting");
        $order_ttl = $default_settimg["delay_reset"] * 60;
        $mt_switch = $default_settimg['meituan'];
        $fn_switch = $default_settimg['fengniao'];
        $ss_switch = $default_settimg['shansong'];
        $mqd_switch = $default_settimg['meiquanda'];
        $dd_switch = $default_settimg['dada'];
        $uu_switch = $default_settimg['uu'];
        $sf_switch = $default_settimg['shunfeng'];
        // 商家设置
        $setting = OrderSetting::where("shop_id", $shop->id)->first();
        if ($setting) {
            $order_ttl = $setting->delay_reset * 60;
            $mt_switch = $setting->meituan;
            $fn_switch = $setting->fengniao;
            $ss_switch = $setting->shansong;
            $mqd_switch = $setting->meiquanda;
            $dd_switch = $setting->dada;
            $uu_switch = $setting->uu;
            $sf_switch = $setting->shunfeng;

            if ($setting->warehouse && $setting->warehouse_time) {
                $time_data = explode('-', $setting->warehouse_time);
                if (!empty($time_data) && (count($time_data) === 2)) {
                    if (in_time_status($time_data[0], $time_data[1])) {
                        // DB::table('orders')->where('id', $this->order->id)->update(['warehouse_id' => $setting->warehouse]);
                        $this->warehouse = $setting->warehouse;
                        $shop = Shop::find($setting->warehouse);
                        $this->log("仓库转发 | 仓库ID：{{ $setting->warehouse }}，名称：{{ $shop->shop_name }}，仓库时间：{{ $setting->warehouse_time }}");
                    }
                }
            }
        }

        // 自助注册运力判断
        $zz_ss = false;
        $shippers = $shop->shippers;
        if (!empty($shippers)) {
            foreach ($shippers as $shipper) {
                if ($shipper->platform === 3) {
                    $zz_ss = true;
                }
            }
        }

        // 判断用户
        if (!$user = User::find($shop->user_id ?? 0)) {
            $this->log("用户不存在，停止派单|user_id:{$shop->user_id}");
            return;
        }

        // Log::info($this->log."检查重新发送时间：{{ $order_ttl }} 秒");
        $this->log("检查重新发送时间：{{ $order_ttl }} 秒");

        if ($order_ttl < 60) {
            $order_ttl = 60;
            // Log::info($this->log."检查重新发送时间小于60秒，重置为60秒");
            $this->log("检查重新发送时间小于60秒，重置为60秒");
        }

        // 判断用户金额是否满足最小订单
        if ($user->money <= 5.2) {
            if ($order->status < 20) {
                DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
            }
            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, 5]));
            // Log::info($this->log."用户金额不足5.2元,不能发单");
            $this->log("用户金额不足5.2元，停止派单");
            return;
        }

        // 冻结金额
        $use_money = 0;
        $orders = DB::table("orders")->where("user_id", $user->id)->whereIn("status", [20, 30])->get();
        if ($orders->isNotEmpty()) {
            foreach ($orders as $v) {
                if ($v->id !== $this->order->id) {
                    $use_money += max($v->money_mt, $v->money_fn, $v->money_ss, $v->money_dd, $v->money_mqd);
                }
            }
        }
        $this->log("用户冻结金额：{$use_money}");

        // 加价金额
        $add_money = $shop->running_add;
        $manager_money = $shop->running_manager_add;
        $this->add_money = $add_money;
        $this->log("用户加价金额：{$add_money}");

        // **********************************************************************************
        // ******************************  顺  丰  跑  腿  ***********************************
        // **********************************************************************************
        // 判断是否开启顺丰跑腿(是否存在顺丰的门店ID，设置是否打开，没用失败信息)
        if ($order->fail_sf) {
            $this->log("已经有「顺丰」失败信息：{$order->fail_sf}，停止「顺丰」派单");
        } elseif ($order->sf_status != 0) {
            $this->log("订单状态：{$order->sf_status}，不是0，停止「顺丰」派单");
        } elseif (!$shop->shop_id_sf) {
            $order->fail_sf = "门店不支持顺丰跑腿";
            $this->log("门店不支持「顺丰」跑腿，停止「顺丰」派单");
        } elseif (!$sf_switch) {
            $order->fail_sf = "门店关闭顺丰跑腿";
            $this->log("门店关闭「顺丰」跑腿，停止「顺丰」派单");
        } else {
            $sf = app("shunfeng");
            $check_sf= $sf->precreateorder($order, $shop);
            $money_sf = (($check_sf['result']['real_pay_money'] ?? 0) / 100) + $add_money;
            if (isset($check_sf['error_code']) && ($check_sf['error_code'] == 0) && ($money_sf >= 1)) {
                // 判断用户金额是否满足顺丰订单
                if ($user->money < ($money_sf + $use_money)) {
                    if ($order->status < 20) {
                        DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_sf + $use_money]));
                    $this->log("用户金额不足发「顺丰」单，停止派单");
                    return;
                }
                $order->money_sf = $money_sf;
                $this->services['sf'] = $money_sf;
            } else {
                $sf_error_msg = $check_sf['error_msg'] ?? "顺丰校验订单请求失败";
                $order->fail_sf = $sf_error_msg;
                $this->log("「顺丰」校验订单失败:{$sf_error_msg}，停止「顺丰」派单");
            }
        }

        // **********************************************************************************
        // ******************************  U  U  跑  腿  ************************************
        // **********************************************************************************
        // 判断是否开启UU跑腿(是否存在UU的门店ID，设置是否打开，没有失败信息)
        if ($order->fail_uu) {
            $this->log("已经有「UU」失败信息：{$order->fail_uu}，停止「UU」派单");
        } elseif ($order->uu_status != 0) {
            $this->log("订单状态[{$order->uu_status}]不是0，停止「UU」派单");
        } elseif (!$shop->shop_id_uu) {
            $order->fail_uu = "门店不支持UU跑腿";
            $this->log("门店不支持「UU」跑腿，停止「UU」派单");
        } elseif (!$uu_switch) {
            $order->fail_uu = "门店关闭UU跑腿";
            $this->log("门店关闭「UU」跑腿，停止「UU」派单");
        } else {
            $uu = app("uu");
            $check_uu= $uu->orderCalculate($order, $shop);
            $money_uu = (($check_uu['need_paymoney'] ?? 0)) + $add_money;
            $addfee =  isset($check_uu['addfee']) ? intval($check_uu['addfee']) : 0;
            $money_uu_total = $check_uu['total_money'] ?? 0;
            $money_uu_need = $check_uu['need_paymoney'] ?? 0;
            $price_token = $check_uu['price_token'] ?? '';
            if ($addfee > 0) {
                $this->log("「UU」校验订单有加价：{$addfee}");
            }
            if (isset($check_uu['return_code']) && ($check_uu['return_code'] === 'ok') && ($money_uu >= 1) && ($addfee <= 10) ) {
                // 判断用户金额是否满足UU订单
                if ($user->money < ($money_uu + $use_money)) {
                    if ($order->status < 20) {
                        DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_uu + $use_money]));
                    $this->log("用户金额不足发「UU」订单，停止派单");
                    return;
                }
                $order->money_uu = $money_uu;
                $order->money_uu_total = $money_uu_total;
                $order->money_uu_need = $money_uu_need;
                $order->price_token = $price_token;
                $this->services['uu'] = $money_uu;
            } else {
                $uu_error_msg = $check_uu['return_msg'] ?? "UU校验订单失败";
                $order->fail_uu = $uu_error_msg;
                $this->log("「UU」校验订单失败:{$uu_error_msg}，停止「UU」派单");
            }
        }

        // **********************************************************************************
        // ******************************  达  达  跑  腿  ***********************************
        // **********************************************************************************
        // 判断是否开启达达跑腿(是否存在美全达的门店ID，设置是否打开，没用失败信息)
        if ($order->fail_dd) {
            $this->log("已经有「达达」失败信息：{$order->fail_dd}，停止「达达」派单");
        } elseif ($order->dd_status != 0) {
            $this->log("订单状态[{$order->dd_status}]不是0，停止「达达」派单");
        } elseif (!$shop->shop_id_dd) {
            $order->fail_dd = "门店不支持达达跑腿";
            $this->log("门店不支持「达达」跑腿，停止「达达」派单");
        } elseif (!$dd_switch) {
            $order->fail_dd = "门店关闭达达跑腿";
            $this->log("门店关闭「达达」跑腿，停止「达达」派单");
        } else {
            $dada = app("dada");
            $check_dd= $dada->orderCalculate($shop, $order);
            $money_dd = (($check_dd['result']['fee'] ?? 0)) + $add_money;
            if (isset($check_dd['code']) && ($check_dd['code'] === 0) && ($money_dd > 1) ) {
                // 判断用户金额是否满足达达订单
                if ($user->money < ($money_dd + $use_money)) {
                    if ($order->status < 20) {
                        DB::table('orders')->where('id', $this->order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_dd + $use_money]));
                    $this->log("用户金额不足发「达达」订单，停止派单");
                    return;
                }
                $this->dada_order_id = $check_dd['result']['deliveryNo'];
                $order->money_dd = $money_dd;
                $this->services['dada'] = $money_dd;
            } else {
                $dd_error_msg = $check_dd['msg'] ?? "达达校验订单请求失败";
                $order->fail_dd = $dd_error_msg;
                $this->log("「达达」校验订单失败:{$dd_error_msg}，停止「达达」派单");
            }
        }

        // **********************************************************************************
        // *****************************  美  全  达  跑  腿  *********************************
        // **********************************************************************************
        // 判断是否开启美全达跑腿(是否存在美全达的门店ID，设置是否打开，没用失败信息)
        if (false) {
            // $this->log("关闭美全达派单");
        } elseif ($order->fail_mqd) {
            $this->log("已经有「美全达」失败信息：{$order->fail_mqd}，停止「美全达」派单");
        } elseif ($order->mqd_status != 0) {
            $this->log("订单状态[{$order->mqd_status}]不是0，停止「美全达」派单");
        } elseif (!$shop->shop_id_mqd) {
            $order->fail_mqd = "门店不支持美全达跑腿";
            $this->log("门店不支持「美全达」跑腿，停止「美全达」派单");
        } elseif (!$mqd_switch) {
            $order->fail_mqd = "门店关闭美全达跑腿";
            $this->log("门店关闭「美全达」跑腿，停止「美全达」派单");
        } else {
            $meiquanda = app('meiquanda');
            $check_mqd = $meiquanda->orderCalculate($shop, $order);
            $money_mqd = $check_mqd['data']['pay_fee'] ?? 0;
            $money_mqd += $add_money;
            if ($money_mqd > 1) {
                // 判断用户金额是否满足美全达订单
                if ($user->money < ($money_mqd + $use_money)) {
                    if ($order->status < 20) {
                        DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mqd + $use_money]));
                    $this->log("用户金额不足发「美全达」订单，停止派单");
                    return;
                }
                $order->money_mqd = $money_mqd;
                $this->services['meiquanda'] = $money_mqd;
            } else {
                $mqd_error_msg = $check_mqd['msg'] ?? "美全达校验订单失败";
                $order->fail_mqd = $mqd_error_msg;
                $this->log("「美全达」校验订单失败:{$mqd_error_msg}，停止「美全达」派单");
            }

        }

        // **********************************************************************************
        // ******************************  美  团  跑  腿  ***********************************
        // **********************************************************************************
        // 判断美团是否可以接单、并加入数组(美团美的ID，设置是否打开，没用失败信息)
        if ($order->fail_mt) {
            $this->log("已经有「美团」失败信息：{$order->fail_mt}，停止「美团」派单");
        } elseif ($order->mt_status != 0) {
            $this->log("订单状态[{$order->dd_status}]不是0，停止「美团」派单");
        } elseif (!$shop->shop_id) {
            $order->fail_mt = "门店不支持美团跑腿";
            $this->log("门店不支持「美团」跑腿，停止「美团」派单");
        } elseif (!$mt_switch) {
            $order->fail_mt = "门店关闭美团跑腿";
            $this->log("门店关闭「美团」跑腿，停止「美团」派单");
        } else {
            $meituan = app("meituan");
            $check_mt = $meituan->preCreateByShop($shop, $this->order);
            $money_mt = $check_mt['data']['delivery_fee'] ?? 0;
            $money_mt += $add_money;
            if (isset($check_mt['code']) && ($check_mt['code'] === 0) && $money_mt > 1) {
                // 判断用户金额是否满足美团订单
                if ($user->money < ($money_mt + $use_money)) {
                    if ($order->status < 20) {
                        DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_mt + $use_money]));
                    $this->log("用户金额不足发「美团」单，停止派单");
                    return;
                }
                $order->money_mt = $money_mt;
                $this->services['meituan'] = $money_mt;
            } else {
                $mt_error_msg = $check_mt['message'] ?? "美团校验订单失败";
                $order->fail_mt = $mt_error_msg;
                $this->log("「美团」校验订单失败:{$mt_error_msg}，停止「美团」派单");
            }
        }

        // **********************************************************************************
        // ******************************  蜂  鸟  跑  腿  ***********************************
        // **********************************************************************************
        // 判断蜂鸟是否可以接单、并加入数组
        if ($order->fail_fn) {
            $this->log("已经有「蜂鸟」失败信息：{$order->fail_fn}，停止「蜂鸟」派单");
        } elseif ($order->fn_status != 0) {
            $this->log("订单状态[{$order->fn_status}]不是0，停止「蜂鸟」派单");
        } elseif (!$shop->shop_id_fn) {
            $order->fail_fn = "门店不支持蜂鸟跑腿";
            $this->log("门店不支持「蜂鸟」跑腿，停止「蜂鸟」派单");
        } elseif (!$fn_switch) {
            $order->fail_fn = "门店关闭蜂鸟跑腿";
            $this->log("门店关闭「蜂鸟」跑腿，停止「蜂鸟」派单");
        } elseif ($order->type == 11) {
            $order->fail_fn = "药柜订单禁止发送蜂鸟";
            $this->log("药柜订单禁止发送「蜂鸟」，停止「蜂鸟」派单");
        } elseif ((time() > strtotime(date("Y-m-d 22:00:00"))) || (time() < strtotime(date("Y-m-d 09:00:00")))) {
            $order->fail_fn = "蜂鸟拒单";
            $this->log("时间问题-「蜂鸟」拒单，停止「蜂鸟」派单");
        } else {
            $fengniao = app("fengniao");
            $check_fn_res = $fengniao->preCreateOrderNew($shop, $this->order);
            $check_fn = json_decode($check_fn_res['business_data'], true);
            $money_fn = (($check_fn['goods_infos'][0]['actual_delivery_amount_cent'] ?? 0) + ($add_money * 100) ) / 100;
            if ($money_fn > 1) {
                // 判断用户金额是否满足蜂鸟订单
                if ($user->money < ($money_fn + $use_money)) {
                    if ($this->order->status < 20) {
                        DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                    }
                    dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_fn + $use_money]));
                    $this->log("用户金额不足发「蜂鸟」订单，停止派单");
                    return;
                }
                $order->money_fn = $money_fn;
                $this->services['fengniao'] = $money_fn;
            } else {
                $fn_error_msg = $check_fn['goods_infos'][0]['disable_reason'] ?? "蜂鸟校验请求失败";
                $order->fail_fn = $fn_error_msg;
                $this->log("「蜂鸟」校验请求失败:{$fn_error_msg}，停止「蜂鸟」派单");
            }
        }

        // **********************************************************************************
        // ******************************  闪  送  跑  腿  ***********************************
        // **********************************************************************************
        // 判断闪送是否可以接单、并加入数组
        if ($order->fail_ss) {
            $this->log("已经有「闪送」失败信息：{$order->fail_ss}，停止「闪送」派单");
        } elseif ($order->ss_status != 0) {
            $this->log("订单状态[{$order->ss_status}]不是0，停止「闪送」派单");
        // } elseif (!$shop->shop_id_ss) {
        //     $order->fail_ss = "门店不支持闪送跑腿";
        //     $this->log("门店不支持「闪送」跑腿，停止「闪送」派单");
        } elseif (!$ss_switch) {
            $order->fail_ss = "门店关闭闪送跑腿";
            $this->log("门店关闭「闪送」跑腿，停止「闪送」派单");
        } else {
            if (!$zz_ss && !$shop->shop_id_ss) {
                $order->fail_ss = "门店不支持闪送跑腿";
                $this->log("门店不支持「闪送」跑腿，停止「闪送」派单");
            } else {
                if ($zz_ss) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                    $this->log("自助注册「闪送」发单");
                } else {
                    $shansong = app("shansong");
                    $this->log("聚合运力「闪送」发单");
                }
                $check_ss = $shansong->orderCalculate($shop, $order);
                $money_ss = (($check_ss['data']['totalFeeAfterSave'] ?? 0) / 100) + $add_money;
                if (isset($check_ss['status']) && ($check_ss['status'] === 200) && ($money_ss > 1) ) {
                    if (isset($check_ss['data']['feeInfoList']) && !empty($check_ss['data']['feeInfoList'])) {
                        // 判断用户金额是否满足闪送订单
                        if ($user->money < ($money_ss + $use_money)) {
                            if ($order->status < 20) {
                                DB::table('orders')->where('id', $order->id)->update(['status' => 5]);
                            }
                            dispatch(new SendSms($user->phone, "SMS_186380293", [$user->phone, $money_ss + $use_money]));
                            $this->log("用户金额不足发「闪送」单，停止派单");
                            return;
                        }

                        $order->money_ss = $money_ss;
                        $order->ss_order_id = $check_ss['data']['orderNumber'] ?? '';
                        $this->services['shansong'] = $money_ss;
                    }
                } else {
                    $ss_error_msg = $check_ss['msg'] ?? "闪送校验请求失败";
                    $order->fail_ss = $ss_error_msg;
                    $this->log("「闪送」校验请求失败:{$ss_error_msg}，停止「闪送」派单");
                }
            }
        }

        // 仓库ID
        $order->warehouse_id = $this->warehouse;

        // 保存订单信息
        $order->user_id = $user->id;
        $order->add_money = $add_money;
        $order->manager_money = $manager_money;
        $order->save();

        // 没有配送服务商
        if (empty($this->services)) {
            if (
                !$order->mt_status
                && !$order->fn_status
                && !$order->ss_status
                && !$order->mqd_status
                && !$order->dd_status
                && !$order->uu_status
                && !$order->sf_status
            ) {
                DB::table('orders')->where('id', $order->id)->update(['status' => 10]);
            }
            $this->log("暂无运力，停止派单");
            return;
        }

        $ps = $this->getService();

        // 判断是否接单了
        $jiedan_lock = Cache::lock("jiedan_lock:{$order->id}", 1);
        if (!$jiedan_lock->get()) {
            // 获取锁定5秒...
            $this->log("已经操作接单，停止派单");
            return;
        }

        if ($ps === "meituan") {
            if ($this->meituan()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「美团」订单失败");
            }
            return;
        } else if ($ps === "fengniao") {
            if ($this->fengniao()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「蜂鸟」订单失败");
            }
            return;
        } else if ($ps === "shansong") {
            if ($this->shansong($zz_ss)) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「闪送」订单失败");
            }
            return;
        } else if ($ps === "meiquanda") {
            if ($this->meiquanda()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「美全达」订单失败");
            }
            return;
        } else if ($ps === "dada") {
            if ($this->dada()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「达达」订单失败");
            }
            return;
        } else if ($ps === "uu") {
            if ($this->uu()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「UU」订单失败");
            }
            return;
        } else if ($ps === "sf") {
            if ($this->sf()) {
                if (count($this->services) > 1) {
                    dispatch(new CheckSendStatus($this->order, $order_ttl));
                }
            } else {
                $this->log("发送「顺丰」订单失败");
            }
            return;
        }
        $this->log("发送订单失败，没有返回最小平台");

        $lock->release();
    }

    public function uu()
    {
        $order = Order::find($this->order->id);
        $shop_id = $order->warehouse_id ?: $order->shop_id;
        $shop = Shop::find($shop_id);

        if ($order->status > 30) {
            $this->log("不能发送「UU」订单，订单状态：{$order->status},大于30，停止派单");
            return false;
        }

        if ($order->fail_uu) {
            $this->log("已有「UU」错误信息，停止派单");
            return false;
        }

        $uu = app("uu");
        // 发送UU订单
        $result_uu = $uu->addOrder($order, $shop);
        if ($result_uu['return_code'] === 'ok') {
            // 订单发送成功
            $this->log("发送「UU」订单成功|返回参数", [$result_uu]);
            // 写入订单信息
            $update_info = [
                // 'money_uu' => $order->money_uu,
                'uu_order_id' => $result_uu['ordercode'],
                'uu_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 6,
                'order_id' => $this->order->id,
                'des' => '「UU」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「UU」更新创建订单状态成功");
            return true;
        } else {
            $fail_uu = $result_uu['return_msg'] ?? "UU创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_uu' => $fail_uu, 'dd_status' => 3]);
            $this->log("「UU」发送订单失败：{$fail_uu}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「UU」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function sf()
    {
        $order = Order::find($this->order->id);
        $shop_id = $order->warehouse_id ?: $order->shop_id;
        $shop = Shop::find($shop_id);

        if ($order->status > 30) {
            $this->log("不能发送「顺丰」订单，订单状态：{$order->status},大于30，停止派单");
        }

        if ($order->fail_sf) {
            $this->log("已有「顺丰」错误信息，停止派单");
            return false;
        }

        $sf = app("shunfeng");
        // 发送顺丰订单
        $result_sf = $sf->createOrder($order, $shop);
        if ($result_sf['error_code'] === 0) {
            // 订单发送成功
            $this->log("发送「顺丰」订单成功|返回参数", [$result_sf]);
            // 写入订单信息
            $money_sf = (($result_sf['result']['real_pay_money'] ?? 0) / 100) + $this->add_money;
            $update_info = [
                'money_sf' => $money_sf,
                'sf_order_id' => $result_sf['result']['sf_order_id'] ?? $this->order->order_id,
                'sf_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 7,
                'order_id' => $this->order->id,
                'des' => '「顺丰」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「顺丰」更新创建订单状态成功");
            $sf->notifyproductready($order);
            return true;
        } else {
            $fail_sf = $result_sf['error_msg'] ?? "顺丰创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_sf' => $fail_sf, 'sf_status' => 3]);
            $this->log("「顺丰」发送订单失败：{$fail_sf}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「顺丰」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function dada()
    {
        $order = Order::find($this->order->id);

        if ($order->status > 30) {
            $this->log("不能发送「达达」订单，订单状态：{$order->status},大于30，停止派单");
        }

        if ($order->fail_dd) {
            $this->log("已有「达达」错误信息，停止派单");
            return false;
        }

        $dada = app("dada");
        // $money = $this->money_dd;
        // 发送达达订单
        $result_dd = $dada->createOrder($this->dada_order_id);
        if ($result_dd['code'] === 0) {
            // 订单发送成功
            $this->log("发送「达达」订单成功|返回参数", [$result_dd]);
            // 写入订单信息
            $update_info = [
                // 'money_mqd' => $money,
                'dd_order_id' => $this->order->order_id,
                'dd_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 5,
                'order_id' => $this->order->id,
                'des' => '「达达」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「达达」更新创建订单状态成功");
            return true;
        } else {
            $fail_dd = $result_dd['message'] ?? "达达创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_dd' => $fail_dd, 'dd_status' => 3]);
            $this->log("「达达」发送订单失败：{$fail_dd}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「达达」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function meiquanda()
    {
        $order = Order::find($this->order->id);

        if ($order->status > 30) {
            $this->log("不能发送「美全达」订单，订单状态：{$order->status},大于30，停止派单");
        }

        if ($order->fail_mqd) {
            $this->log("已有「美全达」错误信息，停止派单");
            return false;
        }
        $shop_id = $order->warehouse_id ?: $order->shop_id;
        $shop = Shop::find($shop_id);
        // $shop = Shop::find($this->order->shop_id);

        $meiquanda = app("meiquanda");
        // 发送美全达订单
        $result_mqd = $meiquanda->createOrder($shop, $this->order);
        if ($result_mqd['code'] === 100) {
            // 订单发送成功
            $this->log("发送「美全达」订单成功|返回参数", [$result_mqd]);
            $mqd_order_info = $meiquanda->getOrderInfo($result_mqd['data']['trade_no'] ?? "");
            // 写入订单信息
            $update_info = [
                // 'money_mqd' => $money,
                'mqd_order_id' => $result_mqd['data']['trade_no'],
                'mqd_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            if (!empty($mqd_order_info['data']['merchant_pay_fee']) && $mqd_order_info['data']['merchant_pay_fee'] > 0) {
                $money = $mqd_order_info['data']['merchant_pay_fee'] + $this->add_money;
                if ($money > $this->add_money) {
                    $update_info['money_mqd'] = $money;
                    $this->log("「美全达」创建订单成功，金额：{$money}，加价：{$this->add_money}");
                }
            }
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 4,
                'order_id' => $this->order->id,
                'des' => '「美全达」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「美全达」更新创建订单状态成功");
            return true;
        } else {
            $fail_mqd = $result_mqd['message'] ?? "美全达创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_mqd' => $fail_mqd, 'mqd_status' => 3]);
            $this->log("「美全达」发送订单失败：{$fail_mqd}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「美全达」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function meituan()
    {
        $order = Order::find($this->order->id);

        if ($order->status > 30) {
            $this->log("不能发送「美团」订单，订单状态：{$order->status},大于30，停止派单");
        }

        if ($order->fail_mt) {
            $this->log("已有「美团」错误信息，停止派单");
            return false;
        }
        $shop_id = $order->warehouse_id ?: $order->shop_id;
        $shop = Shop::find($shop_id);
        // $shop = Shop::find($this->order->shop_id);

        $meituan = app("meituan");
        // $distance = distanceMoney($this->order->distance);
        // $base = baseMoney($shop->city_level ?: 9);
        // $time_money = timeMoney();
        // $date_money = dateMoney();
        // $weight_money = weightMoney($this->order->goods_weight);

        // $money = $base + $time_money + $date_money + $distance + $weight_money;
        // 发送美团订单
        $result_mt = $meituan->createByShop($shop, $this->order);
        if ($result_mt['code'] === 0) {
            // 订单发送成功
            $this->log("发送「美团」订单成功|返回参数", [$result_mt]);
            // 写入订单信息
            $update_info = [
                // 'money_mt' => $money,
                'mt_order_id' => $result_mt['data']['mt_peisong_id'],
                'mt_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            if (!empty($result_mt['data']['delivery_fee']) && $result_mt['data']['delivery_fee'] > 0) {
                $money = $result_mt['data']['delivery_fee'] + $this->add_money;
                if ($money > $this->add_money) {
                    $update_info['money_mt'] = $money;
                    $this->log("「美团」创建订单成功，金额：{$money}，加价：{$this->add_money}");
                }
            }
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 1,
                'order_id' => $this->order->id,
                'des' => '「美团」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「美团」更新创建订单状态成功");
            return true;
        } else {
            $fail_mt = $result_mt['message'] ?? "美团创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_mt' => $fail_mt, 'mt_status' => 3]);
            $this->log("「美团」发送订单失败：{$fail_mt}|所有平台：", [$this->services]);
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「美团」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function fengniao()
    {
        $order = Order::find($this->order->id);

        if ($order->status > 30) {
            $this->log("不能发送「蜂鸟」订单，订单状态：{$order->status},大于30，停止派单");
        }

        if ($order->fail_fn) {
            $this->log("已有「蜂鸟」错误信息，停止派单");
            return false;
        }

        $shop_id = $order->warehouse_id ?: $order->shop_id;
        $shop = Shop::find($shop_id);
        // $shop = Shop::find($this->order->shop_id);

        $fengniao = app("fengniao");
        $result_fn = $fengniao->createOrder($shop, $this->order);
        if ($result_fn['code'] == 200) {
            // 订单发送成功
            $this->log("发送「蜂鸟」订单成功|返回参数", [$result_fn]);
            $fn_order_info = $fengniao->getOrder($this->order->order_id);
            // 写入订单信息
            $update_info = [
                // 'money_fn' => $money,
                'fn_order_id' => $fn_order_info['data']['tracking_id'] ?? $this->order->order_id,
                'fn_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            if ((!empty($fn_order_info['code'])) && ($fn_order_info['code'] == 200)) {
                if (!empty($fn_order_info['data']['order_total_delivery_cost']) && $fn_order_info['data']['order_total_delivery_cost'] > 0) {
                    $money = $fn_order_info['data']['order_total_delivery_cost'] + $this->add_money;
                    if ($money > $this->add_money) {
                        $update_info['money_fn'] = $money;
                        $this->log("「蜂鸟」创建订单成功，金额：{$money}，加价：{$this->add_money}");
                    }
                }
            }
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 2,
                'order_id' => $this->order->id,
                'des' => '「蜂鸟」跑腿发单',
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「蜂鸟」更新创建订单状态成功");
            return true;
        } else {
            $fail_fn = $result_fn['msg'] ?? "蜂鸟创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_fn' => $fail_fn, 'fn_status' => 3]);
            $this->log("「蜂鸟」发送订单失败：{$fail_fn}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「蜂鸟」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    public function shansong($zz = false)
    {
        $order = Order::find($this->order->id);

        if ($order->status > 30) {
            $this->log("不能发送「闪送」订单，订单状态：{$order->status},大于30，停止派单");
            return false;
        }

        if ($order->fail_ss) {
            $this->log("已有「闪送」错误信息，停止派单");
            return false;
        }

        if ($zz) {
            $this->log("自助注册「闪送」发单");
            $shansong = new ShanSongService(config('ps.shansongservice'));
        } else {
            $shansong = app("shansong");
            $this->log("聚合运力「闪送」发单");
        }

        // 发送闪送订单
        $result_ss = $shansong->createOrderByOrder($order);
        if ($result_ss['status'] === 200) {
            // 订单发送成功
            $this->log("发送「闪送」订单成功|返回参数", [$result_ss]);
            // 写入订单信息
            $update_info = [
                // 'money_ss' => $money,
                'shipper_type_ss' => $zz ? 1 : 0,
                'ss_order_id' => $order->ss_order_id,
                'ss_status' => 20,
                'status' => 20,
                'push_at' => date("Y-m-d H:i:s")
            ];
            DB::table('orders')->where('id', $this->order->id)->update($update_info);
            DB::table('order_logs')->insert([
                'ps' => 3,
                'order_id' => $this->order->id,
                'des' => '「闪送」跑腿发单:' . $order->ss_order_id,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            $this->log("「闪送」更新创建订单状态成功");
            return true;
        } else {
            $fail_ss = $result_ss['msg'] ?? "闪送创建订单失败";
            DB::table('orders')->where('id', $this->order->id)->update(['fail_ss' => $fail_ss, 'ss_status' => 3]);
            $this->log("「闪送」发送订单失败：{$fail_ss}");
            if (count($this->services) > 1) {
                dispatch(new CreateMtOrder($this->order, 30));
            } else {
                $this->log("「闪送」发送订单失败，没有平台了");
            }
        }

        return false;
    }

    /**
     * 发送钉钉异常
     * @param $title
     * @param $description
     */
    public function dingTalk($title, $description)
    {
        $logs = [
            "des" => $description,
            "datetime" => date("Y-m-d H:i:s"),
            "order_id" => $this->order->order_id,
            "id" => $this->order->id
        ];
        $res = $this->dingding->sendMarkdownMsgArray($title, $logs);
        \Log::error('发送订单-'.$title, ["logs" => $logs, "dingding" => $res]);
    }

    /**
     * 配送商价格排序
     * @return array
     */
    public function getService()
    {
        $min = 0;
        $res = "";
        $services = $this->services;
        if (count($services) === 1) {
            return array_keys($services)[0];
        }

        foreach ($services as $k => $v) {
            if ($min === 0) {
                $min = $v;
                $res = $k;
            }

            if ($v < $min) {
                $min = $v;
                $res = $k;
            }
        }

        return $res;
    }
}
