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

class PrescriptionOrderNewExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '处方订单.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $request = $this->request;
        // 时间判定
        $date_range = $request->get('date_range', '');
        if (!$date_range) {
            return $this->error('日期范围不能为空');
        }
        $date_arr = explode(',', $date_range);
        if (count($date_arr) !== 2) {
            return $this->error('日期格式不正确');
        }
        $sdate = $date_arr[0];
        $edate = $date_arr[1];
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('查询时间范围不能超过31天');
        }
        // 其它筛选
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');

        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime','prescription_fee')
            // ->where('shop_id', $shop_id)
            ->where('is_prescription', 1)
            ->where('ctime', '>=', strtotime($sdate))
            ->where('ctime', '<', strtotime($edate) + 86400);
        if ($order_id) {
            $query->where('order_id', $order_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
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
            $order->prescription_fee,
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
            '处方费',
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
