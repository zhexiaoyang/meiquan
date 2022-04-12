<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

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

    public function finish(Request $request, $platform)
    {
        if ($order_id = $request->get("order_id", "")) {
            $this->prefix = str_replace('###', get_meituan_develop_platform($platform) . "&完成订单|订单号:{$order_id}", $this->prefix_title);
            $this->log_info('美团外卖统一接口');
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
