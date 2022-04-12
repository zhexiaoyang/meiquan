<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Jobs\VipOrderSettlement;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\WmOrder;
use App\Traits\LogTool;
use Illuminate\Http\Request;

class OrderController
{
    use LogTool;

    public $prefix_title = '[美团外卖回调&###]';

    public function create(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&支付订单|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    public function refund(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&全部退款|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    public function partrefund(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&部分退款|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    public function rider(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&美配订单状态回调|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    public function status_self(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&自配订单状态|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    /**
     * 美团完成订单-统一回调
     * @data 2022/4/12 4:08 下午
     */
    public function finish(Request $request, $platform)
    {
        $order_id = $request->get('wm_order_id_view', '');
        $status = $request->get('status', '');

        if ($order_id && $status) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&完成订单|订单号:{$order_id}", $this->prefix_title);
            if ($order = WmOrder::where('order_id', $order_id)->first()) {
                if ($status == 8 && $order->status < 18) {
                    $order->status = 18;
                    $order->finish_at = date("Y-m-d H:i:s");
                    $order->save();
                    $this->log_info("订单号：{$order_id}|操作完成");
                    if ($order->is_vip) {
                        // 如果是VIP订单，触发JOB
                        dispatch(new VipOrderSettlement($order));
                    }
                } else {
                    $this->log_info("订单号：{$order_id}|操作失败|美团状态：{$status}|系统订单状态：{$order->status}");
                }
            } else {
                $this->log_info("订单号：{$order_id}|订单不存在");
            }
            if ($order_pt = Order::where('order_id', $order_id)->first()) {
                if ($order_pt->status == 0) {
                    $order_pt->status = 70;
                    $order_pt->over_at = date("Y-m-d H:i:s");
                    $order_pt->save();
                    OrderLog::create([
                        "order_id" => $order_pt->id,
                        "des" => "「美团外卖」完成订单"
                    ]);
                }
            }
        }
        return json_encode(['data' => 'ok']);
    }

    public function settlement(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&订单结算|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
        }

        return json_encode(['data' => 'ok']);
    }

    public function remind(Request $request, $platform)
    {
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&催单", $this->prefix_title);

        if ($order_id = $request->get("order_id", "")) {
            $this->log_info('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }

    public function down(Request $request, $platform)
    {
        $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&隐私号降级", $this->prefix_title);

        $data = $request->all();

        if (!empty($data)) {
            $this->log_info('全部参数', $request->all());
        }

        return json_encode(['data' => 'ok']);
    }
}
