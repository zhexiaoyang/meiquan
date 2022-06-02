<?php

namespace App\Exports\Admin;

use App\Models\VipBill;
use App\Models\VipBillItem;
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

class VipOrderBillShopExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '订单列表.xlsx';

    protected $request;
    protected $shop_id;
    protected $sdate;
    protected $edate;

    public function withRequest(Request $request, $shop_id, $sdate, $edate)
    {
        $this->request = $request;
        $this->shop_id = $shop_id;
        $this->sdate = $sdate;
        $this->edate = $edate;
        return $this;
    }

    public function query()
    {
        $query = VipBillItem::with(['shop' => function ($query) {
            $query->with(['manager','operate','internal']);
        }]);

        if ($this->shop_id) {
            $query->where('shop_id', $this->shop_id);
        }
        $query->where('bill_date', '>=', $this->sdate);
        $query->where('bill_date', '<=', $this->edate);

        return $query->orderByDesc('id');
    }

    public function map($order): array
    {
        $platforms = ['','美团','饿了么','京东到家','美全'];
        $status = [1 => '订单创建成功', 4 => '商家已确认', 7 => '备货完成', 9 => '代发货', 12 => '取货中', 14 => '配送中', 16 => '已收货',
            18 => '已完成', 20 => '已关闭', 30 => '已取消', 40 => '售后中', 45 => '售后完成'];
        $trade_types = [1 => '美团外卖订单',2 => '美团订单退款',3 => '美团订单部分退款',11 => '饿了么外卖订单',12 => '饿了么订单退款',
            13 => '饿了么订单部分退款',101 => '跑腿订单扣款',102 => '跑腿订单取消扣款',];
        // 门店名称  下单平台 下单时间， 订单号，订单状态，美团结算金额，商品成本价总计，跑腿费，处方费，完成订单时间，城市经理，运营经理
        return [
            $platforms[$order->platform],
            $order->app_poi_code,
            $order->wm_shop_name,
            $order->order_no,
            $trade_types[$order->trade_type],
            $order->vip_settlement,
            $order->vip_cost,
            $order->vip_permission,
            $order->vip_total,
            $order->vip_company,
            $order->vip_operate,
            $order->vip_city,
            $order->vip_internal,
            $order->vip_business,
            $order->vip_commission_company,
            $order->vip_commission_operate,
            $order->vip_commission_city,
            $order->vip_commission_internal,
            $order->vip_commission_business,
            $status[$order->status],
            $order->created_at,
            $order->order_at,
            $order->finish_at,
            $order->refund_at,
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
            '平台ID',
            '门店名称',
            '订单号',
            '交易类型',
            '结算金额',
            '成本价',
            '审方费',
            '总利润',
            '公司分佣',
            '城市分佣',
            '运营分佣',
            '内勤分佣',
            '商家收入',
            '公司分佣百分比',
            '城市分佣百分比',
            '运营分佣百分比',
            '内勤分佣百分比',
            '商家收入百分比',
            '订单状态',
            '结算时间',
            '下单时间',
            '完成时间',
            '退款时间',
            '城市经理',
            '运营经理',
            '内勤经理',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['D'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
