<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $switch = true;
        $number = 1;
        // $str = '1';

        while ($switch) {
            $taozi = app('taozi');
            $res = $taozi->order($number);
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
                                    $data = WmPrescription::query()->create($v);
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
        // \Log::info($str);
    }
}
