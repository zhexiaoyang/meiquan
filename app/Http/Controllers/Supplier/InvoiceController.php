<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceTitle;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        $user = Auth::user();

        $list = SupplierInvoice::where("user_id", $user->id)->orderBy("id", "desc")->paginate($page_size);

        return $this->page($list);
    }

    public function store()
    {
        $user = Auth::user();

        if (!$title = SupplierInvoiceTitle::query()->where(['user_id' => $user->id])->first()) {
            return $this->error("请先填写发票抬头");
        }

        try {
            \DB::transaction(function () use ($title, $user) {

                $money = 0;
                $ids = [];
                $orders = SupplierOrder::query()->where(["status" => 70, "invoice" => 0, "shop_id" => $user->id])->get();

                if (!empty($orders)) {
                    foreach ($orders as $order) {
                        $ids[] = $order->id;
                        $money += $order->mq_charge_fee;
                        if ($order->payment_method !==0 && $order->payment_method !== 30) {
                            $money += $order->pay_charge_fee;
                        }
                    }
                }
                $invoice = [
                    "user_id" => $user->id,
                    "name" => $user->name,
                    "title" => $title->title ?? "",
                    "enterprise" => $title->enterprise ?? "",
                    "type" => $title->type ?? "",
                    "number" => $title->number ?? "",
                    "bank" => $title->bank ?? "",
                    "no" => $title->no ?? "",
                    "address" => $title->address ?? "",
                    "phone" => $title->phone ?? "",
                    "receiver_name" => $title->receiver_name ?? "",
                    "receiver_address" => $title->receiver_address ?? "",
                    "receiver_phone" => $title->receiver_phone ?? "",
                    "money" => $money,
                ];
                SupplierInvoice::create($invoice);
                SupplierOrder::whereIn("id", $ids)->update(["invoice" => 1, "invoice_at" => date("Y-m-d H:i:s")]);

                \Log::info("[供货商端-发票管理-开发票-事务提交成功");
            });
        } catch (\Exception $e) {
            $message = [
                $e->getCode(),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ];
            \Log::info("[供货商端-发票管理-开发票-事务提交失败", $message);
            return $this->error("操作失败，请稍后再试");
        }

        return $this->success();
    }

    public function order(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        $user_id = Auth::id();

        $orders = SupplierOrder::query()->where(["status" => 70, "invoice" => 0, "shop_id" => $user_id])->paginate($page_size);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $profit_fee = $order->mq_charge_fee;
                if ($order->payment_method !==0 && $order->payment_method !== 30) {
                    $profit_fee += $order->pay_charge_fee;
                } else {
                    $order->pay_charge_fee = 0;
                }
                $order->invoice_fee = (float) sprintf("%.2f",$profit_fee);
            }
        }

        return $this->page($orders);
    }
}
