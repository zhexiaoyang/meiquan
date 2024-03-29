<?php

namespace App\Models;

use App\Exceptions\HttpException;
use App\Services\Delivery;
use App\Task\TakeoutOrderVoiceNoticeTask;
use Hhxsv5\LaravelS\Swoole\Task\Task;
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

    public $status_map = [
        0 =>'新订单',
        3 => '[预约]待呼叫',
        5 => '余额不足',
        7 => '取消呼叫',
        8 => '即将呼叫',
        10 => '暂无运力',
        20 => '待接单',
        50 => '待取货',
        60 => '配送中',
        70 => '已完成',
        75 => '已完成',
        80 => '异常',
        99 => '已取消',
    ];

    protected $fillable = [
        'wm_poi_name','wm_id','caution','estimate_arrival_time','poi_receive',
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
        'zb_status','money_zb','fail_zb','money_zb','service_fee',
        'courier_lng', 'courier_lat','pay_status','pay_at','refund_at','add_money','manager_money',
        'receive_at','take_at','over_at','cancel_at','push_at','created_at','updated_at',
        'shipper_type_ss','shipper_type_dd','shipper_type_sf','expected_send_time','pick_type','post_back','ignore',
        'ps_type'
    ];

    // 跑腿运力记录
    public function deliveries()
    {
        return $this->hasMany(OrderDelivery::class, "order_id", "id");
    }

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

    public function products()
    {
        return $this->hasMany(WmOrderItem::class, "order_id", "wm_id");
    }

    public function order()
    {
        return $this->belongsTo(WmOrder::class, "wm_id", "id");
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
        });

        // static::created(function ($model) {
        //     if ($shop = Shop::where('shop_id', $model->shop_id)->first()) {
        //         $model->distance = getShopDistanceV4($shop, $model->receiver_lng, $model->receiver_lat);
        //     }
        // });

        static::saved(function ($order) {
            if ($order->status === 50) {
                Task::deliver(new TakeoutOrderVoiceNoticeTask(11, $order->user_id), true);
            }
            if ($order->status === 70) {
                if (!ManagerProfit::where('order_id', $order->id)->first()) {
                    Log::info("[完成订单监听]-[订单ID：{$order->id}，订单号：{$order->order_id}]");
                    if ($order->ps != 4 && $order->ps != 200) {
                        $manager_ids = User::whereHas("roles", function ($query) {
                            $query->where('name', 'city_manager');
                        })->orderByDesc('id')->pluck("id")->toArray();
                        $_users = DB::table('user_has_shops')->where('shop_id', $order->shop_id)->get();
                        if (!empty($_users)) {
                            foreach ($_users as $_user) {
                                if (in_array($_user->user_id, $manager_ids)) {
                                    $order_profit = $order->profit;
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
                    // Task::deliver(new TakeoutOrderVoiceNoticeTask(14, $order->user_id), true);
                }
                if ($wm_order = WmOrder::where('is_vip', 1)->where('id', $order->wm_id)->first()) {
                    if (!VipBillItem::where('trade_type', 101)->where('order_id', 101)->exists()) {
                        if ($shop = Shop::find($order->shop_id)) {
                            // VIP门店各方利润百分比
                            $commission = $shop->vip_commission;
                            $commission_manager = $shop->vip_commission_manager;
                            $commission_operate = $shop->vip_commission_operate;
                            $commission_internal = $shop->vip_commission_internal;
                            $business = 100 - $commission - $commission_manager - $commission_operate - $commission_internal;
                            // 订单收入（负值）
                            $poi_receive = 0 - $order->money;
                            // 总收入
                            $total = $poi_receive;
                            $vip_city = sprintf("%.2f",$total * $commission_manager / 100);
                            $vip_operate = sprintf("%.2f", $total * $commission_operate / 100);
                            $vip_internal = sprintf("%.2f",$total * $commission_internal / 100);
                            $vip_business = sprintf("%.2f",$total * $business / 100);
                            $vip_company = sprintf("%.2f",$total - $vip_operate - $vip_city - $vip_internal - $vip_business);
                            $item = [
                                'shop_id' => $wm_order->shop_id,
                                'order_id' => $wm_order->id,
                                'order_no' => $wm_order->order_id,
                                'platform' => $wm_order->platform,
                                'app_poi_code' => $wm_order->app_poi_code,
                                'wm_shop_name' => $wm_order->wm_shop_name,
                                'day_seq' => $wm_order->day_seq,
                                'trade_type' => 101,
                                'status' => $wm_order->status,
                                'order_at' => $wm_order->created_at,
                                'finish_at' => $wm_order->finish_at,
                                'bill_date' => $order->over_at,
                                'vip_settlement' => $poi_receive,
                                'vip_cost' => 0,
                                'vip_permission' => 0,
                                'vip_total' => $total,
                                'vip_commission_company' => $commission,
                                'vip_commission_manager' => $commission_manager,
                                'vip_commission_operate' => $commission_operate,
                                'vip_commission_internal' => $commission_internal,
                                'vip_commission_business' => $business,
                                'vip_company' => $vip_company,
                                'vip_city' => $vip_city,
                                'vip_operate' => $vip_operate,
                                'vip_internal' => $vip_internal,
                                'vip_business' => $vip_business,
                            ];
                            VipBillItem::create($item);
                            \Log::info("VIP订单结算处理，跑腿订单扣费结算成功");
                        }
                    }
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

    /**
     * APP订单列表，标题
     * @data 2023/8/10 9:32 上午
     */
    public static function setAppOrderListTitle($status, $delivery_time, $estimate_arrival_time, $order): string
    {
        if ($status === 10) {
            if ($delivery_time) {
                return '预约订单，<text class="time-text" style="color: #5ac725">' . tranTime2($delivery_time) . '<text/>送达';
            } else {
                return '立即送达，<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->created_at)) . '</text>下单';
            }
        } elseif ($status === 20 && $order->push_at) {
            return '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->push_at)) . '</text>发单';
        } elseif ($status === 30 && $order->receive_at) {
            return '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->receive_at)) . '</text>接单';
        } elseif ($status === 40) {
            if ($delivery_time) {
                return '<text class="time-text" style="color: #5ac725">预约订单，' . tranTime2($delivery_time) . '<text/>送达' . tranTime3($delivery_time);
            } elseif ($estimate_arrival_time) {
                return '<text class="time-text" style="color: #5ac725">' . tranTime2($estimate_arrival_time) . '</text>前送达' . tranTime3($estimate_arrival_time);
            }
        }
        if ($delivery_time) {
            return '<text class="time-text" style="color: #5ac725">预约订单，' . date("m-d H:i", $delivery_time) . '<text/>送达';
        } else {
            return '<text class="time-text" style="color: #5ac725">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
    }

    public static function setAppOrderInfoTitle($delivery_time, $order): string
    {
        if ($delivery_time) {
            $title = '<text class="time-text" style="color: #5ac725">预约订单，' . date("m-d H:i", $delivery_time) . '</text>送达';
        } else {
            $title = '<text class="time-text" style="color: #5ac725">立即送达，' . date("m-d H:i", strtotime($order->created_at)) . '</text>下单';
        }
        return $title;
    }

    public static function setAppSearchOrderTitle($delivery_time, $estimate_arrival_time, $order): string
    {
        if ($order->status == 70 || $order->status == 75) {
            if ($order->over_at) {
                $title = date("m-d H:i", strtotime($order->over_at)) . '已完成';
            } else {
                $title = '已完成，' . date("m-d H:i", strtotime($order->created_at))  . '下单';
            }
        } elseif ($order->status == 99) {
            $title = '已取消，<text class="time-text" style="color: #5ac725">' . date("m-d H:i", strtotime($order->created_at))  . '</text>下单';
        } else {
            if ($order->status === 20 && $order->push_at) {
                $title = '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->push_at)) . '</text>发单';
            } elseif ($order->status === 50 && $order->receive_at) {
                $title = '<text class="time-text" style="color: #5ac725">' . tranTime(strtotime($order->receive_at)) . '</text>接单';
            } elseif ($order->status === 60) {
                if ($delivery_time) {
                    $title = '<text class="time-text" style="color: #5ac725">预约订单，' . tranTime2($delivery_time) . '<text/>送达' . tranTime3($delivery_time);
                } else{
                    if ($estimate_arrival_time) {
                        $title = '<text class="time-text" style="color: #5ac725">' . tranTime2($delivery_time) . '</text>前送达' . tranTime3($estimate_arrival_time);
                    } else {
                        $title = '<text class="time-text" style="color: #5ac725">立即送达，' . tranTime(strtotime($order->created_at)) . '</text>下单';
                    }
                }
            } else {
                if (!empty($delivery_time)) {
                    $title = '<text class="time-text" style="color: #5ac725">预约订单，' . tranTime2($delivery_time) . '<text/>送达';
                } else {
                    $title = '<text class="time-text" style="color: #5ac725">立即送达，' . tranTime(strtotime($order->created_at)) . '</text>下单';
                }
            }
        }
        return $title;
    }
}
