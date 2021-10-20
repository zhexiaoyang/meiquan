<?php

namespace App\Console\Commands;

use App\Models\OnlineShop;
use App\Models\OnlineShopLog;
use Illuminate\Console\Command;

class CalculateOnlineShopStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate-online-statistic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算-外卖上线门店-统计';

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
        // 在命令行打印一行信息
        $this->info("开始计算...");
        $shops = OnlineShop::select("id","shop_id","status","created_at")
            ->where("status","<", 40)->get();
        $insert = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $insert[] = [
                    "shop_id" => $shop->shop_id,
                    "online_shop_id" => $shop->id,
                    "status" => $shop->status,
                    "shop_time" => $shop->created_at,
                    "date" => date("Y-m-d", time() - 86400),
                ];
            }
        }
        if (!empty($insert)) {
            OnlineShopLog::insert($insert);
        }
        $this->info("成功生成！");
    }
}
