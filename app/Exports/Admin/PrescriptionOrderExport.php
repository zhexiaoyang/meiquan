<?php

namespace App\Exports\Admin;

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

class PrescriptionOrderExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '处方订单导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $request = $this->request;
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $stime = $request->get('stime', '');
        $etime = $request->get('etime', '');

        $query = WmPrescription::query();

        if ($order_id) {
            $query->where('outOrderID', $order_id);
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($stime) {
            $query->where('rpCreateTime', '>=', $stime);
        }
        if ($etime) {
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        }

        return $query;
    }

    public function map($order): array
    {
        return [
            "'" . $order->outOrderID,
            $order->storeName,
            $order->outDoctorName,
            $order->money,
            $order->reviewStatus,
            $order->orderStatus,
            $order->rpCreateTime,
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '门店名称',
            '医生名称',
            '金额(元)',
            '审方状态',
            '订单状态',
            '处方开具时间',
        ];
    }

    public function title(): string
    {
        return '处方订单';
    }
}
