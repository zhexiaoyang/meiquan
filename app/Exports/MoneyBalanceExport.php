<?php

namespace App\Exports;

use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use App\Models\UserOperateBalance;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MoneyBalanceExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '余额明细.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $user_id = $this->request->user()->id;
        $type = $this->request->get('type', 1);
        $start_date = $this->request->get('start_date', '');
        $end_date = $this->request->get('end_date', '');

        if ($type == 1) {
            $query = UserMoneyBalance::query();
        } else if ($type == 2) {
            $query = UserFrozenBalance::query();
        } else {
            $query = UserOperateBalance::query();
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $query = $query->where("user_id", $user_id)->orderByDesc("id");

        return $query;
    }

    public function map($balance): array
    {
        return [
            $balance->created_at,
            $balance->description,
            $balance->money,
            $balance->after_money
        ];
    }

    public function headings(): array
    {
        return [
            '时间',
            '描述',
            '金额',
            '余额'
        ];
    }

    public function title(): string
    {
        return '余额明细';
    }
}
