<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class OrderDelivery extends Model
{
    protected $fillable = ['shop_id','warehouse_id','order_id','wm_id','order_no','three_order_no','bill_no','platform',
        'type','day_seq','money','original','coupon','insurance','tip','distance','weight','remark','delivery_name',
        'delivery_phone','delivery_lng','delivery_lat','is_payment','is_refund','status','track','send_at','arrival_at',
        'atshop_at','pickup_at','finished_at','cancel_at','paid_at','refund_at',
        'user_id','add_money','addfee'
    ];

    static $delivery_status_order_list_title_map = [
        '20' => '发起配送',
        '50' => '抢单成功',
        '60' => '配送中',
        '70' => '已完成',
        '75' => '已完成',
        '99' => '已取消',
    ];

    static $delivery_status_order_info_title_map = [
        '20' => '待抢单',
        '50' => '抢单成功',
        '60' => '配送中',
        '70' => '已完成',
        '75' => '已完成',
        '99' => '已取消',
    ];

    static $delivery_status_order_info_description_map = [
        '20' => '下单成功，等待骑手接单',
        '50' => '订单已进入配送中，点击查看配送动态',
        '60' => '订单已进入配送中，点击查看配送动态',
        '70' => '订单已完成',
        '75' => '订单已完成',
        '99' => '订单已取消',
    ];

    static $delivery_platform_map = [
        0 => '平台运力',
        1 => '美团跑腿',
        2 =>  '蜂鸟',
        3 =>  '闪送',
        4 =>  '美全达',
        5 =>  '达达',
        6 =>  'UU',
        7 =>  '顺丰',
        8 =>  '美团众包',
        200 =>  '自配送',
    ];

    // 足迹记录
    public function tracks()
    {
        return $this->hasMany(OrderDeliveryTrack::class, "delivery_id", "id");
    }

    public static function cancel_log ($order_id, $platform, $action) {
        Log::info($action . "cancel_log取消{$platform}跑腿-开始|order_id:{$order_id}");
        // 顺丰跑腿运力
        $delivery = OrderDelivery::where('order_id', $order_id)->where('platform', $platform)->where('status', '<=', 70)->orderByDesc('id')->first();
        // 写入顺丰取消足迹
        if ($delivery) {
            try {
                $delivery->update([
                    'status' => 99,
                    'cancel_at' => date("Y-m-d H:i:s"),
                    'track' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                ]);
                OrderDeliveryTrack::firstOrCreate(
                    [
                        'delivery_id' => $delivery->id,
                        'status' => 99,
                        'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    ], [
                        'order_id' => $delivery->order_id,
                        'wm_id' => $delivery->wm_id,
                        'delivery_id' => $delivery->id,
                        'status' => 99,
                        'status_des' => OrderDeliveryTrack::TRACK_STATUS_CANCEL,
                    ]
                );
            } catch (\Exception $exception) {
                Log::info($action . "cancel_log取消{$platform}跑腿-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
                // $this->ding_error("众包取消顺丰-写入新数据出错|{$order->order_id}|" . date("Y-m-d H:i:s"));
            }
        } else {
            Log::info($action . "cancel_log取消{$platform}跑腿-写入新数据出错-未找到配送记录|order_id:{$order_id}");
            // $this->ding_error("未找到配送记录-众包取消顺丰|{$order->order_id}|" . date("Y-m-d H:i:s"));
        }
    }

    public static function finish_log ($order_id, $platform, $action) {
        Log::info($action . "finish_log完成{$platform}跑腿-开始|order_id:{$order_id}");
        // 跑腿运力
        $delivery = OrderDelivery::where('order_id', $order_id)->where('platform', $platform)->where('status', '<=', 70)->orderByDesc('id')->first();
        // 写入完成足迹
        if ($delivery) {
            try {
                $delivery->update([
                    'status' => 70,
                    'finished_at' => date("Y-m-d H:i:s"),
                    'track' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                ]);
                OrderDeliveryTrack::firstOrCreate(
                    [
                        'delivery_id' => $delivery->id,
                        'status' => 70,
                        'status_des' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                    ], [
                        'order_id' => $delivery->order_id,
                        'wm_id' => $delivery->wm_id,
                        'delivery_id' => $delivery->id,
                        'status' => 70,
                        'status_des' => OrderDeliveryTrack::TRACK_STATUS_FINISH,
                    ]
                );
            } catch (\Exception $exception) {
                Log::info($action . "finish_log完成{$platform}跑腿-写入新数据出错", [$exception->getFile(),$exception->getLine(),$exception->getMessage(),$exception->getCode()]);
            }
        } else {
            Log::info($action . "finish_log完成{$platform}跑腿-写入新数据出错-未找到配送记录|order_id:{$order_id}");
        }
    }
}
