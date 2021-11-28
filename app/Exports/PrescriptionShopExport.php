<?php

namespace App\Exports;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PrescriptionShopExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '处方门店列表.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $request = $this->request;


        $query = DB::table('shops')->leftJoin('users', 'shops.own_id', '=', 'users.id')
            ->select('users.id as uid','users.phone','users.operate_money','users.id','shops.id',
                'shops.own_id','shops.shop_name','shops.mtwm','shops.ele','shops.jddj','shops.chufang_status as status')
            ->where('shops.user_id', '>', 0)->where('shops.second_category', '200001');

        if ($status = $request->get('status')) {
            if (in_array($status, [1, 2])) {
                $query->where('shops.chufang_status', $status);
            }
            if ($status == 3) {
                $query->where('shops.chufang_status', '>', 0);
            }
            if ($status == 4) {
                $query->where('shops.chufang_status', 0);
            }
        }
        if ($name = $request->get('name')) {
            $query->where('shops.shop_name', 'like', "%{$name}%");
        }

        if ($phone = $request->get('phone')) {
            $query->where('users.phone', 'like', "%{$phone}%");
        }
        if ($start = $request->get('start')) {
            $query->where('users.operate_money', '>=', $start);
        }
        if ($end = $request->get('end')) {
            $query->where('users.operate_money', '<', $end);
        }

        $order_key = $request->get('order_key');
        $order = $request->get('order');
        if ($order_key && $order) {
            if ($order_key == 'uid') {
                if ($order == 'descend') {
                    $query->orderByDesc('users.id');
                } else {
                    $query->orderBy('users.id');
                }
            }
            if ($order_key == 'operate_money') {
                if ($order == 'descend') {
                    $query->orderByDesc('users.operate_money');
                } else {
                    $query->orderBy('users.operate_money');
                }
            }
        } else {
            $query->orderByDesc('shops.id');
        }

        return $query;
    }

    public function map($shop): array
    {
        return [
            $shop->id,
            $shop->mtwm,
            $shop->ele ? "'" . $shop->ele : '',
            $shop->jddj ? "'" . $shop->jddj : '',
            $shop->shop_name,
            $shop->own->id ?? '',
            $shop->own->phone ?? '',
            $shop->own->money ?? '',
            $shop->status == 1 ? '上线' : '下线',
        ];
    }

    public function headings(): array
    {
        return [
            '门店ID',
            '美团ID',
            '饿了么ID',
            '京东到家ID',
            '门店名称',
            '用户ID',
            '用户手机',
            '处方余额',
            '门店处方状态',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
}
