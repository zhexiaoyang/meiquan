<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class VipShopController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $query = Shop::with(['operate' => function($query) {
            $query->select('id', 'nickname');
        },'manager' => function($query) {
            $query->select('id', 'nickname');
        }])->select('id','shop_name','contact_name','contact_phone','vip_logistics','vip_commission',
            'vip_commission_manager','vip_commission_operate','manager_id',
            'vip_settlement','vip_at','operate_id','vip_mt','vip_ele','mtwm','ele');

        $query->whereIn('id', $request->user()->shops()->pluck('id'));
        $query->where('vip_status', 1);

        if ($name = $request->get('name', '')) {
            $query->where('shop_name','like', "%{$name}%");
        }
        if ($platform = $request->get('platform', '')) {
            if ($platform == 1) {
                $query->where('vip_mt',1);
            }
            if ($platform == 2) {
                $query->where('vip_ele',1);
            }
        }
        if ($logistics = $request->get('logistics', '')) {
            $query->where('vip_logistics',$logistics);
        }

        $data = $query->paginate($page_size);

        return $this->page($data);
    }

    public function all(Request $request)
    {
        $query = Shop::select('id','shop_name')->where('vip_status', 1);
        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        $data = $query->get();

        return $this->success($data);
    }
}
