<?php

namespace App\Libraries\KuaiDi\Api;

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
            // 'from' => '北京市海淀区',
            'to' => $order->address['address'] ?? '',
        ];

        return $this->post('poll/maptrack.do', $param);
    }
}
