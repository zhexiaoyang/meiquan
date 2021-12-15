<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ["user_id","own_id","shop_id","mt_shop_id","shop_id_fn","shop_id_ss","shop_id_sf","shop_name",
        "category","second_category","contact_name","contact_phone","shop_address","city_level","city_level_fn","city",
        "citycode","shop_lng","shop_lat","coordinate_type","delivery_service_codes","business_hours","status","auth",
        "auth_error","material","material_error","mtwm","ele","jddj","apply_auth_time","adopt_auth_time","area","manager_id",
        "yyzz","yyzz_img","yyzz_name",
        "apply_material_time","adopt_material_time", "running_select","province","district","chufang_status"];

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

    /**
     * 门店付款账号
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 门店所属用户
     */
    public function own()
    {
        return $this->belongsTo(User::class, 'own_id', 'id');
    }

    /**
     * 申请三方ID记录
     * @data 2021/12/1 2:21 下午
     */
    public function apply_three_id()
    {
        return $this->hasOne(ShopThreeId::class, "shop_id", "id");
    }

    /**
     * 认证门店
     * @data 2021/12/1 2:15 下午
     */
    public function auth_shop()
    {
        return $this->hasOne(ShopAuthentication::class, "shop_id", "id");
    }

    public function change_shop()
    {
        return $this->hasOne(ShopAuthenticationChange::class, "shop_id", "id");
    }

    /**
     * 外卖资料
     * @data 2021/12/15 3:19 下午
     */
    public function online_shop()
    {
        return $this->hasOne(OnlineShop::class, "shop_id", "id");
    }

    /**
     * 签过线下处方合同的门店
     * @data 2021/12/15 3:19 下午
     */
    public function prescription()
    {
        return $this->hasOne(ContractOrder::class, "shop_id", "id")->where('contract_id',4);
    }

    /**
     * 签过线下处方合同的门店
     * @data 2021/12/15 3:19 下午
     */
    public function pharmacists()
    {
        return $this->hasMany(Pharmacist::class, "shop_id", "id");
    }

    public function manager()
    {
        return $this->belongsTo(User::class, "manager_id", "id");
    }

    public function getStatusLabelAttribute()
    {
        return $this->status_data[$this->status] ?? '状态错误';
    }
}
