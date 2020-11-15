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

class SupplierOrderProductsExport implements WithStrictNullComparison, Responsable, FromArray, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '订单商品导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function array(): array
    {

        $user = Auth::user();

        $search_key = $this->request->get("search_key", '');
        $status = $this->request->get("status", null);
        $start_date = $this->request->get("start_date", '');
        $end_date = $this->request->get("end_date", '');

        $query = SupplierOrder::with('items')->orderBy("id", "desc")->where("shop_id", $user->id);

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('no', 'like', "%{$search_key}%");
                $query->orWhere('shop_name', 'like', "%{$search_key}%");
            });
        }

        if (!is_null($status)) {
            $query->where("status", $status);
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $orders = $query->get();

        $result = [];

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $tmp['no'] = $order->no;
                $tmp['shop_name'] = $order->address['shop_name'];
                $tmp['contact_name'] = $order->address['contact_name'];
                $tmp['contact_phone'] = $order->address['contact_phone'];
                $tmp['address'] = $order->address['address'];
                $tmp['total_fee'] = $order->total_fee;
                $tmp['status'] = $order->status;
                $tmp['created_at'] = $order->created_at;
                if (!empty($order->items)) {
                    foreach ($order->items as $item) {
                        $_tmp['product_name'] = $item->name;
                        $_tmp['product_upc'] = $item->upc;
                        $_tmp['product_spec'] = $item->spec;
                        $_tmp['product_unit'] = $item->unit;
                        $_tmp['product_amount'] = $item->amount;
                        $_tmp['product_price'] = $item->price;
                        $result[] = array_merge($tmp, $_tmp);
                        unset($_tmp);
                    }
                } else {
                    $result[] = $tmp;
                }
                unset($tmp);
            }
        }

        return $result;
    }

    public function map($order): array
    {
        $status = [0 => "未付款", 30 => "待发货", 50 => "已发货", 70 => "已收货", 90 => "已取消"];

        return [
            isset($order['no']) ? "`".$order['no'] : '',
            $order['shop_name'] ?? '',
            $order['contact_name'] ?? '',
            $order['contact_phone'] ?? '',
            // $order->address['meituan_id'],
            $order['address'] ?? '',
            $order['total_fee'] ?? '',
            isset($order['status']) ? $status[$order['status']] : '',
            $order['created_at'] ?? '',
            $order['product_name'] ?? '',
            $order['product_upc'] ?? '',
            $order['product_spec'] ?? '',
            $order['product_unit'] ?? '',
            $order['product_amount'] ?? '',
            $order['product_price'] ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '门店名称',
            '收货人',
            '收货电话',
            // '美团ID',
            '收货地址',
            '支付金额',
            '状态',
            '创建时间',
            '商品名称',
            '商品条形码',
            '商品规格',
            '商品单位',
            '商品数量',
            '商品价格',
        ];
    }

    public function title(): string
    {
        return '订单商品列表';
    }
}
