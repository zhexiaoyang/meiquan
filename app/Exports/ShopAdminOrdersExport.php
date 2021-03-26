<?php

namespace App\Exports;

use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ShopAdminOrdersExport implements WithStrictNullComparison, Responsable, FromArray, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '订单导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function array(): array
    {

        // $page_size = $this->request->get("page_size", 10);
        $search_key = $this->request->get("search_key", '');
        $status = $this->request->get("status", null);
        $start_date = $this->request->get("start_date", '');
        $end_date = $this->request->get("end_date", '');

        $query = SupplierOrder::with(["shop", "items"])->orderBy("id", "desc");

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('no', 'like', "%{$search_key}%");
                $query->orWhere('receive_shop_name', 'like', "%{$search_key}%");
            });
        }

        if (!is_null($status) && $status != "") {
            $query->where("status", $status);
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $orders = $query->get();

        $_res = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $order_info['no'] = $order->no;
                $order_info['shop_name'] = $order->address['shop_name'] ?? '';
                $order_info['contact_name'] = $order->address['contact_name'] ?? '';
                $order_info['contact_phone'] = $order->address['contact_phone'] ?? '';
                $order_info['shipping_fee'] = $order->shipping_fee;
                $order_info['total_fee'] = $order->total_fee;
                $order_info['frozen_fee'] = $order->frozen_fee;
                $order_info['product_fee'] = $order->product_fee;
                $order_info['pay_charge_fee'] = $order->pay_charge_fee;
                $order_info['mq_charge_fee'] = $order->mq_charge_fee;
                $order_info['payment_no'] = $order->payment_no;
                $order_info['payment_method'] = $order->payment_method;
                $order_info['cancel_reason'] = $order->cancel_reason;
                $order_info['status'] = $order->status;
                $order_info['shop_name'] = $order->shop->name ?? "";
                $order_info['paid_at'] = $order->paid_at ? date("Y-m-d H:i:s", strtotime($order->paid_at)) : '-';
                $order_info['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));

                // 结算金额（js有精度问题，放到程序里面做）
                $profit_fee = $order->total_fee - $order->mq_charge_fee;
                if ($order->payment_method !==0 && $order->payment_method !== 30) {
                    $profit_fee -= $order->pay_charge_fee * 100;
                }
                $order_info['profit_fee'] = (float) sprintf("%.2f",$profit_fee);

                // 判断支付手续费
                if ($order->status !== 0 && $order->status !== 30) {
                    $order_info['pay_charge_fee'] = $order->pay_charge_fee;
                } else {
                    $order_info['pay_charge_fee'] = 0;
                }

                $result[] = $order_info;
            }
        }

        return $result;
    }

    public function map($order): array
    {
        $pay_status = [0 => '', 1 => "支付宝", 2 => "微信", 8 => "余额", 30 => "余额"];
        $order_status = [0 => "未付款", 30 => "待发货", 50 => "已发货", 70 => "已收货", 90 => "已取消", 99 => "已取消"];

        $money = $order['total_fee'] - $order['mq_charge_fee'];
        $pay_charge_fee = $order['pay_charge_fee'];
        if ($order['payment_method'] !==0 && $order['payment_method'] !== 30) {
            $money -= $order['pay_charge_fee'];
        } else {
            $pay_charge_fee = 0;
        }

        return [
            isset($order['no']) ? "`".$order['no'] : '',
            $order['shop_name'] ?? '',
            $order['contact_name'] ?? '',
            $order['contact_phone'] ?? '',
            $order['total_fee'] ?? '',
            $order['frozen_fee'] ?? '',
            $order['product_fee'] ?? '',
            $order['shipping_fee'] ?? '',
            isset($order['payment_method']) ? $pay_status[$order['payment_method']] : '',
            $order['payment_no'] ?? '',
            isset($order['status']) ? $order_status[$order['status']] : '',
            $pay_charge_fee,
            $order['mq_charge_fee'] ?? '',
            $money,
            $order['created_at'] ?? '',
            $order['paid_at'] ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '收货门店',
            '收货人',
            '收货手机号',
            '订单总金额',
            '冻结余额支付金额',
            '商品金额',
            '配送费',
            '支付方式',
            '支付单号',
            '订单状态',
            '支付手续费',
            '平台服务费',
            '结算金额',
            '下单时间',
            '支付时间',
        ];
    }

    public function title(): string
    {
        return '订单列表';
    }
}
