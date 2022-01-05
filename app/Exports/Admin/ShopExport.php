<?php

namespace App\Exports\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\WmPrescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ShopExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '门店导出.xlsx';

    protected $request;

    public function withRequest()
    {
        return $this;
    }

    public function query()
    {
        $manager = User::query()->whereHas('roles', function ($query) {
            $query->where('name', 'city_manager');
        })->where('status', 1)->pluck("id");

        $query = Db::table('shops as a')
            ->select('a.id','shop_name','contact_name','shop_name','mtwm','ele','contact_phone','phone','nickname')
            ->leftJoin('user_has_shops as b', 'a.id', '=','b.shop_id')
            ->leftJoin('users as c',function($join) use ($manager){
                $join->on('b.user_id','=','c.id')->whereIn('c.id', $manager);
            })->orderBy('a.id');

        return $query;
    }

    public function map($shop): array
    {
        return [
            $shop->id,
            $shop->shop_name,
            $shop->contact_name,
            $shop->contact_phone,
            $shop->mtwm,
            $shop->ele,
            $shop->nickname,
        ];
    }

    public function headings(): array
    {
        return [
            '门店ID',
            '门店名称',
            '联系人',
            '电话',
            '美团ID',
            '饿了么ID',
            '城市经理',
        ];
    }

    public function title(): string
    {
        return '门店导出';
    }
}
