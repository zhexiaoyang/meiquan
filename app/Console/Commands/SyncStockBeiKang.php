<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStockBeiKang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-stock-beikang';

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
        $minkang = app("minkang");

        // --------------------- 桐君阁大药房（北城天街店）:5910555 ---------------------
        $this->info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-开始......');
        Log::info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-开始......');
        $data = DB::connection('beikang')
            ->select("SELECT 门店商品编码 as id, 库存 as stock FROM [dbo].[商品库存清单] WHERE [门店编码] = N'38店' AND [药品条形码] <> '' AND [药品条形码] IS NOT NULL");
        if (!empty($data)) {
            $data = array_chunk($data, 200);
            foreach ($data as $items) {
                $stock_data = [];
                foreach ($items as $item) {
                    $stock_data[] = [
                        'app_medicine_code' => trim($item->id),
                        'stock' => (int) $item->stock,
                    ];
                }

                $params['app_poi_code'] = '5910555';
                $params['medicine_data'] = json_encode($stock_data);
                $minkang->medicineStock($params);
            }
        }
        $this->info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-结束......');
        Log::info('门店「桐君阁大药房（北城天街店）:5910555」库存同步-结束......');
    }
}
