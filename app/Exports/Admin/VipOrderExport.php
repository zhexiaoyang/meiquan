<?php

namespace App\Exports\Admin;

use App\Models\WmOrder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VipOrderExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '订单列表.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $query = WmOrder::with(['shop' => function ($query) {
            $query->with(['manager','operate','internal']);
        }])->where('is_vip', 1);

        if ($status = $this->request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $this->request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $this->request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $this->request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $this->request->get('order_id', '')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }
        if ($name = $this->request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $this->request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }
        if ($stime = $this->request->get('stime', '')) {
            $query->where('finish_at', '>=', $stime);
        }
        if ($etime = $this->request->get('etime', '')) {
            $query->where('finish_at', '<', date("Y-m-d H:i:s", strtotime($etime) + 86400));
        }

        return $query->orderByDesc('id');
    }

    public function map($order): array
    {
        $platforms = ['','美团','饿了么','京东到家','美全'];
        $status = [1 => '订单创建成功', 4 => '商家已确认', 7 => '备货完成', 9 => '代发货', 12 => '取货中', 14 => '配送中', 16 => '已收货',
            18 => '已完成', 20 => '已关闭', 30 => '已取消', 40 => '售后中', 45 => '售后完成'];
        // 门店名称  下单平台 下单时间， 订单号，订单状态，美团结算金额，商品成本价总计，跑腿费，处方费，完成订单时间，城市经理，运营经理
        return [
            $platforms[$order->platform],
            $order->wm_shop_name,
            $order->order_id,
            $order->poi_receive,
            $order->vip_cost,
            $order->running_fee,
            $order->prescription_fee,
            $status[$order->status],
            $order->created_at,
            $order->finish_at,
            $order->shop->manager->nickname ?? '',
            $order->shop->operate->nickname ?? '',
            $order->shop->internal->nickname ?? '',
        ];
    }

    public function headings(): array
    {
        // 门店名称  下单平台 下单时间， 订单号，订单状态，美团结算金额，商品成本价总计，跑腿费，处方费，完成订单时间，城市经理，运营经理
        return [
            '下单平台',
            '门店名称',
            '订单号',
            '美团结算金额',
            '商品成本价总计',
            '跑腿费',
            '处方费',
            '订单状态',
            '下单时间',
            '完成时间',
            '城市经理',
            '运营经理',
            '内勤经理',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['C'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
