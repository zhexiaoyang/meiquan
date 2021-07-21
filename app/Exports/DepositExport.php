<?php

namespace App\Exports;

use App\Models\Deposit;
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

class DepositExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '充值记录.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        $phone = $this->request->get('phone', '');
        $start_date = $this->request->get('start_date', '');
        $end_date = $this->request->get('end_date', '');
        $type = $this->request->get("type", 1);

        $query = Deposit::with(['user' => function ($query) {
            $query->with(["my_shops" => function ($query) {
                $query->select("id", "own_id", "shop_name");
            }]);
            $query->select("id", "phone", "name");
        }])->select("id", "user_id", "amount", "paid_at", "pay_method", "pay_no", "type", "created_at");

        if ($phone) {
            $query->whereHas("user", function ($query) use ($phone) {
                $query->where('phone', 'like', "%{$phone}%");
            });
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $query->where("type", $type)->where("status", 1)->orderByDesc("id");

        return $query;
    }

    public function map($deposit): array
    {
        return [
            $deposit->id,
            $deposit->user->name ?? '',
            $deposit->user->phone ?? '',
            $deposit->type === 1 ? '跑腿充值' : '商城充值',
            $deposit->amount,
            $deposit->pay_methord === 1 ? '支付宝' : '微信',
            $deposit->pay_no,
            $deposit->paid_at,
            empty($deposit->user->my_shops) ? '' : implode(",", Arr::pluck($deposit->user->my_shops, 'shop_name')),
            empty($deposit->user->my_shops) ? '' : implode(",", Arr::pluck($deposit->user->my_shops, 'id')),
            empty($deposit->user->my_shops) ? '' : implode(",", Arr::pluck($deposit->user->my_shops, 'mt_shop_id')),
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            '用户名',
            '手机号',
            '类型',
            '金额',
            '支付方式',
            '支付单号',
            '支付时间',
            '门店列表',
            '门店ID',
            '美团ID',
        ];
    }

    public function title(): string
    {
        return '订单明细';
    }
}
