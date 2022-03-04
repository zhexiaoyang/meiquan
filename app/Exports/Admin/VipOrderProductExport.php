<?php

namespace App\Exports\Admin;

use App\Models\WmOrder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class VipOrderProductExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromArray, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '订单列表.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function array(): array
    {
        $query = WmOrder::with(['shop' => function ($query) {
            $query->with(['manager','operate','internal']);
        },'items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc','vip_cost');
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

        $orders = $query->orderByDesc('id')->get();

        $data = [];

        if (!empty($orders)) {
            $platforms = ['','美团','饿了么','京东到家','美全'];
            $status = [1 => '订单创建成功', 4 => '商家已确认', 7 => '备货完成', 9 => '代发货', 12 => '取货中', 14 => '配送中', 16 => '已收货',
                18 => '已完成', 20 => '已关闭', 30 => '已取消', 40 => '售后中', 45 => '售后完成'];
            foreach ($orders as $order) {
                if (!empty($order->items)) {
                    foreach ($order->items as $item) {
                        $data[] = [
                            'product_name' => $item->food_name,
                            'product_upc' => $item->upc,
                            'product_quantity' => $item->quantity,
                            'product_price' => $item->price,
                            'product_cost' => $item->vip_cost,
                            'platform' => $platforms[$order->platform],
                            'shop_name' => $order->wm_shop_name,
                            'order_id' => $order->order_id,
                            'status' => $status[$order->status],
                            'created_at' => $order->created_at,
                            'finish_at' => $order->finish_at,
                            'manager' => $order->shop->manager->nickname ?? '',
                            'operate' => $order->shop->operate->nickname ?? '',
                            'internal' => $order->shop->internal->nickname ?? '',
                        ];
                    }
                }
            }
        }

        return $data;
    }

    public function map($data): array
    {
        // 门店名称 下单平台，下单时间，订单状态，商品名称，订单号，条形码，购买数量，商品成本价，完成订单时间，城市经理，运营经理
        return [
            $data['product_name'],
            $data['product_upc'],
            $data['product_quantity'],
            $data['product_price'],
            $data['product_cost'],
            $data['platform'],
            $data['shop_name'],
            $data['order_id'],
            $data['status'],
            $data['created_at'],
            $data['finish_at'],
            $data['manager'],
            $data['operate'],
            $data['internal'],
        ];
    }

    public function headings(): array
    {
        return [
            '商品名称',
            '条形码',
            '购买数量',
            '商品销售价',
            '商品成本价',
            '下单平台',
            '门店名称',
            '订单号',
            '订单状态',
            '下单时间',
            '完成订单时间',
            '城市经理',
            '运营经理',
            '内勤经理',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['B','H'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
