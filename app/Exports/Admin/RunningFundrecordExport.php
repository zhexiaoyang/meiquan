<?php

namespace App\Exports\Admin;

use App\Models\Order;
use App\Models\WmPrescription;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RunningFundrecordExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '跑腿结算导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $request = $this->request;
        $start_date = $request->get("start_date");
        $end_date = $request->get("end_date");
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        $end_date = date("Y-m-d", $end_time + 86400);

        $where = [
            ["status", 70],
            ["over_at", '>', $start_date],
            ["over_at", '<', $end_date],
        ];

        $query = Order::with(['shop', 'deduction'])->where($where)->orderByDesc("over_at");

        return $query;
    }

    public function map($order): array
    {
        return [
            "'" . $order->order_id,
            $order->shop->shop_name,
            $order->money,
            $order->over_at,
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '门店名称',
            '金额(元)',
            '完成时间',
        ];
    }

    public function title(): string
    {
        return '跑腿结算导出';
    }
}
