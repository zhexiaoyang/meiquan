<?php

namespace App\Http\Controllers;

use App\Models\SupplierDepot;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;

class ExampleProductController extends Controller
{
    public function index()
    {
        $data = SupplierDepot::with(['first','second'])->where('status', 0)->get();

        return $this->success($data);
    }

    public function setAuth(Request $request)
    {
        if (!$depot = SupplierDepot::query()->find($request->get("depot_id", 0))) {
            return $this->error("药品不存在");
        }

        $status = intval($request->get("status", 1));

        if ($status === 1 && $depot->status === 0) {
            $depot->status = 20;
            $depot->save();
            // if ($depot->save()) {
                // SupplierProduct::query()->where('depot_id', $depot->id)->update(['status' => 20]);
            // }
        }

        if ($status === 2) {
            $depot->status = 5;
            $depot->reason = $request->get("reason", "");
            $depot->save();
        }

        return $this->success();
    }
}
