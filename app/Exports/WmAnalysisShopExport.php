<?php

namespace App\Exports;

use App\Models\Medicine;
use App\Models\MedicineSyncLog;
use App\Models\MedicineSyncLogItem;
use App\Models\Shop;
use App\Models\User;
use App\Models\WmOrder;
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

class WmAnalysisShopExport extends DefaultValueBinder implements  WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '门店分析导出.xlsx';

    protected $sdate;
    protected $edate;
    protected $shop_id;
    protected $user_id;

    public function withRequest($sdate, $edate, $shop_id, $user_id)
    {
        $this->sdate = $sdate;
        $this->edate = $edate;
        $this->shop_id = $shop_id;
        $this->user_id = $user_id;
        return $this;
    }

    public function query()
    {
        $query = WmOrder::select('id','order_id','platform','wm_shop_name','poi_receive','vip_cost','running_fee','prescription_fee','status','created_at','finish_at','operate_service_fee')
            ->where('status', '<', 30)->where('created_at', '>=', $this->sdate)
            ->where('created_at', '<', date("Y-m-d", strtotime($this->edate) + 86400));
        if ($this->shop_id) {
            $query->where('shop_id', $this->shop_id);
        } else {
            $user = User::find($this->user_id);
            if ($user->hasRole('city_manager')) {
                $shop_ids = $user->shops()->pluck('id');
                // $shops = Shop::select('id', 'shop_name')->whereIn('id', $user->shops()->pluck('id'))->get();
            } else {
                // $shops = Shop::select('id', 'shop_name')->where('user_id', $user_id)->get();
                $shop_ids = Shop::select('id')->where('user_id', $this->user_id)->get()->pluck('id');
            }
            $query->whereIn('shop_id', $shop_ids);
        }
        \Log::info('a');
        return $query;
    }

    public function map($order): array
    {
        \Log::info('aaa');
        $platforms = ['','美团','饿了么','京东到家','美全'];
        $status = [1 => '订单创建成功', 4 => '商家已确认', 7 => '备货完成', 9 => '代发货', 12 => '取货中', 14 => '配送中', 16 => '已收货',
            18 => '已完成', 20 => '已关闭', 30 => '已取消', 40 => '售后中', 45 => '售后完成'];
        // 门店名称  下单平台 下单时间， 订单号，订单状态，美团结算金额，商品成本价总计，跑腿费，处方费，完成订单时间，城市经理，运营经理
        return [
            $platforms[$order->platform],
            $order->wm_shop_name,
            $order->order_id,
            (float) sprintf("%.2f", $order->poi_receive),
            (float) sprintf("%.2f", $order->refund_settle_amount),
            (float) sprintf("%.2f", $order->vip_cost),
            (float) sprintf("%.2f", $order->running_fee),
            (float) sprintf("%.2f", $order->prescription_fee),
            (float) sprintf("%.2f", $order->operate_service_fee),
            (float) sprintf("%.2f", $order->refund_operate_service_fee),
            $status[$order->status],
            $order->created_at,
            $order->finish_at,
        ];
    }

    public function headings(): array
    {
        return [
            '下单平台',
            '门店名称',
            '订单号',
            '美团结算金额',
            '部分退款',
            '商品成本价总计',
            '跑腿费',
            '处方费',
            '代运营',
            '代运营退款',
            '订单状态',
            '下单时间',
            '完成时间',
        ];
    }

    public function title(): string
    {
        return '订单列表';
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A', 'B', 'C'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
