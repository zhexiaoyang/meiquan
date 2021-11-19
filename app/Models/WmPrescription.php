<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmPrescription extends Model
{
    protected $fillable = ['clientID','clientName','storeID','storeName','outOrderID','outRpId','outDoctorName',
        'patientName','patientSex','rpStatus','orderStatus','reviewStatus','rejectReason','rpCreateTime','status',
        'reason','platform','shop_id','money'];


    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            // 如果模型的 no 字段为空
            if (!$model->outOrderID) {
                // 调用 findAvailableNo 生成订单流水号
                $model->outOrderID = static::findAvailableNo();
                // 如果生成失败，则终止创建订单
                if (!$model->outOrderID) {
                    return false;
                }
            }
        });
    }

    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = substr(date('YmdHis'), 2);
        for ($i = 0; $i < 10; $i++) {
            // 随机生成 6 位的数字
            $order_id = $prefix.str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('outOrderID', $order_id)->exists()) {
                return $order_id;
            }
        }
        return false;
    }
}
