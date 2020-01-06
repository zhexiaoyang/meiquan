<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $casts =[
        'exception_time' => 'datetime'
    ];

    public $status_data = [
        -1 => '发送失败',
        0 => '待调度',
        20 => '已接单',
        30 => '已取货',
        50 => '已送达',
        99 => '已取消'
    ];

    protected $fillable = ['delivery_id','order_id','shop_id','delivery_service_code','receiver_name',
        'receiver_address','receiver_phone','receiver_lng','receiver_lat','coordinate_type','goods_value',
        'goods_height','goods_width','goods_length','goods_weight','goods_pickup_info',
        'goods_delivery_info','expected_pickup_time','expected_delivery_time','order_type','poi_seq',
        'note','cash_on_delivery','cash_on_pickup','invoice_title','mt_peisong_id', 'courier_name',
        'courier_phone', 'cancel_reason_id', 'cancel_reason','status','failed'];

    public function shop() {
        return $this->belongsTo(Shop::class);
    }


    public function getStatusLabelAttribute()
    {
        return $this->status_data[$this->status] ?? '状态错误';
    }

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            // 如果模型的 no 字段为空
            if (!$model->order_id) {
                // 调用 findAvailableNo 生成订单流水号
                $model->order_id = static::findAvailableNo();
                // 如果生成失败，则终止创建订单
                if (!$model->order_id) {
                    return false;
                }
            }
            if (!$model->delivery_id) {
                $model->delivery_id = $model->order_id;
            }
        });
    }


    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        for ($i = 0; $i < 10; $i++) {
            // 随机生成 6 位的数字
            $order_id = $prefix.str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('order_id', $order_id)->exists()) {
                return $order_id;
            }
        }
        return false;
    }
}
