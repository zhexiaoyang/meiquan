<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCodeJIShiTang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-code-jishitang';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $this->info('------------济世堂绑定编码------------');
        $minkang = app("minkang");
        $meiquan = app("meiquan");

        // 8982389
        $this->info('门店「8982389」同步-开始......');
        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT product_id as id,upc FROM [dbo].[v_store_m_mtxs] WHERE [shop_id] = N'0028' AND [upc] <> '' AND [upc] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $code_data = [];
                foreach ($items as $item) {
                    $code_data[] = [
                        'upc' => $item->upc,
                        'app_medicine_code_new' => $item->id,
                    ];
                }

                // 绑定商品编码
                $params['app_poi_code'] = '8982389';
                $params['medicine_data'] = json_encode($code_data);
                $meiquan->medicineCodeUpdate($params);
            }
        }
        $this->info('门店「8982389」同步-结束......');

    }
}
