<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpressOrder;
use Illuminate\Http\Request;

class ExpressOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $order_id = $request->get('order_id', '');

        $query = ExpressOrder::with(['shop' => function($query) {
            $query->select('id', 'shop_name', 'contact_name', 'contact_phone', 'shop_address');
        }, 'logs'])->where('user_id', $request->user()->id);

        if ($order_id = $request->get('order_id')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }
}
