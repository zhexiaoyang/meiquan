<?php

namespace App\Http\Controllers;

use App\Models\SupplierUser;
use Illuminate\Http\Request;

class SupplierUserController extends Controller
{
    public function user()
    {
        $data = SupplierUser::query()->select("id", "name", "phone", "avatar", "yyzz", "ypjy", "auth_at")
            ->where("is_auth", 0)->where('yyzz', '<>', '')->get();

        return $this->success($data);
    }

    public function example(Request $request)
    {
        if (!$supplier = SupplierUser::query()->find($request->get("supplier_id", 0))) {
            return $this->error("供货商不存在");
        }

        if ($supplier->is_auth === 0) {
            $supplier->is_auth = 1;
            $supplier->save();
        }

        return $this->success();
    }
}
