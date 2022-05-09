<?php

namespace App\Http\Controllers\Api\Waimai\MinKang;

use App\Http\Controllers\Controller;
use App\Jobs\MeiTuanWaiMaiPicking;
use App\Jobs\VipOrderSettlement;
use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\VipProduct;
use App\Models\WmOrder;
use App\Models\WmOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public $prefix_title = '[美团外卖&&民康订单&&###]';

    public function create(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', "创建订单|订单号:{$order_id}", $this->prefix_title);
            // $this->log('全部参数', $request->all());
            $app_poi_code = $request->get('app_poi_code', 0);
            $data = ['9413566', '13921009'];
            if (!in_array($app_poi_code, $data)) {
                $meituan = app("minkang");
                $res = $meituan->orderConfirm($order_id);
                $this->log("订单号：{$order_id}|操作接单返回信息", $res);
                // dispatch(new MeiTuanWaiMaiPicking($order_id, 180));
            }
        }

        return json_encode(['data' => 'ok']);
    }

    // public function confirm(Request $request)
    // {
    //     $this->prefix .= '-[确认]';
    //
    //     if ($order_id = $request->get("order_id", "")) {
    //         $this->log('全部参数', $request->all());
    //     }
    //
    //     return json_encode(['data' => 'ok']);
    // }

    public function refund(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            $notify_type = $request->get('notify_type');
            $this->prefix = str_replace('###', "全部退款|订单号:{$order_id}", $this->prefix_title);
            if ($notify_type == 'agree') {
                if ($order = WmOrder::where('order_id', $order_id)->first()) {
                    WmOrder::where('id', $order->id)->update([
                        'refund_status' => 1,
                        'refund_fee' => $order->total,
                        'refund_at' => date("Y-m-d H:i:s")
                    ]);
                }
            }
            $this->log('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    public function partrefund(Request $request)
    {
        if ($order_id = $request->get("order_id", "")) {
            $money = $request->get('money');
            $notify_type = $request->get('notify_type');
            $this->prefix = str_replace('###', "部分退款|类型:{$notify_type}|订单号:{$order_id}", $this->prefix_title);
            if (($notify_type == 'agree') && ($money > 0)) {
                if ($order = WmOrder::where('order_id', $order_id)->first()) {
                    $this->log('全部参数', $request->all());
                    WmOrder::where('id', $order->id)->update([
                        'refund_status' => 2,
                        'refund_fee' => $money,
                        'refund_at' => date("Y-m-d H:i:s"),
                    ]);
                    $food_str = $request->get('food');
                    $foods = json_decode($food_str, true);
                    if (!empty($foods)) {
                        DB::transaction(function () use ($order, $foods) {
                            $vip = $order->is_vip;
                            $dec_cost = 0;
                            $where['order_id'] = $order->id;
                            foreach ($foods as $food) {
                                $where['upc'] = $food['upc'];
                                $count = $food['count'];
                                if ($item = WmOrderItem::where($where)->where('quantity', '>', 0)->first()) {
                                    WmOrderItem::where('id', $item->id)->update([
                                        'quantity' => $item->quantity - $count,
                                        'refund_quantity' => $count,
                                    ]);
                                }
                                if ($vip) {
                                    $cost = VipProduct::select('cost')->where(['upc' => $food['upc'], 'shop_id' => $order->shop_id])->first();
                                    if (isset($cost->cost)) {
                                        $dec_cost += ($cost->cost ?? 0);
                                    } else {
                                        $this->log("成本价不存在，订单ID:{$order->order_id}");
                                    }
                                }
                            }
                            $vip_cost = $order->vip_cost - $dec_cost;
                            WmOrder::where('id', $order->id)->update(['vip_cost' => $vip_cost > 0 ? $vip_cost : 0]);
                        });
                        if ($order->is_vip) {
                            // 如果是VIP订单，触发结算JOB
                            dispatch(new VipOrderSettlement($order));
                        }
                    }
                }
            }
        }

        return json_encode(['data' => 'ok']);
    }

    /**
     * 美配订单状态回调
     * @data 2022/2/26 9:24 上午
     */
    public function rider(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('time', 0);
        $name = $request->get('dispatcher_name', '');
        $phone = $request->get('dispatcher_mobile', '');
        // $this->log('美配订单状态回调全部参数', $request->all());

        if ($order_id && $status) {
            $this->prefix = str_replace('###', "美配订单状态回调|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if (in_array($status, [10, 20, 40]) && $order->status < 16) {
                    if ($status == 10) {
                        $order->status = 12;
                        $order->receive_at = date("Y-m-d H:i:s", $time ?: time());
                    } elseif ($status == 20) {
                        $order->status = 14;
                        $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    } elseif ($status == 40) {
                        $order->status = 16;
                        $order->deliver_at = date("Y-m-d H:i:s", $time ?: time());
                    }
                    if ($name) {
                        $order->shipper_name = $name;
                        $order->shipper_phone = $phone;
                    }
                    $order->save();
                    $this->log("订单号：{$order_id}|操作完成");
                } else {
                    $this->log("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log("订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    /**
     * 自配订单状态回调
     * @data 2022/2/26 9:24 上午
     */
    public function status_self(Request $request)
    {
        $order_id = $request->get('order_view_id', '');
        $status = $request->get('logistics_status', '');
        $time = $request->get('operate_time', 0);

        if ($order_id && $status) {
            $this->prefix = str_replace('###', "自配订单状态|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 20 && $order->status < 14) {
                    $order->send_at = date("Y-m-d H:i:s", $time ?: time());
                    $order->status = 14;
                    $order->save();
                    $this->log("订单号：{$order_id}|操作完成");
                } else {
                    $this->log("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log("订单号：{$order_id}|订单不存在");
            }
        }
        return json_encode(['data' => 'ok']);
    }

    /**
     * 完成订单
     * @data 2022/2/26 9:21 上午
     */
    public function finish(Request $request)
    {
    }

    public function settlement(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $status = $request->get('status', '');
        $fee = $request->get('settleAmount', '');
        if ($order_id && $status) {
            $this->prefix = str_replace('###', "订单结算|订单状态:{$status}|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                $this->log('VIP全部参数', $request->all());
                if (floatval($order->poi_receive) != floatval($fee)) {
                    $dingding = new DingTalkRobotNotice("c957a526bb78093f61c61ef0693cc82aae34e079f4de3321ef14c881611204c4");
                    $dingding->sendTextMsg("结算金额不一致异常,status:{$status},order_id:{$order_id},poi_receive:{$order->poi_receive},fee:{$fee},时间:".date("Y-m-d H:i:s"));
                }
            } else {
                $this->log('全部参数', $request->all());
            }
        }

        return json_encode(['data' => 'ok']);
    }

    public function down(Request $request)
    {
        // $this->prefix = str_replace('###', "隐私号降级", $this->prefix_title);
        //
        // $data = $request->all();
        //
        // if (!empty($data)) {
        //     $this->log('全部参数', $request->all());
        // }
        //
        // return json_encode(['data' => 'ok']);
    }

    public function remind(Request $request)
    {
        // $this->prefix = str_replace('###', "催单", $this->prefix_title);
        //
        // if ($order_id = $request->get("order_id", "")) {
        //     $this->log('全部参数', $request->all());
        // }
        //
        // return json_encode(['data' => 'ok']);
    }
}
