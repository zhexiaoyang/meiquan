<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UserExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '用户导出.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $query = User::with("my_shops")->select("id","name","phone","frozen_money","money");

        return $query;
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->phone,
            $user->money,
            $user->frozen_money,
            $user->my_shops,
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            '用户名',
            '手机号',
            '跑腿余额',
            '商城余额',
            '所有门店',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
}
