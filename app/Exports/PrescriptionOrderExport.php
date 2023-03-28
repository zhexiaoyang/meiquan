<?php

namespace App\Exports;

use App\Models\WmOrder;
use App\Models\WmPrescription;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PrescriptionOrderExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '余额明细.xlsx';

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
        $sdate = $request->get('sdate', '');
        $edate = $request->get('edate', '');

        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime')
            ->where('shop_id', $shop_id)
            ->where('is_prescription', 1)
            ->where('ctime', '>=', strtotime($sdate))
            ->where('ctime', '<', strtotime($edate) + 86400);
        if ($order_id) {
            $query->where('order_id', $order_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }

        return $query;
    }

    public function map($order): array
    {
        return [
            $order->order_id,
            $order->wm_shop_name,
            $order->status === 18 ? '已完成' : ($order->status > 18 ? '已取消' : '进行中'),
            $order->platform === 1 ? '美团外卖' : '饿了么',
            date("Y-m-d H:i:s", $order->ctime),
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '门店名称',
            '订单状态',
            '订单平台',
            '下单时间',
        ];
    }

    public function title(): string
    {
        return '处方订单';
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        // if (in_array( $column, ['C', 'D'])) {
        //     $cell->setValueExplicit($value, DataType::TYPE_FORMULA);
        //     return true;
        // }
        return parent::bindValue($cell, $value);
    }
}
