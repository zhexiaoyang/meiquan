<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GetMinkangShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get-minkang-shops';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取民康美团门店';

    protected $mt = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->mt = app("minkang");
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
        $this->info("开始获取...");
        $shop_ids_arr = $this->getShopIds();
        if (!empty($shop_ids_arr)) {
            sort($shop_ids_arr);
            $shop_ids = array_chunk($shop_ids_arr, 200);
            \Log::info("aaa", $shop_ids);
        }
        $this->info("成功生成！");
    }

    public function getShopIds(): array
    {
        $shop_ids_res = $this->mt->getShopIds();

        return empty($shop_ids_res['data']) ? [] : $shop_ids_res['data'];
    }
}
