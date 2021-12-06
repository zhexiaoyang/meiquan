<?php

namespace App\Console\Commands;

use App\Models\SupplierOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetSupplierOrderComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'set-supplier-order-complete';

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
        $this->info("商城订单设置完成|开始--------");
        $date = date("Y-m-d",strtotime("-7 day"));
        $this->info("商城订单设置完成|日期：{$date}");
        Log::info("商城订单设置完成|日期：{$date}");
        $res = SupplierOrder::query()->where([
            ['status', 50],
            ['deliver_at', '<=', $date],
        ])->update(['status' => 70, 'completion_at' => date("Y-m-d H:i:s")]);

        $this->info("商城订单设置完成|操作数量：{$res}");
        Log::info("商城订单设置完成|操作数量：{$res}");
        $this->info("商城订单设置完成|结束--------");
    }
}
