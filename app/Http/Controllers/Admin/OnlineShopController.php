<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOnlineShopSettlementExport;
use App\Http\Controllers\Controller;
use App\Models\OnlineShop;
use Illuminate\Http\Request;

class OnlineShopController extends Controller
{
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $name = trim($request->get("name", ""));

        $query = OnlineShop::query();

        // 非管理员只能查看所指定的门店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }

        $data = $query->orderBy("id", "desc")->paginate($page_size);

        return $this->page($data);
    }

    public function show(OnlineShop $shop)
    {
        return $this->success($shop);
    }

    public function export(Request $request, AdminOnlineShopSettlementExport $adminOnlineShopSettlementExport)
    {
        return $adminOnlineShopSettlementExport->withRequest($request);
    }
}
