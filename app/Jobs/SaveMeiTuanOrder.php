<?php

namespace App\Jobs;

use App\Models\OrderSetting;
use App\Models\VipProduct;
use App\Models\WmOrderItem;
use App\Models\WmOrder;
use App\Models\WmOrderReceive;
use App\Models\WmPrinter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaveMeiTuanOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    private $platform;
    private $from_type;
    private $shop_id;
    private $g_status;
    private $g_no;
    private $g_error;
    private $vip;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, int $platform, int $from_type, int $shop_id, int $g_status = 1, string $g_no = '', string $g_error = '', int $vip = 0)
    {
        $this->data = $data;
        // 平台
        $this->platform = $platform;
        // 来源
        $this->from_type = $from_type;
        // 美全门店ID
        $this->shop_id = $shop_id;
        // 药柜订单状态
        $this->g_status = $g_status;
        // 药柜订单号
        $this->g_no = $g_no;
        $this->g_error = $g_error;
        // 是否VIP门店订单
        $this->vip = $vip;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;

        $mt_shop_id = $data['app_poi_code'];
        $mt_order_id = $data['wm_order_id_view'];
        $products = json_decode(urldecode($data['detail']), true);
        $poi_receive_detail_yuan = json_decode(urldecode($data['poi_receive_detail_yuan']), true);
        $order_tag_list = json_decode(urldecode($data['order_tag_list']), true);

        if (!$mt_order_id || !$mt_shop_id) {
            return false;
        }

        $status_filter = [1 => 1, 2 => 1, 4 => 4, 8 => 18, 9 => 30];

        $logistics_code = isset($data['logistics_code']) ? intval($data['logistics_code']) : 0;

        if ($logistics_code > 0) {
            if ($logistics_code === 1001) {
                $logistics_code = 1;
            }
            if ($logistics_code === 2002) {
                $logistics_code = 2;
            }
            if ($logistics_code === 3001) {
                $logistics_code = 3;
            }
        }

        $order_data = [
            "shop_id" => $this->shop_id,
            "order_id" => $mt_order_id,
            "wm_order_id_view" => $mt_order_id,
            "platform" => $this->platform,
            "from_type" => $this->from_type,
            "app_poi_code" => $mt_shop_id,
            "wm_shop_name" => urldecode($data['wm_poi_name'] ?? ''),
            "recipient_name" => urldecode($data['recipient_name']) ?? "无名客人",
            "recipient_phone" => $data['recipient_phone'],
            "recipient_address" => urldecode($data['recipient_address']),
            "recipient_address_detail" => urldecode($data['recipient_address_detail']  ?? ''),
            "latitude" => $data['latitude'],
            "longitude" => $data['longitude'],
            "shipping_fee" => $data['shipping_fee'],
            "total" => $data['total'],
            "original_price" => $data['original_price'],
            "package_bag_money_yuan" => $data['package_bag_money_yuan'] ?? 0,
            "service_fee" => $poi_receive_detail_yuan['foodShareFeeChargeByPoi'] ?? 0,
            "logistics_fee" => $poi_receive_detail_yuan['logisticsFee'] ?? 0,
            "online_payment" => $poi_receive_detail_yuan['onlinePayment'] ?? 0,
            "poi_receive" => $poi_receive_detail_yuan['poiReceive'] ?? 0,
            "rebate_fee" => $poi_receive_detail_yuan['agreementCommissionRebateAmount'] ?? 0,
            "caution" => urldecode($data['caution']),
            "shipper_phone" => $data['shipper_phone'] ?? "",
            "status" => $status_filter[$data['status']] ?? 4,
            "ctime" => $data['ctime'],
            "estimate_arrival_time" => $data['estimate_arrival_time'] ?? 0,
            "utime" => $data['utime'],
            "delivery_time" => $data['delivery_time'],
            "pick_type" => $data['pick_type'] ?? 0,
            "day_seq" => $data['day_seq'] ?? 0,
            "invoice_title" => $data['invoice_title'] ?? '',
            "taxpayer_id" => $data['taxpayer_id'] ?? '',
            "is_prescription" => in_array(8, $order_tag_list) ? 1 : 0,
            "is_favorites" => intval($data['is_favorites'] ?? 0),
            "is_poi_first_order" => intval($data['is_poi_first_order'] ?? 0),
            "logistics_code" => $logistics_code,
            "ware_order_id" => $this->g_no,
            "ware_status" => $this->g_status,
            "ware_error" => $this->g_error,
            "ware_take_code" => substr($mt_order_id, -6),
            "is_vip" => $this->vip,
            "prescription_fee" => 1.5,
        ];

        $order = DB::transaction(function () use ($products, $order_data, $poi_receive_detail_yuan) {
            // 商品信息
            $items = [];
            // VIP成本价
            $cost_money = 0;
            $cost_data = [];
            // 保存订单
            $order = WmOrder::query()->create($order_data);
            // 组合商品数组，计算成本价
            if (!empty($products)) {
                foreach ($products as $product) {
                    $_tmp = [
                        'order_id' => $order->id,
                        'app_food_code' => $product['app_food_code'] ?? '',
                        'food_name' => $product['food_name'] ?? '',
                        'unit' => $product['unit'] ?? '',
                        'upc' => $product['upc'] ?? '',
                        'quantity' => $product['quantity'] ?? 0,
                        'price' => $product['price'] ?? 0,
                        'spec' => $product['spec'] ?? '',
                        'vip_cost' => 0
                    ];
                    if ($this->vip) {
                        $cost = VipProduct::select('cost')->where(['upc' => $product['upc'], 'shop_id' => $this->shop_id])->first();
                        if (isset($cost->cost)) {
                            $cost_money += $cost->cost ?? 0;
                            $_tmp['vip_cost'] = $cost->cost;
                            $cost_data[] = ['upc' => $product['upc'], 'cost' => $cost->cost];
                        } else {
                            $upc = $product['upc'];
                            Log::info("[保存外卖订单]-[成本价小于等于零]-[shop_id：{$this->shop_id}|upc：{$upc}]");
                        }
                    }
                    $items[] = $_tmp;
                }
            }
            if (!empty($items)) {
                $order->vip_cost = $cost_money;
                $order->vip_cost_info = json_encode($cost_data, JSON_UNESCAPED_UNICODE);
                $order->save();
                WmOrderItem::query()->insert($items);
                Log::info("[保存外卖订单]-[成本价计算：{$cost_money}]-[shop_id：{$this->shop_id},order_id：{$order->order_id}]");
            }
            $receives = [];
            if (!empty($poi_receive_detail_yuan['actOrderChargeByMt'])) {
                foreach ($poi_receive_detail_yuan['actOrderChargeByMt'] as $receive) {
                    if ($receive['money'] > 0) {
                        $receives[] = [
                            'type' => 1,
                            'order_id' => $order->id,
                            'comment' => $receive['comment'],
                            'fee_desc' => $receive['feeTypeDesc'],
                            'money' => $receive['money'],
                        ];
                    }
                }
            }
            if (!empty($poi_receive_detail_yuan['actOrderChargeByPoi'])) {
                foreach ($poi_receive_detail_yuan['actOrderChargeByPoi'] as $receive) {
                    if ($receive['money'] > 0) {
                        $receives[] = [
                            'type' => 2,
                            'order_id' => $order->id,
                            'comment' => $receive['comment'],
                            'fee_desc' => $receive['feeTypeDesc'],
                            'money' => $receive['money'],
                        ];
                    }
                }
            }
            if (!empty($receives)) {
                WmOrderReceive::query()->insert($receives);
            }

            return $order;
        });

        if ($print = WmPrinter::where('shop_id', $this->shop_id)->first()) {
            dispatch(new PrintWaiMaiOrder($order, $print));
        }

        // 转仓库打印
        Log::info("[保存外卖订单]-[转单打印]-[shop_id：{$this->shop_id}");
        if ($setting = OrderSetting::where('shop_id', $this->shop_id)->first()) {
            Log::info("[保存外卖订单]-[转单打印]-[setting：{$setting->id}", [$setting]);
            if ($setting->warehouse && $setting->warehouse_time && $setting->warehouse_print) {
                $time_data = explode('-', $setting->warehouse_time);
                Log::info("[保存外卖订单]-[转单打印]-[time_data", [$time_data]);
                if (!empty($time_data) && (count($time_data) === 2)) {
                    if (in_time_status($time_data[0], $time_data[1])) {
                        Log::info("[保存外卖订单]-[转单打印]-[仓库ID：{$setting->warehouse}");
                        if ($print = WmPrinter::where('shop_id', $setting->warehouse)->first()) {
                            Log::info("[保存外卖订单]-[转单打印]-[订单ID：{$order->id}，订单号：{$order->order_id}，门店ID：{$order->shop_id}，仓库ID：{$setting->warehouse}]");
                            dispatch(new PrintWaiMaiOrder($order, $print));
                        }
                    }
                }
            }
        }
    }
}
