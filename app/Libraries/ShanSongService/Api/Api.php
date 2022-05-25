<?php

namespace App\Libraries\ShanSongService\Api;

use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopShipper;
use App\Traits\NoticeTool;
use Illuminate\Support\Facades\Cache;

class Api extends Request
{
    use NoticeTool;

    public function get_token_by_code($code)
    {
        $params = [
            'clientId' => $this->client_id,
            'code' => $code,
        ];

        $http = $this->getHttp();
        $response = $http->post($this->url . '/openapi/oauth/token', $params);

        return json_decode(strval($response->getBody()), true);
    }

    public function token_ref($refresh_token, $shop_id)
    {
        // $this->access_token = $this->get_token($shop_id);

        $data = [
            'refreshToken' => $refresh_token,
        ];

        return $this->post('/openapi/oauth/refresh_token', $data);
    }

    // /**
    //  * 创建门店
    //  */
    // public function createShop(Shop $shop)
    // {
    //     $jwd = gd2bd($shop->shop_lng, $shop->shop_lat);
    //
    //     $data = [
    //         "storeName" => $shop->shop_name,
    //         "cityName" => $shop->city,
    //         "address" => $shop->shop_address . ',' . $shop->shop_name,
    //         "addressDetail" => "-",
    //         "latitude" => $jwd['lat'],
    //         "longitude" => $jwd['lng'],
    //         "phone" => $shop->contact_phone,
    //         "goodType" => 13
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/storeOperation', $data);
    // }
    //
    // /**
    //  * 更新门店
    //  */
    // public function updateShop(Shop $shop)
    // {
    //     $jwd = gd2bd($shop->shop_lng, $shop->shop_lat);
    //
    //     $data = [
    //         "storeId" => $shop->shop_id_ss,
    //         "storeName" => $shop->shop_name,
    //         "cityName" => $shop->city,
    //         "address" => $shop->shop_address . ',' . $shop->shop_name,
    //         "addressDetail" => "-",
    //         "latitude" => $jwd['lat'],
    //         "longitude" => $jwd['lng'],
    //         "phone" => $shop->contact_phone,
    //         "goodType" => 13,
    //         "operationType" => 2
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/storeOperation', $data);
    // }
    //
    /**
     * 获取门店信息
     * @return mixed
     */
    public function getShop($shop_id, $storeId)
    {
        $this->access_token = $this->get_token($shop_id);

        return $this->post('/openapi/developer/v5/queryAllStores', ['storeId' => $storeId]);
    }

    /**
     * 订单计费
     */
    public function orderCalculate(Shop $shop, Order $order)
    {
        $this->access_token = $this->get_token($shop->id);

        $jwd1 = gd2bd($shop->shop_lng, $shop->shop_lat);
        $jwd2 = gd2bd($order->receiver_lng, $order->receiver_lat);
        $data = [
            "cityName" => $shop->city,
            "sender" => [
                "fromAddress" => $shop->shop_address,
                "fromAddressDetail" => $shop->shop_name ?? "",
                "fromSenderName" => $shop->contact_name,
                "fromMobile" => $shop->contact_phone,
                "fromLatitude" => $jwd1['lat'],
                "fromLongitude" => $jwd1['lng']
            ],
            "receiverList" => [[
                "orderNo" => $order->order_id,
                "toAddress" => $order->receiver_address,
                "toAddressDetail" => $order->receiver_address,
                "toLatitude" => $jwd2['lat'],
                "toLongitude" => $jwd2['lng'],
                "toReceiverName" => $order->receiver_name ?: "无名",
                "toMobile" => str_replace('_', '#', $order->receiver_phone),
                "goodType" => 13,
                "weight" => 1,
                "remarks" => $order->note ?? "",
                // "additionFee" => 500,
                // "insurance" => 200,
                // "insuranceProId" => "SS_baofei_001",
                // "orderingSourceType" =>4,
                // "orderingSourceNo" => $order->goods_pickup_info ?? "",
                // "orderingSourceNo" => $order->goods_pickup_info ? "取货码：" . $order->goods_pickup_info : ''
            ]],
            // "appointType" => $order->order_type ?? "",
            "appointType" => 0,
            // "appointmentDate" => (isset($order->expected_pickup_time) && $order->expected_pickup_time) ? date("Y-m-d H:i", $order->expected_pickup_time) : "",
            // "appointmentDate" => "",
            "travelWay" => $order->tool === 8 ? 8 : 0,
            // "storeId" => $shop->shop_id_ss
        ];


        if ($order->goods_pickup_info) {
            $data['receiverList'][0]['orderingSourceType'] = 4;
            $data['receiverList'][0]['orderingSourceNo'] = "取货码：" . $order->goods_pickup_info;
        } elseif ($order->day_seq) {
            if ($order->platform === 1) {
                $data['receiverList'][0]['orderingSourceType'] = 4;
                $data['receiverList'][0]['orderingSourceNo'] = $order->day_seq;
            }
            if ($order->platform === 2) {
                $data['receiverList'][0]['orderingSourceType'] = 3;
                $data['receiverList'][0]['orderingSourceNo'] = $order->day_seq;
            }
        }

        // if ($order->type === 11) {
        //     $data['appointmentDate'] = '';
        // }

        return $this->post('/openapi/developer/v5/orderCalculate', $data);
    }

