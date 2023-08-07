<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        '70' => '配送完成',
        '90' => '已取消',
    ];

    static $delivery_status_order_info_title_map = [
        '20' => '带抢单',
        '50' => '抢单成功',
        '60' => '配送中',
        '70' => '配送完成',
        '90' => '已取消',
    ];

    static $delivery_platform_map = [
        1 => '美团跑腿',
        2 =>  '蜂鸟',
        3 =>  '闪送',
        4 =>  '美全达',
        5 =>  '达达',
        6 =>  'UU',
        7 =>  '顺丰',
        8 =>  '美团众包',
    ];

    // 足迹记录
    public function tracks()
    {
        return $this->hasMany(OrderDeliveryTrack::class, "delivery_id", "id");
    }
}
