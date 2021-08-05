<?php

namespace App\Exports;

use App\Models\User;
use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
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
        $date1 = $this->request->get("date", date("Y-m-d"));
        $date = date("Y-m-d", strtotime($date1) + 86400);
        $frozen = UserFrozenBalance::query()
            ->where("user_id", $user->id)
            ->where("created_at", "<", $date)->orderByDesc("id")->first();
        $money = UserMoneyBalance::query()
            ->where("user_id", $user->id)
            ->where("created_at", "<", $date)->orderByDesc("id")->first();
        return [
            $user->id,
            $user->name,
            $user->phone,
            $user->money,
            $user->frozen_money,
            $date1,
            $money ? $money->after_money : $user->money,
            $frozen ? $frozen->after_money : $user->frozen_money,
            // $user->my_shops,
            implode(",", Arr::pluck($user->my_shops, 'shop_name')),
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
            '日期',
            '跑腿余额（时间）',
            '商城余额（时间）',
            '所有门店',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
}
