<?php

namespace App\Http\Controllers;

use App\Http\Requests\Request;
use App\Http\Requests\Supplier\OrderRequest;
use App\Models\Shop;
use App\Models\SupplierOrder;
use App\Models\SupplierProduct;

class SupplierOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
    }

    public function store(OrderRequest $request)
    {
        $user  = $request->user();
        // 开启一个数据库事务
        $order = \DB::transaction(function () use ($user, $request) {
            $address = Shop::find($request->input('address_id'));
            // 创建一个订单
            $order   = new SupplierOrder([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->shop_address,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => $request->input('remark'),
                'total_amount' => 0,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;
            $items = $request->input('items');
            // 遍历用户提交的 SKU
            foreach ($items as $data) {
                $product  = SupplierProduct::query()->find($data['product_id']);
                // 创建一个 OrderItem 并直接与当前订单关联
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price'  => $product->price,
                ]);
                $item->product()->associate($product->id);
                $item->save();
                $totalAmount += $product->price * $data['amount'];
            }

            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);

            // 将下单的商品从购物车中移除
            $product_ids = collect($items)->pluck('product_id');
            $user->carts()->whereIn('product_id', $product_ids)->delete();

            return $order;
        });

        return $order;
    }

    public function received(SupplierOrder $order)
    {
        $order->status = 70;
        $order->save();
        return $this->success();
    }


}
