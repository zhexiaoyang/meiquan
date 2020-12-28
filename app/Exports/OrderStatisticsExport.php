<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class OrderStatisticsExport implements WithStrictNullComparison, Responsable, FromArray, WithMapping, WithHeadings, WithTitle, ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    private $fileName = '订单统计导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function array(): array
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

        $start = strtotime($start_date);
        $end = strtotime($end_date);

        while ($start <= $end) {
            $data[date("Y-m-d", $start)]["date"] = date("Y-m-d", $start);
            $data[date("Y-m-d", $start)]["num"] = 0;
            $data[date("Y-m-d", $start)]["money"] = 0;
            $start += 86400;
        }

        $query = Order::select("id","shop_id","ps","money","created_at")->where("status", 70)
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));

        if (!$this->request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $this->request->user()->shops()->pluck('id'));
        }

        $orders = $query->get();

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $data[date("Y-m-d", strtotime($order->created_at))]["money"] += $order->money * 100;
                $data[date("Y-m-d", strtotime($order->created_at))]["num"]++;
            }
        }

        foreach ($data as $k => $v) {
            $data[$k]["money"] = $v["money"] / 100;
        }

        $data = array_values($data);

        return $data;
    }

    public function map($order): array
    {
        return [
            $order['date'],
            $order['money'],
            $order['num'],
        ];
    }

    public function headings(): array
    {
        return [
            '日期',
            '配送费',
            '有效订单数',
        ];
    }

    public function title(): string
    {
        return '订单统计';
    }

    public function sheets(): array
    {
        return [
            (new OrdersExport())->withRequest($this->request),
            (new self())->withRequest($this->request),
        ];
    }
}
