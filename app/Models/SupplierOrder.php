<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SupplierOrder extends Model
{
    protected $fillable = [
        'no',
        'pay_no',
        'address',
        'receive_shop_id',
        'receive_shop_name',
        'shop_id',
        'total_fee',
        'remark',
        'paid_at',
        'payment_method',
        'payment_no',
        'refund_status',
        'refund_no',
        'closed',
        'reviewed',
        'ship_status',
        'ship_data',
        'extra',
        'status',
    ];

    protected $casts = [
        'closed'    => 'boolean',
        'reviewed'  => 'boolean',
        'address'   => 'json',
        'ship_data' => 'json',
        'extra'     => 'json',
    ];

    protected $dates = [
        'paid_at',
    ];

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            // 如果模型的 no 字段为空
            if (!$model->no) {
                // 调用 findAvailableNo 生成订单流水号
                $model->no = static::findAvailableNo();
                // 如果生成失败，则终止创建订单
                if (!$model->no) {
                    return false;
                }
            }
        });
    }

    protected function asJson($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierOrderItem::class, "order_id");
    }

    public function shop()
    {
        return $this->belongsTo(SupplierUser::class, "shop_id");
    }

    /**
     * 订单号生成
     * @author zhangzhen
     * @data dateTime
     */
    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        $prefix = substr($prefix, 2);
        for ($i = 0; $i < 20; $i++) {
            // 随机生成 6 位的数字
            $no = $prefix.str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
        }
        Log::warning('find order no failed');

        return false;
    }

    /**
     * 支付订单号生成
     * @author zhangzhen
     * @data dateTime
     */
    public static function findAvailablePayNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        $prefix = '8' . substr($prefix, 2);
        for ($i = 0; $i < 20; $i++) {
            // 随机生成 6 位的数字
            $no = $prefix.str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
        }
        Log::warning('find order no failed');

        return false;
    }
}
