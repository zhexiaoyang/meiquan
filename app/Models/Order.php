<?php

namespace App\Models;

use App\Exceptions\HttpException;
use App\Services\Delivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    protected $casts =[
        'exception_time' => 'datetime'
    ];

    public $status_data = [
        200 => '未发送',
        -2 => '等待发送',
        -1 => '发送失败',
        0 => '待调度',
        20 => '已接单',
        30 => '已取货',
        50 => '已送达',
        99 => '已取消'
    ];

    protected $fillable = [
        'wm_poi_name','wm_id','caution',
        'delivery_id','order_id','peisong_id','shop_id','delivery_service_code','receiver_name',
        'receiver_address','receiver_phone','receiver_lng','receiver_lat','coordinate_type','goods_value',
        'goods_height','goods_width','goods_length','goods_weight','goods_pickup_info','goods_delivery_info',
        'expected_pickup_time','expected_delivery_time','order_type','day_seq','platform','note','type','status','failed',
        'courier_name','courier_phone','cancel_reason_id','cancel_reason','exception_id','exception_code',
        'exception_descr','exception_time','distance','money','base_money','distance_money','weight_money',
        'time_money','date_money','money_mt','money_fn','money_ss','ss_order_id','fail_mt','fail_fn','fail_ss','ps',
        'mt_status','money_mt','fn_status','money_fn','ss_status','money_ss','user_id','tool','profit',
        'mqd_status','money_mqd','fail_mqd','dd_status','money_dd','fail_dd',
        'uu_status','money_uu','fail_uu','money_uu_total','money_uu_need',
        'courier_lng', 'courier_lat','pay_status','pay_at','refund_at','add_money','manager_money',
        'receive_at','take_at','over_at','cancel_at','push_at','created_at','updated_at'
    ];

    public function shop() {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }

    public function warehouse() {
        return $this->belongsTo(Shop::class, 'warehouse_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(OrderDetail::class, "order_id", "id");
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class, "order_id", "id");
    }

    public function deduction()
    {
        return $this->hasMany(OrderDeduction::class, 'order_id');
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
            if ($shop = Shop::where('shop_id', $model->shop_id)->first()) {
                $model->distance = getShopDistanceV4($shop, $model->receiver_lng, $model->receiver_lat);
            }
        });

        static::saved(function ($order) {
            if (($order->status === 70) && !ManagerProfit::where('order_id', $order->id)->first()) {
                Log::info("[完成订单监听]-[订单ID：{$order->id}，订单号：{$order->order_id}]");
                if ($order->ps != 4) {
                    $manager_ids = User::whereHas("roles", function ($query) {
                        $query->where('name', 'city_manager');
                    })->orderByDesc('id')->pluck("id")->toArray();
                    $_users = DB::table('user_has_shops')->where('shop_id', $order->shop_id)->get();
                    if (!empty($_users)) {
                        foreach ($_users as $_user) {
                            if (in_array($_user->user_id, $manager_ids)) {
                                $order_profit = 1;
                                $manager = User::find($_user->user_id);
                                if (!$manager_return = UserReturn::where('user_id', $_user->user_id)->first()) {
                                    Log::info("未找到城市经理收益，收益未结算|门店ID：{$order->shop_id}|经理ID：{$_user->user_id}");
                                    break;
                                }
                                $return_type = $manager_return->running_type;
                                if ($return_type == 1) {
                                    $return_value = $manager_return->running_value1;
                                    $profit = $return_value;
                                } else {
                                    $return_value = $manager_return->running_value2;
                                    $profit = $order_profit * $return_value;
                                }
                                if ($profit <= 0) {
                                    Log::info("收益小于等于0，收益未结算|订单ID：{$order->id}|门店ID：{$order->shop_id}|经理ID：{$_user->user_id}");
                                    break;
                                }
                                $profit_data = [
                                    'user_id' => $manager->id,
                                    'order_id' => $order->id,
                                    'order_no' => $order->order_id,
                                    'shop_id' => $order->shop_id,
                                    'order_profit' => $order_profit,
                                    'profit' => $profit,
                                    'return_type' => $return_type,
                                    'return_value' => $return_value,
                                    'type' => 1,
                                    'created_at' => $order->over_at,
                                    'updated_at' => $order->over_at,
                                ];
                                ManagerProfit::create($profit_data);
                                break;
                            }
                        }
                    }
                } else {
                    Log::info("[完成订单监听]-[订单ID：{$order->id}，订单号：{$order->order_id}]-[美全达配送，不算收益]");
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
            if (!static::query()->where('order_id', $order_id)->exists()) {
                return $order_id;
            }
        }
        return false;
    }
}
