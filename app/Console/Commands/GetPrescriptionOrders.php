<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPrescriptionOrders extends Command
{
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
        $start_date = '2021-11-01';
        $last = WmPrescription::orderByDesc('id')->first();
        if ($last) {
            $start_date = $last->rpCreateTime;
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
            Log::info("[拉取桃子处方订单]-拉取日期：{$date}");
            $taozi = app('taozi');
            $switch = true;
            $number = 1;
            while ($switch) {
                $res = $taozi->order($number, 100, $date);
                $data = $res['data'] ?? [];

                if (!empty($data)) {
                    foreach ($data as $v) {
                        if (WmPrescription::query()->where('outOrderID', $v['outOrderID'])->exists()) {
                            $switch = false;
                            break;
                        } else {
                            $shop_id = $v['storeID'];
                            $order_id = $v['outOrderID'];
                            $v['platform'] = 1;
                            if ($shop_id && $order_id) {
                                if ($shop = Shop::query()->select('id','user_id')->where('chufang_mt', $shop_id)->first()) {
                                    DB::transaction(function () use ($v, $shop, $order_id) {
                                        $v['shop_id'] = $shop->id;
                                        $current_user = DB::table('users')->find($shop->user_id);
                                        $money = 1.5;
                                        $_tmp = [
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
                                        $data = WmPrescription::query()->create($_tmp);
                                        if ($v['orderStatus'] == '已完成') {
                                            UserOperateBalance::create([
                                                "user_id" => $current_user->id,
                                                "money" => $money,
                                                "type" => 2,
                                                "before_money" => $current_user->operate_money,
                                                "after_money" => ($current_user->operate_money - $money),
                                                "description" => "[美团]处方单审方：" . $order_id,
                                                "shop_id" => $shop->id,
                                                "tid" => $data->id,
                                                'order_at' => $v['rpCreateTime']
                                            ]);
                                            // 减去用户运营余额
                                            DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $money);
                                        }
                                    });
                                } else {
                                    $v['status'] = 2;
                                    $v['reason'] = '未开通处方';
                                    WmPrescription::query()->create($v);
                                }
                            } else {
                                $v['status'] = 2;
                                $v['reason'] = '门店ID或订单号不存在';
                                WmPrescription::query()->create($v);
                            }
                        }
                    }
                }
                $number++;
                if (($number > 300) || empty($data)) {
                    $switch = false;
                }
            }
        }
    }
}
