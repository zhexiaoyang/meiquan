<?php

namespace App\Http\Controllers;

use App\Models\SupplierDepot;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;

class ExampleProductController extends Controller
{
    public function index()
    {
        $data = SupplierDepot::query()->select('id', 'name', 'generi_name', 'cover', 'spec', 'unit', 'is_otc','type', 'created_at')
            ->where('status', 0)->get();

        return $this->success($data);
    }

    public function setAuth(Request $request)
    {
        if (!$depot = SupplierDepot::query()->find($request->get("depot_id", 0))) {
            return $this->error("药品不存在");
        }

        if ($depot->status === 0) {
            $depot->status = 20;
            if ($depot->save()) {
                SupplierProduct::query()->where('depot_id', $depot->id)->update(['status' => 20]);
            }
        }

        return $this->success();
    }
}
