<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOnlineShopSettlementExport;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\OnlineShop;
use Illuminate\Http\Request;

class OnlineShopController extends Controller
{
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $name = trim($request->get("name", ""));

        $query = OnlineShop::with('contract');

        // 非管理员只能查看所指定的门店
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }

        $shops = $query->orderBy("id", "desc")->paginate($page_size);

        $contracts = Contract::select('id', 'name')->get()->toArray();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $data = $contracts;
                foreach ($data as $k => $v) {
                    $data[$k]['status'] = 0;
                    if (!empty($shop->contract)) {
                        foreach ($shop->contract as $item) {
                            if ($v['id'] === $item->contract_id) {
                                $data[$k]['status'] = $item->status;
                            }
                        }
                    }
                }
                unset($shop->contract);
                $shop->contract = $data;
            }
        }

        return $this->page($shops);
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
