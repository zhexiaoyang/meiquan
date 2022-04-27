<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\VipBill;
use App\Models\WmOrder;
use App\Traits\VipShopHelper;
use Illuminate\Http\Request;

class VipBillController extends Controller
{
    use VipShopHelper;

    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        if (!$sdate = $request->get('sdate')) {
            return $this->error('开始时间不能为空');
        }
        if (!$edate = $request->get('edate')) {
            return $this->error('结束时间不能为空');
        }

        $query = VipBill::where('date', '>=', $sdate)->where('date', '<=', $edate);

        if ($shop_id = $request->get('shop_id', '')) {
            $query->where('shop_id',$shop_id);
        }
        if ($name = $request->get('name', '')) {
            $query->where('shop_name','like', "%{$name}%");
        }

        // 判断角色
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function show(VipBill $bill)
    {
        $date = $bill->date;
        $shop_id = $bill->shop_id;
        $total = ['order_id' => '总计：', 'poi_receive' => 0, 'vip_cost' => 0, 'running_fee' => 0, 'prescription_fee' => 0, 'refund_fee' => 0, 'vip_total' => 0];

        $orders = WmOrder::where('shop_id', $shop_id)->where('bill_date', $date)->get();
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $total['poi_receive'] = sprintf("%.2f", $total['poi_receive'] + $order->poi_receive);
                $total['vip_cost'] = sprintf("%.2f", $total['vip_cost'] + $order->vip_cost);
                $total['running_fee'] = sprintf("%.2f", $total['running_fee'] + $order->running_fee);
                $total['prescription_fee'] = sprintf("%.2f", $total['prescription_fee'] + $order->prescription_fee);
                $total['refund_fee'] = sprintf("%.2f", $total['refund_fee'] + $order->refund_fee);
                $total['vip_total'] = sprintf("%.2f", $total['vip_total'] + $order->vip_total);
            }
        }
        $orders->push(collect($total));

        $res = [
            'orders' => $orders,
            'count' => count($orders) - 1,
            'shop_name' => $bill->shop_name,
            'bill_date' => $bill->date,
        ];

        return $this->success($res);
    }

    public function reset(VipBill $bill)
    {
        $date = $bill->date;
        $shop_id = $bill->shop_id;

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        $this->make_bill($shop, $date, $bill);

        return $this->success();
    }
}
