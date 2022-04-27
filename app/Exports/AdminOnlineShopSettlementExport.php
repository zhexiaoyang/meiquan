<?php

namespace App\Exports;

use App\Models\OnlineShop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AdminOnlineShopSettlementExport implements WithStrictNullComparison, Responsable, FromArray, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '结算信息.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function array(): array
    {

        $name = $this->request->get("name", '');

        $query = OnlineShop::with(['shop' => function ($query) {
            $query->with('manager');
        },'user']);

        // 非管理员只能查看所指定的门店
        if (!$this->request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$this->request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $this->request->user()->shops()->pluck('id'));
        }

        if ($name) {
            $query->where('name', 'like', "%{$name}%");
        }

        $shops = $query->get();


        $result = [];

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $tmp['shop_id'] = $shop->shop_id ?? '';
                $tmp['mtwm'] = $shop->shop->mtwm ?? '';
                $tmp['ele'] = $shop->shop->ele ?? '';
                $tmp['name'] = $shop->name ?? '';
                $tmp['city'] = $shop->city ?? '';
                $tmp['address'] = $shop->address ?? '';
                $tmp['user_name'] = $shop->user->phone ?? '';
                $tmp['user_phone'] = $shop->user->phone ?? '';
                $tmp['contact_name'] = $shop->contact_name ?? '';
                $tmp['contact_phone'] = $shop->contact_phone ?? '';
                $tmp['manager_name'] = $shop->shop->manager->nickname ?? '';
                $tmp['manager_phone'] = $shop->shop->manager->name ?? '';
                $tmp['account_no'] = "'" . $shop->account_no ?? '';
                $tmp['bank_user'] = $shop->bank_user ?? '';
                $tmp['bank_name'] = $shop->bank_name ?? '';

                $result[] = $tmp;
            }
        }

        return $result;
    }

    public function map($shop): array
    {

        return [
            $shop['shop_id'] ?? '',
            $shop['mtwm'] ?? '',
            $shop['ele'] ?? '',
            $shop['name'] ?? '',
            $shop['city'] ?? '',
            $shop['address'] ?? '',
            $shop['user_name'] ?? '',
            $shop['user_phone'] ?? '',
            $shop['contact_name'] ?? '',
            $shop['contact_phone'] ?? '',
            $shop['manager_name'] ?? '',
            $shop['manager_phone'] ?? '',
            $shop['account_no'] ?? '',
            $shop['bank_user'] ?? '',
            $shop['bank_name'] ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            '美全门店ID',
            '美团ID',
            '饿了ID',
            '门店名称',
            '城市',
            '地址',
            '用户名',
            '账号',
            '门店联系人',
            '门店联系人电话',
            '美全负责人',
            '美全负责人电话',
            '打款账号',
            '开户名',
            '开户行',
        ];
    }

    public function title(): string
    {
        return '结算信息';
    }
}