    // /**
    //  * 创建订单
    //  * @param $order_id
    //  * @return mixed
    //  */
    // public function createOrder($order_id)
    // {
    //     $data = [
    //         'issOrderNo' => $order_id
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/orderPlace', $data);
    // }
    //
    // /**
    //  * 取消订单
    //  * @param $order_id
    //  * @return mixed
    //  */
    // public function cancelOrder($order_id)
    // {
    //     $data = [
    //         'issOrderNo' => $order_id
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/abortOrder', $data);
    // }
    //
    // /**
    //  * 获取订单
    //  * @param $data
    //  * @return mixed
    //  */
    // public function getOrder($data)
    // {
    //     return $this->post('/openapi/merchants/v5/orderInfo', $data);
    // }
    //
    // /**
    //  * 配送员信息
    //  * @param $order_id
    //  * @return mixed
    //  */
    // public function carrier($order_id)
    // {
    //     $data = [
    //         'issOrderNo' => $order_id
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/courierInfo', $data);
    // }
    //
    // /**
    //  * 确认物品送回
    //  * @param $order_id
    //  * @return mixed
    //  */
    // public function confirmGoodsReturn($order_id)
    // {
    //     $data = [
    //         'issOrderNo' => $order_id
    //     ];
    //
    //     return $this->post('/openapi/merchants/v5/confirmGoodsReturn', $data);
    // }
    //
    // public function getUserAccount()
    // {
    //     return $this->post('/openapi/merchants/v5/getUserAccount', []);
    // }


    public function get_token($shop_id)
    {
        $key = 'ss:shop:auth:' . $shop_id;
        $key_ref = 'ss:shop:auth:ref:' . $shop_id;
        $access_token = Cache::store('redis')->get($key, '');
        if (!$access_token) {
            $refresh_token = Cache::store('redis')->get($key_ref);
            $token_res = ShopShipper::where('shop_id', $shop_id)->where('platform', 3)->first();
            if (!$refresh_token) {
                if ($token_res) {
                    $refresh_token = $token_res->refresh_token;
                }
            }
            if (!$refresh_token) {
                $this->ding_error("闪送自建门店token不存在错误，shop_id:{$shop_id}");
                return false;
            }
            $res = $this->token_ref($refresh_token, $shop_id);
            if (!empty($res['data']['access_token'])) {
                $access_token = $res['data']['access_token'];
                // $refresh_token = $res['data']['refresh_token'];
                $expires_in = $res['data']['expires_in'];
                Cache::put($key, $access_token, $expires_in - 100);
                Cache::forever($key_ref, $refresh_token);
                if ($token_res) {
                    ShopShipper::where('shop_id', $shop_id)->where('platform', 3)->update([
                        'access_token' => $access_token,
                        // 'refresh_token' => $refresh_token,
                        'token_time' => date("Y-m-d H:i:s"),
                    ]);
                }
            } else {
                $this->ding_error("闪送自建门店token刷新失败错误，shop_id:{$shop_id}");
                \Log::info("闪送自建门店token刷新失败错误", [$res]);
                return false;
            }
        }

        return $access_token;
    }
}
