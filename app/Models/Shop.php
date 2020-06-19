<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['user_id','shop_id','shop_name','category','second_category','contact_name','contact_phone',
        'shop_address','shop_lng','shop_lat','coordinate_type','delivery_service_codes','business_hours','status','city','citycode'];

    protected $casts = [
        'business_hours' => 'json',
    ];

    public $status_data = [
        -10 => '审核驳回',
        0 => '等待提交',
        10 => '等待审核',
        20 => '审核通过',
        30 => '创建成功',
        40 => '上线可发单'
    ];

    public function range()
    {
        return $this->hasOne(ShopRange::class);
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute()
    {
        return $this->status_data[$this->status] ?? '状态错误';
    }
}
