<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\WmOrder;
use App\Models\WmOrderExtra;
use App\Models\WmOrderItem;
use App\Models\WmOrderReceive;
use Illuminate\Console\Command;

class BackupOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup-orders';

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
        $day = date("Y-m-d", strtotime('-93 day'));
        // 同步跑腿订单
        $pt_order_last = Order::select('id')->where('created_at', '<', $day)->orderByDesc('id')->first();
        if ($pt_order_last && !empty($pt_order_last->id)) {
            // 同步跑腿订单-主表
            Order::where('created_at', '<', $day)->where('id', '<=', $pt_order_last->id)->chunk(200, function ($orders) {
                \DB::table('orders_backups')->insert($orders->toArray());
            });
            // 删除跑腿订单-主表
            Order::where('created_at', '<', $day)->where('id', '<=', $pt_order_last->id)->delete();
            // ---------------------------------------------------------------------------------------------------------
            // 同步跑腿订单-操作日志表
            OrderLog::where('order_id', '<=', $pt_order_last->id)->chunk(300, function ($logs) {
                \DB::table('order_logs_backups')->insert($logs->toArray());
            });
            // 删除跑腿订单-操作日志表
            OrderLog::where('order_id', '<=', $pt_order_last->id)->delete();
        }
        // -------------------------------------------------------------------------------------------------------------
        // 同步跑腿订单
        $order_last = WmOrder::select('id')->where('created_at', '<', $day)->orderByDesc('id')->first();
        if ($order_last && !empty($order_last->id)) {
            // 同步外卖订单-主表
            WmOrder::where('created_at', '<', $day)->where('id', '<=', $order_last->id)->chunk(200, function ($orders) {
                \DB::table('wm_orders_backups')->insert($orders->toArray());
            });
            // 删除外卖订单-主表
            WmOrder::where('created_at', '<', $day)->where('id', '<=', $order_last->id)->delete();
            // ---------------------------------------------------------------------------------------------------------
            // 同步外卖订单-商品表
            WmOrderItem::where('order_id', '<=', $order_last->id)->chunk(300, function ($items) {
                \DB::table('wm_order_items_backups')->insert($items->toArray());
            });
            // 删除外卖订单-商品表
            WmOrderItem::where('order_id', '<=', $order_last->id)->delete();
            // ---------------------------------------------------------------------------------------------------------
            // 同步外卖订单-对账表
            WmOrderReceive::where('order_id', '<=', $order_last->id)->chunk(300, function ($items) {
                \DB::table('wm_order_receives_backups')->insert($items->toArray());
            });
            // 删除外卖订单-对账表
            WmOrderReceive::where('order_id', '<=', $order_last->id)->delete();
            // ---------------------------------------------------------------------------------------------------------
            // 同步外卖订单-优惠表
            WmOrderExtra::where('order_id', '<=', $order_last->id)->chunk(300, function ($items) {
                \DB::table('wm_order_extras_backups')->insert($items->toArray());
            });
            // 删除外卖订单-优惠表
            WmOrderExtra::where('order_id', '<=', $order_last->id)->delete();
        }
    }
}
