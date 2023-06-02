<?php

namespace App\Http\Controllers;

use App\Models\ErpAccessShop;
use App\Models\Shop;
use Illuminate\Http\Request;

class ErpAdminAccessKeyShopController extends Controller
{
    public function index(Request $request)
    {
        if (!$access_id = $request->get("access_id")) {
            return $this->error("参数错误");
        }
        $page_size = $request->get("page_size", 10);

        $data = ErpAccessShop::with(['shop' => function($query) {
            $query->select('id', 'waimai_mt', 'waimai_ele');
        }])->where("access_id", $access_id)->paginate($page_size);

        return $this->page($data, [], 'data');
    }

    public function store(Request $request)
    {
        if (!$access_id = $request->get("access_id")) {
            return $this->error("参数错误");
        }
        $data['access_id'] = $access_id;

        if (!$shop_id = $request->get("mq_shop_id")) {
            return $this->error("请选择门店");
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error("门店不存在");
        }
        // if (!$shop->waimai_mt) {
        //     return $this->error("该门店没有绑定美团外卖，请先绑定");
        // }
        // if (!in_array($shop->meituan_bind_platform, [31, 4])) {
        //     return $this->error("该门店未绑定到民康或者闪购，请核对");
        // }

        $data['shop_name'] = $shop->shop_name;
        $data['shop_id'] = $shop->id;
        $data['mt_shop_id'] = $shop->waimai_mt;
        $data['mq_shop_id'] = $shop->id;
        $data['ele_shop_id'] = $shop->waimai_ele;

        // if (!$type = $request->get("type")) {
        //     return $this->error("品牌不能为空");
        // }
        $data['type'] = $shop->meituan_bind_platform;

        ErpAccessShop::query()->create($data);

        return $this->success();
    }

    public function update(Request $request)
    {
        if (!$id = $request->get("id")) {
            return $this->error("门店不存在");
        }

        if (!$shop_name = $request->get("shop_name")) {
            return $this->error("门店名称不能为空");
        }

        if (!$mt_shop_id = $request->get("mt_shop_id")) {
            return $this->error("美团ID不能为空");
        }

        if (!$type = $request->get("type")) {
            return $this->error("品牌不能为空");
        }

        if (!$shop = ErpAccessShop::query()->find($id)) {
            return $this->error("门店不存在");
        }

        $shop->shop_name = $shop_name;
        $shop->shop_id = $mt_shop_id;
        $shop->mt_shop_id = $mt_shop_id;
        $shop->type = $type;
        $shop->save();

        return $this->success();
    }

    public function destroy(Request $request)
    {
        if (!$access = ErpAccessShop::find($request->get("id", 0))) {
            return $this->error("门店不存在");
        }

        $access->delete();

        return $this->success();
    }
}
