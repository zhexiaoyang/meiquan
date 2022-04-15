<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VipProduct;
use App\Models\VipProductException;
use Illuminate\Http\Request;

class VipProductExceptionController extends Controller
{
    public function index(Request $request)
    {

        $page_size = $request->get('page_size', 10);
        $status = $request->get('status');
        $error_type = $request->get('error_type');

        $query = VipProductException::query();

        if (is_numeric($error_type)) {
            $query->where('error_type',$error_type);
        }
        if (is_numeric($status)) {
            $query->where('status',$status);
        }
        if ($shop_id = $request->get('shop_id', '')) {
            $query->where('shop_id',$shop_id);
        }
        if ($name = $request->get('name', '')) {
            $query->where('name','like', "%{$name}%");
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data, false, 'data');
    }

    public function statistics()
    {
        $res = [
            'error1' => VipProductException::where(['status' => 0, 'error_type' => 1])->count(),
            'error2' => VipProductException::where(['status' => 0, 'error_type' => 2])->count(),
        ];

        return $this->success($res);
    }

    public function update(VipProductException $exception, Request $request)
    {
        $cost = $request->get('cost');

        if (!$cost) {
            return $this->error('价格错误');
        }

        if (!$product = VipProduct::find($exception->product_id)) {
            return $this->error('商品不存在');
        }

        if ($cost > 0) {
            $product->cost = $cost;
            $product->save();
            $exception->update(['status' => 1, 'message' => "修改成本价({$cost}元)"]);
        }

        return $this->success();
    }

    public function ignore(Request $request)
    {
        $id = $request->get("id");
        $msg = $request->get("msg");

        if (empty($msg)) {
            return $this->error('忽略原因不能为空');
        }

        // if (!is_array($ids) || empty($ids)) {
        //     return $this->error('参数错误');
        // }

        if (!$exception = VipProductException::find($id)) {
            return $this->error('商品不存在');
        }

        $exception->update(['status' => 2, 'message' => $msg]);

        // VipProductException::whereIn('id', $ids)->update(['status' => 2, 'message' => $msg]);

        return $this->success();
    }
}
