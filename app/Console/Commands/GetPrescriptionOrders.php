<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsNew;
use App\Models\Shop;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPrescriptionOrders extends Command
{
    protected $money = 1.5;
    protected $expend = 0.8;
    protected $income = 0.7;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get-prescription-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取桃子医院处方订单';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $start_date = '2021-12-3';
        $last = WmPrescription::where('platform', 1)->orderByDesc('id')->first();
        if ($last) {
            $start_date = substr($last->rpCreateTime, 0, 10);
        }
        $date_arr = [];
        if (strtotime($start_date) >= (strtotime(date("Y-m-d"))) + 86400) {
            Log::info("[拉取桃子处方订单]-失败：最后时间{$start_date}");
            return false;
        }
        array_push($date_arr, $start_date);
        while (strtotime($start_date) < strtotime(date("Y-m-d"))) {
            $start_date = date("Y-m-d", strtotime($start_date) + 86400);
            array_push($date_arr, $start_date);
        }
        Log::info("[拉取桃子处方订单]-所有拉取日期", $date_arr);

        foreach ($date_arr as $date) {
            $log = "[拉取桃子处方订单]-拉取日期：{$date}";
            $this->info($log);
            Log::info($log);
            $i = 0;
            $o2o = 0;
            $b2c = 0;
            $taozi = app('taozi');
            $switch = true;
            $number = 1;
            while ($switch) {
                $res = $taozi->order($number, 100, $date);
                $data = $res['data'] ?? [];

                if (!empty($data)) {
                    foreach ($data as $v) {
                        if (WmPrescription::where('outOrderID', $v['outOrderID'])->exists()) {
                            $switch = false;
                            break;
                        } else {
                            $i++;
                            if ($v['clientName'] == '美全科技B2C') {
                                $b2c++;
                                continue;
                            }
                            $o2o++;
                            $shop_id = $v['storeID'];
                            $order_id = $v['outOrderID'];
                            $v['platform'] = 1;
                            if ($shop_id && $order_id) {
                                if ($shop = Shop::select('id','user_id')->where('chufang_mt', $shop_id)->orderByDesc('id')->first()) {
                                    DB::transaction(function () use ($v, $shop, $order_id) {
                                        $v['shop_id'] = $shop->id;
                                        $current_user = DB::table('users')->find($shop->user_id);
                                        $_tmp = [
                                            'money' => $this->money,
                                            'expend' => $this->expend,
                                            'income' => $this->income,
                                            'status' => 1,
                                            'platform' => 1,
                                            'shop_id' => $shop->id,
                                            'clientID' => $v['clientID'] ?? '',
                                            'clientName' => $v['clientName'] ?? '',
                                            'storeID' => $v['storeID'] ?? '',
                                            'storeName' => $v['storeName'] ?? '',
                                            'outOrderID' => $v['outOrderID'] ?? '',
                                            'outRpId' => $v['outRpId'] ?? '',
                                            'outDoctorName' => $v['outDoctorName'] ?? '',
                                            'orderStatus' => $v['orderStatus'] ?? '',
                                            'reviewStatus' => $v['reviewStatus'] ?? '',
                                            'orderCreateTime' => $v['orderCreateTime'] ?? '',
                                            'rpCreateTime' => $v['rpCreateTime'] ?? '',
                                        ];
                                        $data = WmPrescription::create($_tmp);
                                        if ($v['orderStatus'] == '已完成' || $v['orderStatus'] == '进行中') {
                                            UserOperateBalance::create([
                                                "user_id" => $current_user->id,
                                                "money" => $this->money,
                                                "type" => 2,
                                                "before_money" => $current_user->operate_money,
                                                "after_money" => ($current_user->operate_money - $this->money),
                                                "description" => "处方单审方：" . $order_id,
                                                "shop_id" => $shop->id,
                                                "tid" => $data->id,
                                                'order_at' => $v['rpCreateTime']
                                            ]);
                                            // 减去用户运营余额
                                            DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $this->money);
                                            $_user = DB::table('users')->find($current_user->id);
                                            if ($_user->operate_money < 50) {
                                                $phone = $_user->phone;
                                                $lock = Cache::lock("send_sms_chufang:{$phone}", 3600);
                                                if ($lock->get()) {
                                                    Log::info("处方余额不足发送短信：{$phone}");
                                                    dispatch(new SendSmsNew($phone, "SMS_227744641", ['money' => 50, 'phone' => '15843224429']));
                                                } else {
                                                    Log::info("今天已经发过短信了：{$phone}");
                                                }
                                            }
                                        }
                                    });
                                } else {
                                    if ($v['orderStatus'] == '已取消') {
                                        $v['status'] = 1;
                                        WmPrescription::create($v);
                                    } else {
                                        $v['money'] = $this->money;
                                        $v['expend'] = $this->expend;
                                        $v['income'] = $this->income;
                                        $v['status'] = 2;
                                        $v['platform'] = 1;
                                        $v['reason'] = '未开通处方';
                                        WmPrescription::create($v);
                                    }
                                }
                            } else {
                                $v['money'] = $this->money;
                                $v['expend'] = $this->expend;
                                $v['income'] = $this->income;
                                $v['status'] = 2;
                                $v['platform'] = 1;
                                $v['reason'] = '门店ID或订单号不存在';
                                WmPrescription::create($v);
                            }
                        }
                    }
                }
                $number++;
                if (($number > 300) || empty($data)) {
                    $switch = false;
                }
            }
            $log = "[拉取桃子处方订单]-拉取日期：{$date}结束 | 一共：{$i} 条 | B2C：{$b2c}条 | O2O：{$o2o}条";
            $this->info($log);
            Log::info($log);
        }
    }
}
