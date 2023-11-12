<?php

namespace App\Exports;

use App\Models\WmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class WmOrdersExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, WithCustomValueBinder
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
        $request = $this->request;
        $exception = $request->get('exception', 0);
        if (!$sdate = $request->get('sdate')) {
            $sdate = date("Y-m-d");
        }
        if (!$edate = $request->get('edate')) {
            $edate = date("Y-m-d");
        }
        // if ((strtotime($edate) - strtotime($sdate)) / 86400 > 31) {
        //     return $this->error('时间范围不能超过31天');
        // }

        $query = WmOrder::with(['items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc', 'vip_cost');
        }, 'receives', 'running' => function ($query) {
            $query->with(['logs' => function ($q) {
                $q->orderByDesc('id');
            }])->select('id', 'wm_id', 'courier_name', 'courier_phone', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_lng', 'shop_lat');
        }])->select('id','platform','day_seq','shop_id','is_prescription','order_id','delivery_time',
            'estimate_arrival_time','status','recipient_name','recipient_phone','is_poi_first_order','way',
            'recipient_address_detail','wm_shop_name','app_poi_code','ctime','caution','print_number','poi_receive',
            'vip_cost','running_fee','prescription_fee','operate_service_fee','operate_service_fee_status','created_at');

        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        $query->where('created_at', '>=', $sdate)->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));

        if ($exception) {
            if ($exception == 2) {
                $query->where('vip_cost', '<=', 0);
            } elseif ($exception == 1) {
                $query->where(DB::raw("poi_receive - vip_cost - running_fee - prescription_fee"), '<', 0);
            }
        }

        if ($status = $request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $request->get('order_id', '')) {
            $query->where('order_id', 'like', "{$order_id}%");
        }
        // if ($name = $request->get('name', '')) {
        //     $query->where('recipient_name', $name);
        // }
        // if ($phone = $request->get('phone', '')) {
        //     $query->where('recipient_phone', $phone);
        // }
        $query->orderByDesc('id');

        return $query;
    }

    public function map($order): array
    {
        return [
            $order->order_id,
            $order->shop_id,
            $order->app_poi_code,
            $order->wm_shop_name,
            $order->poi_receive,
            $order->vip_cost,
            $order->running_fee,
            $order->prescription_fee,
            $order->operate_service_fee,
            $order->operate_service_fee_status ? '已扣费' : '未扣费',
            $order->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '订单号',
            '中台门店ID',
            '美团门店ID',
            '门店名称',
            '平台结算',
            '成本价',
            '跑腿费',
            '处方费',
            '代运营费',
            '代运营费状态',
            '下单时间',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class  => function(AfterSheet $event) {
                //设置列宽
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(500);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('G')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('H')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('I')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('J')->setWidth(100);
                $event->sheet->getDelegate()->getColumnDimension('K')->setWidth(200);
            }
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A', 'B', 'C', 'D'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
