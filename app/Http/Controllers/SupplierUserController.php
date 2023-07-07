<?php

namespace App\Http\Controllers;

use App\Models\SupplierUser;
use Illuminate\Http\Request;

class SupplierUserController extends Controller
{
    public function user()
    {
        $data = SupplierUser::where("is_auth", 0)->where('yyzz', '<>', '')->get();

        return $this->success($data);
    }

    public function example(Request $request)
    {
        if (!$supplier = SupplierUser::find($request->get("supplier_id", 0))) {
            return $this->error("供货商不存在");
        }

        $status = intval($request->get("status", 1));

        if ($status === 1 && $supplier->is_auth === 0) {
            $supplier->is_auth = 1;
            $supplier->save();
        }

        if ($status === 2) {
            $supplier->is_auth = 2;
            $supplier->reason = $request->get("reason", "");
            $supplier->save();
        }

        return $this->success();
    }
}
