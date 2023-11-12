<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopRestLog;
use Illuminate\Http\Request;

class ShopRestController extends Controller
{
    public function index(Request $request)
    {
        $query = ShopRestLog::query();
        if ($shop_id = (int) $request->get('shop_id')) {
            $query->where('shop_id', $shop_id);
        }
        if ($name = $request->get('name')) {
            $query->where('shop_name', 'like', "%{$name}%");
        }
        if ($type = (int) $request->get('type')) {
            if ($type === 1) {
                $query->where('type', 1);
            } elseif ($type === 2) {
                $query->where('type', 2);
            }
        }
        if ($status = (int) $request->get('status')) {
            if ($status === 1) {
                $query->where('status', 1);
            } elseif ($status === 2) {
                $query->where('status', 0);
            }
        }

        $data = $query->orderByDesc('id')->paginate($request->get('page_size'));

        return $this->page($data, [],'data');
    }
}
