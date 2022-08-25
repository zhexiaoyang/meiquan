<?php

namespace App\Libraries\Ele\Api;

use App\Models\Order;

class Api extends Request
{
    public function shopInfo($shop_id)
    {
        $data = [
            'baidu_shop_id' => $shop_id
        ];

        return $this->post('shop.get', $data);
    }

    /**
     * 获取门店营业时间
     * @data 2022/5/7 6:55 下午
     */
    public function shopBusstatus($shop_id)
    {
        $data = [
            'baidu_shop_id' => $shop_id,
            'platformFlag' => 1
        ];

        return $this->post('shop.busstatus.get', $data);
    }

    /**
     * 修改门店营业时间
     * @data 2022/5/7 6:55 下午
     */
    public function shippingTimeUpdate($shop_id, $start, $end)
    {
        $data = [
            'baidu_shop_id' => $shop_id,
            'business_time' => [
                'start' => $start,
                'end' => $end,
            ]
        ];

        return $this->post('shop.update', $data);
    }

    /**
     * 修改门店营业时间
     * @data 2022/5/7 6:55 下午
     */
    public function shopOpen($shop_id)
    {
        $data = [
            'baidu_shop_id' => $shop_id
        ];

        return $this->post('shop.open', $data);
    }
    public function shopClose($shop_id)
    {
        $data = [
            'baidu_shop_id' => $shop_id
        ];

        return $this->post('shop.close', $data);
    }

    public function shopInfoByStoreId($shop_id)
    {
        $data = [
            'shop_id' => $shop_id
        ];

        return $this->post('shop.get', $data);
    }

    /**
     * 订单详情
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/4 8:37 下午
     */
    public function orderInfo($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.get', $data);
    }

    /**
     * 确认订单
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function confirmOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.confirm', $data);
    }

    /**
     * 拣货完成
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function pickcompleteOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.pickcomplete', $data);
    }

    /**
     * 订单送达
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function completeOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.complete', $data);
    }

    /**
     * 订单送出
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/7/27 5:53 下午
     */
    public function sendoutOrder($order_id)
    {
        $data = [
            'order_id' => $order_id
        ];

        return $this->post('order.sendout', $data);
    }

    /**
     * 同步状态信息
     * @param $order_id
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/4 8:49 下午
     */
    public function deliveryStatus($params)
    {
        $data = [
            'distributor_id' => 201,
            'order_id' => $params['order_id'],
            'state' => 21,
            'knight' => [
                'id' => 1,
                'name' => $params['name'],
                'phone' => $params['phone']
            ]
        ];

        return $this->post('order.selfDeliveryStateSync', $data);
    }

    public function skuStockUpdate($data)
    {
        return $this->post('sku.stock.update.batch', $data);
    }

    public function selfDeliveryLocationSync($data)
    {
        return $this->post('order.selfDeliveryLocationSync', $data);
    }

    public function skuList($shop_id, $upc = '')
    {
        if ($shop_id) {
            $data['shop_id'] = $shop_id;
        }
        if ($upc) {
            $data['upc'] = $upc;
        }
        $data['pagesize'] = 100;

        return $this->post('sku.list', $data);
    }

    public function add_category($data)
    {
        return $this->post('sku.shop.category.create', $data);
    }

    public function delete_category($data)
    {
        return $this->post('sku.shop.category.delete', $data);
    }

    public function add_product($data)
    {
        return $this->post('sku.create', $data);
    }

    public function category_list()
    {
        // 201223557 其他
        // 201232918 情趣助力
        $data = [
            'keyword' => '',
            'depth' => 3,
            'parent_id' => 201221734
        ];
        return $this->post('sku.category.list', $data);
    }

    public function uploadrtf($shop_id, $str)
    {
        $tail = '';
        $images = explode(',', $str);
        foreach ($images as $image) {
            $end = stripos($image, '?');
            if ($end !== false) {
                $image = substr($image, 0, $end);
            }
            $tail .= "<img src='{$image}'><br/>";
        }
        $tail = '<div>' . $tail . '</div>';
        \Log::info("$tail");
        $data = [
            'shop_id' => (string) $shop_id,
            'rtf_detail' => $tail,
        ];
        return $this->post('sku.uploadrtf', $data);
    }
}
