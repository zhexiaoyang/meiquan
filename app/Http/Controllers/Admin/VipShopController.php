<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'vip_commission_manager','vip_commission_operate',
            'vip_settlement','vip_at','operate_id','vip_mt','vip_ele','mtwm','ele');

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

    public function store(Request $request)
    {
        if (!$platform = $request->get('platform')) {
            return $this->error('开通平台不能为空');
        }
        if (empty($platform)) {
            return $this->error('开通平台不能为空');
        }
        if (!in_array(1, $platform)) {
            return $this->error('开通平台不能为空');
        }
        if (!$commission = $request->get('commission', 0)) {
            return $this->error('抽佣不能为 0');
        }
        $commission_manager = $request->get('commission_manager', 0);
        $commission_operate = $request->get('commission_operate', 0);
        if (!$commission = $request->get('commission', 0)) {
            return $this->error('抽佣不能为 0');
        }
        if (!$settlement = $request->get('settlement')) {
            return $this->error('结算周期不能为空');
        }
        if (!$logistics = $request->get('logistics')) {
            return $this->error('配送方式不能为空');
        }
        if (!$operate = $request->get('operate')) {
            return $this->error('业务经理不能为空');
        }
        if (!$shop = Shop::find($request->get('shop_id',0))) {
            return $this->error('门店不存在');
        }

        $shop->operate_id = $operate;
        $shop->vip_settlement = $settlement;
        $shop->vip_commission = $commission;
        $shop->vip_commission_manager = $commission_manager;
        $shop->vip_commission_operate = $commission_operate;
        $shop->vip_logistics = $logistics;

        $mt = false;
        $ele = false;
        if (in_array(1, $platform)) {
            $shop->vip_mt = 1;
            $mt = true;
        }
        if (in_array(2, $platform)) {
            $shop->vip_ele = 1;
            $ele = true;
        }

        if ($mt || $ele) {
            if ($shop->vip_status == 0) {
                $shop->vip_status = 1;
                $shop->vip_at = date("Y-m-d H:i:s");
            }
        } else {
            return $this->error('开通平台不能为空');
        }

        $shop->save();

        return $this->status();
    }

    public function destroy(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id',0))) {
            return $this->error('门店不存在');
        }

        $shop->vip_status = 0;
        $shop->vip_mt = 0;
        $shop->vip_ele = 0;
        $shop->save();

        return $this->status();
    }

    public function all()
    {
        $data = Shop::select('id','shop_name')->where('vip_status', 1)->get();

        return $this->success($data);
    }
}
