<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoiceTitle;
use Illuminate\Support\Facades\Auth;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;

class InvoiceTitleController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        $title = SupplierInvoiceTitle::where("user_id", $user->id)->first();

        return $this->success($title);
    }

    public function save(Request $request)
    {
        if (!$request->get("title")) {
            return $this->error("发票抬头不能为空");
        }
        if (!$request->get("number")) {
            return $this->error("税务登记证号不能为空");
        }


        $user = Auth::user();

        $title = SupplierInvoiceTitle::where("user_id", $user->id)->first();

        if ($title) {
            $title->title = $request->get("title");
            $title->number = $request->get("number");
            $title->bank = $request->get("bank", "");
            $title->no = $request->get("no", "");
            $title->address = $request->get("address", "");
            $title->phone = $request->get("phone", "");
            $title->receiver_name = $request->get("receiver_name", "");
            $title->receiver_address = $request->get("receiver_address", "");
            $title->receiver_phone = $request->get("receiver_phone", "");
            $title->save();
        } else {
            $data = [
                "user_id" => $user->id,
                "title" => $request->get("title"),
                "enterprise" => 1,
                "type" => 1,
                "number" => $request->get("number"),
                "bank" => $request->get("bank", ""),
                "no" => $request->get("no", ""),
                "address" => $request->get("address", ""),
                "phone" => $request->get("phone", ""),
                "receiver_name" => $request->get("receiver_name"),
                "receiver_address" => $request->get("receiver_address"),
                "receiver_phone" => $request->get("receiver_phone")
            ];
            SupplierInvoiceTitle::create($data);
        }

        return $this->success();
    }
}
