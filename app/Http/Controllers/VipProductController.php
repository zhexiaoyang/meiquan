<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\VipProduct;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VipProductController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $query = VipProduct::whereIn('shop_id', $request->user()->shops()->pluck('id'));

        if ($shop_id = $request->get('shop_id', '')) {
            $query->where('shop_id',$shop_id);
        }
        if ($name = $request->get('name', '')) {
            $query->where('name','like', "%{$name}%");
        }
        if ($category = $request->get('category', '')) {
            $query->where('category_name','like', "%{$category}%");
        }
        if ($stock = $request->get('stock', '')) {
            if ($stock == 1) {
                $query->where('stock','>',0);
            }
            if ($stock == 2) {
                $query->where('stock',0);
            }
        }
        if ($cost = $request->get('cost', '')) {
            if ($cost == 1) {
                $query->where('cost','>',0);
            }
            if ($cost == 2) {
                $query->where('cost',0);
            }
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }
}
