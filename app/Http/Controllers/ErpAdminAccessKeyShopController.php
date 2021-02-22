<?php

namespace App\Http\Controllers;

use App\Models\ErpAccessShop;
use Illuminate\Http\Request;

class ErpAdminAccessKeyShopController extends Controller
{
    public function index(Request $request)
    {
        if (!$access_id = $request->get("access_id")) {
            return $this->error("参数错误");
        }
        $page_size = $request->get("page_size", 10);

        $data = ErpAccessShop::query()->where("access_id", $access_id)->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        if (!$access_id = $request->get("access_id")) {
            return $this->error("参数错误");
        }
        $data['access_id'] = $access_id;

        if (!$shop_name = $request->get("shop_name")) {
            return $this->error("门店名称不能为空");
        }
        $data['shop_name'] = $shop_name;

        if (!$mt_shop_id = $request->get("mt_shop_id")) {
            return $this->error("美团ID不能为空");
        }
        $data['mt_shop_id'] = $mt_shop_id;

        if (!$type = $request->get("type")) {
            return $this->error("品牌不能为空");
        }
        if (!in_array($type, [1, 4])) {
            return $this->error("品牌错误");
        }
        $data['type'] = $type;

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
