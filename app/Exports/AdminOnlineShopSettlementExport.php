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

        $query = OnlineShop::query();

        if ($name) {
            $query->where('name', 'like', "%{$name}%");
        }

        $shops = $query->get();


        $result = [];

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $tmp['name'] = $shop->name ?? '';
                $tmp['city'] = $shop->city ?? '';
                $tmp['address'] = $shop->address ?? '';
                $tmp['contact_name'] = $shop->contact_name ?? '';
                $tmp['contact_phone'] = $shop->contact_phone ?? '';
                $tmp['account_no'] = "'" . $shop->account_no ?? '';
                $tmp['bank_user'] = $shop->bank_user ?? '';
                $tmp['bank_name'] = $shop->bank_name ?? '';

                $result[] = $tmp;
            }
        }

        \Log::info("aaa", $result);

        return $result;
    }

    public function map($shop): array
    {

        return [
            $shop['name'] ?? '',
            $shop['city'] ?? '',
            $shop['address'] ?? '',
            $shop['contact_name'] ?? '',
            $shop['contact_phone'] ?? '',
            $shop['account_no'] ?? '',
            $shop['bank_user'] ?? '',
            $shop['bank_name'] ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            '门店名称',
            '城市',
            '地址',
            '门店联系人',
            '门店联系人电话',
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
