<?php

namespace App\Http\Controllers;

use App\Libraries\KuaiDi\KuaiDi;
use App\Models\ExpressOrder;
use App\Models\Shop;
use Illuminate\Http\Request;

class ExpressOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpressOrder::with(['shop' => function($query) {
            $query->select('id', 'shop_name');
        }])->where('user_id', $request->user()->id);

        if ($order_id = $request->get('order_id')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }

        $data = $query->orderByDesc('id')->paginate($request->get('page_size', 10));
        return $this->page($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'shop_id' => 'required',
            'receive_name' => 'required',
            'receive_phone' => 'required',
            'province' => 'required',
            'city' => 'required',
            'area' => 'required',
            'address' => 'required',
        ], [], [
            'shop_id' => '发单门店',
            'receive_name' => '收货人',
            'receive_phone' => '收货人电话',
            'province' => '地区',
            'city' => '地区',
            'area' => '地区',
            'address' => '详细地址',
        ]);
        $data = $request->only('shop_id','receive_name','receive_phone','province','city','area','address','goods','platform');
        if (!$shop = Shop::find($data['shop_id'] ?? 0)) {
            return $this->error('门店不存在');
        }
        $data['user_id'] = $shop->own_id;
        $order = ExpressOrder::create($data);
        $kuaidi = New KuaiDi(config('kuaidi'));
        $res = $kuaidi->create_order($order, $shop);
        if ($res['returnCode'] == 200) {
            $order->task_id = $res['data']['taskId'];
            $order->order_id = $res['data']['orderId'];
        } else {
            $order->delete();
        }
        return $this->success();
    }

    public function destroy(Request $request, ExpressOrder $expressOrder)
    {
        $kuaidi = New KuaiDi(config('kuaidi'));
        $res = $kuaidi->cancel_order($expressOrder);

        if ($res['returnCode'] != 200) {
            return $this->error($res['message'] ?? '取消失败');
        }

        return $this->success();
    }
}
