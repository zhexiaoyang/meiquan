<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WmOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $exception = $request->get('exception', 0);

        if (!$sdate = $request->get('sdate')) {
            $sdate = date("Y-m-d");
        }
        if (!$edate = $request->get('edate')) {
            $edate = date("Y-m-d");
        }
        if ((strtotime($edate) - strtotime($sdate)) / 86400 > 31) {
            return $this->error('时间范围不能超过31天');
        }

        $query = WmOrder::with(['items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc', 'vip_cost');
        }, 'running' => function ($query) {
            $query->with(['logs' => function ($q) {
                $q->orderByDesc('id');
            }])->select('id', 'wm_id', 'courier_name', 'courier_phone', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_lng', 'shop_lat');
        }])->select('id','platform','day_seq','shop_id','is_prescription','order_id','delivery_time','estimate_arrival_time',
            'status','recipient_name','recipient_phone','is_poi_first_order','way','recipient_address_detail','wm_shop_name',
            'ctime','caution','print_number','poi_receive','vip_cost','running_fee','prescription_fee','operate_service_fee')
            ->where('shop_id', '>', 0);

        // $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        $query->where('created_at', '>=', $sdate)->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));

        if ($exception) {
            if ($exception == 2) {
                $query->where('vip_cost', '<=', 0);
            } elseif ($exception == 1) {
                // $query->where(DB::raw('poi_receive') - DB::raw('vip_cost') - DB::raw('running_fee') - DB::raw('prescription_fee'), '<', 0);
                $query->where(DB::raw("poi_receive - vip_cost - running_fee - prescription_fee"), '<', 0);
            }
        }

        if ($status = $request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $request->get('order_id', '')) {
            $query->where('order_id', 'like', "{$order_id}%");
        }
        if ($name = $request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function show(Request $request)
    {
        if (!$order = WmOrder::with('items')->find($request->get('order_id', 0))) {
            return $this->error('订单不存在');
        }

        return $this->success($order);
    }
}
