<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopPlatFormController extends Controller
{
    /**
     * 审核-门店平台管理-门店列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/7 10:57 下午
     */
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $name = trim($request->get("name", ""));

        $query = Shop::query()->select("id","shop_name","ele_shop_id","mt_shop_id","shop_id","shop_id_fn",
            "shop_id_ss","shop_id_dd","shop_id_mqd","shop_id_uu","shop_id_sf");

        if ($name) {
            $query->where("shop_name", "like", "%{$name}%");
        }

        $data = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }

    /**
     * 审核-门店平台管理-更新
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/7 10:57 下午
     */
    public function update(Request $request)
    {
        // 门店ID
        $shop_id = $request->get("shop_id", 0);

        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error("门店不存在");
        }

        // 状态（开通、关闭），开通为门店ID，关闭为空
        $status = $request->get("status", 0);

        // 平台ID
        $platform = $request->get("platform", 0);
        // 美团自动接单，饿了么自动接单 | 美团跑腿，蜂鸟，闪送，达达，美全达
        // 1,2 | 11, 12, 13, 14, 15
        // "mt_shop_id","ele_shop_id","shop_id","shop_id_fn","shop_id_ss","shop_id_dd","shop_id_mqd"

        switch ($platform) {
            case 1:
                $shop->mt_shop_id = $status;
                break;
            case 2:
                $shop->ele_shop_id = $status;
                break;
            case 11:
                $shop->shop_id = $status;
                break;
            case 12:
                $shop->shop_id_fn = $status;
                break;
            case 13:
                $shop->shop_id_ss = $status;
                break;
            case 14:
                $shop->shop_id_dd = $status;
                break;
            case 15:
                $shop->shop_id_mqd = $status;
                break;
            case 16:
                $shop->shop_id_uu = $status;
                break;
            case 17:
                $shop->shop_id_sf = $status;
                break;
        }

        $shop->save();

        return $this->success();
    }
}
