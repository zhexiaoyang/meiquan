<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class OrdersExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '订单明细导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $type = $this->request->get("type", "");
        $start_date = $this->request->get("start_date", "");
        $end_date = $this->request->get("end_date", "");

        if ($type == "a") {
            $start_date = date("Y-m-01");
            $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
        } elseif ($type == "b") {
            $start_date = date("Y-m-d",strtotime("this week"));
            $end_date = date('Y-m-d', strtotime($start_date) + 86400 * 7);
        } elseif ($type == "c") {
            $start_date = date('Y-m-01', strtotime('-1 month'));
            $end_date = date('Y-m-t', strtotime('-1 month'));
        }

        $query = Order::with("shop")->select("id","order_id","shop_id","ps","money","created_at")->where("status", 70)
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));

        if (!$this->request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $this->request->user()->shops()->pluck('id'));
        }

        return $query;
    }

    public function map($order): array
    {
        $ps = ["", "美团", "蜂鸟", "闪送"];
        return [
            "`".$order->order_id,
            $order->money,
            $order->created_at,
            $ps[$order->ps],
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '金额',
            '创建时间',
            '配送平台',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
}