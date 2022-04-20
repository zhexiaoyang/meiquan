<?php

namespace App\Traits;

use App\Models\Shop;
use App\Models\VipBill;
use App\Models\WmOrder;

trait VipShopHelper
{
    public function make_bill(Shop $shop, $date = null, VipBill $bill = null)
    {
        $orders = WmOrder::query()
            ->where('is_vip', 1)
            ->where('shop_id', $shop->id)
            ->where('bill_date', $date)
            ->get();
        $poi_receive = 0;
        $vip_cost = 0;
        $running_fee = 0;
        $prescription_fee = 0;
        $refund_fee = 0;
        $vip_total = 0;
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $poi_receive += $order->poi_receive;
                $vip_cost += $order->vip_cost;
                $running_fee += $order->running_fee;
                $prescription_fee += $order->prescription_fee;
                $refund_fee += $order->vip_company;
                $vip_total += $order->vip_total;
            }
        }

        $data = [
            'shop_id' => $shop->id,
            'shop_name' => $shop->shop_name,
            'mt_id' => $shop->waimai_mt ?: $shop->mtwm,
            'ele_id' => $shop->ele,
            'date' => $date,
            'poi_receive' => $poi_receive,
            'cost' => $vip_cost,
            'running' => $running_fee,
            'prescription' => $prescription_fee,
            'company' => $refund_fee,
            'total' => $vip_total,
        ];

        if ($bill) {
            VipBill::where('id', $bill->id)->update($data);
        } else {
            VipBill::create($data);
        }

        return true;
    }
}
