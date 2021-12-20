<?php

namespace App\Libraries\KuaiDi\Api;

use App\Models\ExpressOrder;
use App\Models\Shop;
use App\Models\SupplierOrder;

class Api extends Request
{
    public function poll_query(SupplierOrder $order)
    {
        $platform_data = [
            '申通快递' => 'shentong',
            '韵达快递' => 'yunda',
            '圆通快递' => 'yuantong',
            '顺丰快递' => 'shunfeng',
            '中通快递' => 'zhongtong',
            '百世快递' => 'huitongkuaidi',
            '德邦快递' => 'debangkuaidi',
            '天天快递' => 'tiantian',
            'EMS快递' => 'ems',
        ];

        $platform = $platform_data[$order->ship_platform] ?? '';

        if (!$platform) {
            return [];
        }

        $param = [
            'num' => $order->ship_no,
            'com' => $platform,
            'resultv2' => '1'
        ];

        return $this->post('poll/query.do', $param);
    }


    public function maptrack(SupplierOrder $order, string $num)
    {
        $platform_data = [
            '申通快递' => 'shentong',
            '韵达快递' => 'yunda',
            '圆通快递' => 'yuantong',
            '顺丰快递' => 'shunfeng',
            '中通快递' => 'zhongtong',
            '百世快递' => 'huitongkuaidi',
            '德邦快递' => 'debangkuaidi',
            '天天快递' => 'tiantian',
            'EMS快递' => 'ems',
        ];

        $platform = $platform_data[$order->ship_platform] ?? '';

        if (!$platform) {
            return [];
        }

        $param = [
            'num' => $num,
            'com' => $platform,
            'from' => '',
            'to' => $order->address['address'] ?? '',
        ];

        return $this->post('poll/maptrack.do', $param);
    }

    /**
     * 商家寄件-查看运费
     * @data 2021/12/16 5:08 下午
     */
    public function pre_order()
    {
        $params = [
            'kuaidiCom' => 'jtexpress',
            'address' => '辽宁省沈阳市'
        ];

        return $this->post_order('order/borderapi.do', 'queryPrice', $params);
    }

    /**
     * 商家寄件-创建订单
     * @data 2021/12/16 5:08 下午
     */
    public function create_order(ExpressOrder $order, Shop $shop)
    {
        $type = ['','jtexpress','yuantong','shentong','jd','debangkuaidi'];
        $platform = $type[$order->platform] ?? '';
        $params = [
            'kuaidicom' => $platform,
            'recManName' => $order->receive_name,
            'recManMobile' => $order->receive_phone,
            'recManPrintAddr' => $order->province . $order->city . $order->area . $order->address,
            'sendManName' => $shop->contact_name,
            'sendManMobile' => $shop->contact_phone,
            'sendManPrintAddr' => $shop->shop_address,
            'callBackUrl' => "http://psapi.meiquanda.com/api/callback/kuaidi/order",
        ];
        if ($order->goods) {
            $params['cargo'] = $order->goods;
        }
        return $this->post_order('order/borderapi.do', 'bOrder', $params);
    }

    /**
     * 商家寄件-取消订单
     * @data 2021/12/16 5:08 下午
     */
    public function cancel_order(ExpressOrder $order)
    {
        $params = [
            'taskId' => $order->task_id,
            'orderId' => $order->order_id,
            'cancelMsg' => '暂时不寄件了'
        ];
        return $this->post_order('order/borderapi.do', 'cancel', $params);
    }
}
