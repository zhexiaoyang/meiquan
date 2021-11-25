<?php

namespace App\Exports;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        $query = Shop::with(['own' => function ($query) {
            $query->select('id', 'phone', 'operate_money as money');
        }])->select('id','own_id','shop_name','mtwm','ele','jddj','chufang_status as status')->where(function ($query) {
            $query->where('chufang_mt', '<>', '')->orWhere('chufang_ele', '<>', '')->orWhere('jddj', '<>', '');
        })->where('status', '>', 0);

        if ($phone = $request->get('phone')) {
            $query->whereHas('own', function ($query) use ($phone) {
                $query->where('phone', 'like', "%{$phone}%");
            });
        }
        if ($start = $request->get('start')) {
            $query->whereHas('own', function ($query) use ($start) {
                $query->where('operate_money', '>=', $start);
            });
        }
        if ($end = $request->get('end')) {
            $query->whereHas('own', function ($query) use ($end) {
                $query->where('operate_money', '<', $end);
            });
        }
        if ($status = $request->get('status')) {
            if (in_array($status, [1, 2])) {
                $query->where('chufang_status', $status);
            }
            if ($status == 3) {
                $query->where('chufang_status', '>', 0);
            }
            if ($status == 4) {
                $query->where('chufang_status', 0);
            }
        }
        if ($name = $request->get('name')) {
            $query->where('shop_name', 'like', "%{$name}%");
        }

        $query->orderByDesc('id');

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
