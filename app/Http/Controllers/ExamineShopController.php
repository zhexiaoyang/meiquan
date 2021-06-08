<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtShop;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamineShopController extends Controller
{
    public function index(Request $request)
    {
        $search_key = $request->get("search_key", "");
        $page_size = $request->get("page_size", 0);

        if ($page_size > 100 || $page_size < 10) {
            $page_size = 10;
        }

        $query = Shop::query()->select("id","shop_name","shop_address","category","second_category","contact_name",
            "contact_phone","shop_lng","shop_lat","status","created_at")
            ->where("status", 0)->where("id", ">", 900);

        if ($search_key) {
            $query->where("shop_name", "like", "%{$search_key}%");
        }

        $shops = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($shops);
    }

    public function store(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);
        $status = $request->get("status", 0);

        if (!in_array($status, [10, 20])) {
            return $this->error("状态错误");
        }

        if (!$shop = Shop::query()->where(["id" => $shop_id ])->first()) {
            return $this->error("门店不存在");
        }

        if ($shop->status > 0) {
            return $this->error("门店已审核");
        }

        $shop->status = $status;

        if ($status === 10) {
            $shop->status_error = $request->get("reason", "");
        }

        $shop->save();

        if ($shop->status === 20) {
            dispatch(new CreateMtShop($shop));
        }

        return $this->success('审核成功');
    }

    public function autoList(Request $request)
    {
        $search_key = $request->get("search_key", "");
        $page_size = $request->get("page_size", 0);

        if ($page_size > 100 || $page_size < 10) {
            $page_size = 10;
        }

        $query = Shop::query()->select("id","mtwm","ele","shop_name","shop_address","category","second_category","contact_name",
            "contact_phone","shop_lng","shop_lat","status","created_at","mt_shop_id","ele_shop_id")
            ->where(function ($query) {
                $query->where(
                    [
                        ['mt_shop_id', ""],
                        ['mtwm', '<>', ""],
                        ['mtwm', '<>', 0]
                    ]
                )->orWhere(
                    [
                        ['ele_shop_id', ""],
                        ['ele', '<>', ""],
                        ['ele', '<>', 0]
                    ]
                );
            });

        if ($search_key) {
            $query->where("shop_name", "like", "%{$search_key}%");
        }
        DB::connection()->enableQueryLog();
        $shops = $query->orderBy("id", "desc")->paginate($page_size);
        $queries = DB::connection()->getQueryLog();

        \Log::info($queries);

        return $this->page($shops);
    }

    public function AutoStore(Request $request)
    {
        $shop_id = $request->get("shop_id", 0);
        $status = $request->get("status", 0);

        if (!in_array($status, [1, 2])) {
            return $this->error("状态错误");
        }

        if (!$shop = Shop::query()->where(["id" => $shop_id ])->first()) {
            return $this->error("门店不存在");
        }

        if ($status === 1) {
            if ($shop->mtwm) {
                $shop->mt_shop_id = $shop->mtwm;
            }
            if ($shop->ele) {
                $shop->ele_shop_id = $shop->ele;
            }
        }

        if ($status === 2) {
            $shop->mtwm = "";
            $shop->ele = "";
        }

        $shop->save();

        return $this->success('审核成功');
    }

    public function update(Request $request)
    {
        $id = $request->get("id", 0);
        $name = $request->get("name", '');

        if (empty($name)) {
            return $this->error("门店名称不能为空");
        }

        if (!$shop = Shop::query()->find($id)) {
            return $this->error("门店不存在");
        }

        $shop->shop_name = $name;
        $shop->save();

        return $this->success();
    }
}
