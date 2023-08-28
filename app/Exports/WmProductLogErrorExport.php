<?php

namespace App\Exports;

use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use App\Models\WmProductLogItem;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WmProductLogErrorExport implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = '同步异常商品.xlsx';

    protected $log_id;

    public function withRequest($log_id)
    {
        $this->log_id = $log_id;
        return $this;
    }

    public function query()
    {
        $query = WmProductLogItem::query()->where('log_id', $this->log_id);

        return $query;
    }

    public function map($log): array
    {
        $type = [1 => '上传成功,有异常', 5 => '上传失败'];
        return [
            $type[$log->type] ?? '',
            $log->name,
            $log->description,
        ];
    }

    public function headings(): array
    {
        return [
            '状态',
            '商品名称',
            '异常信息',
        ];
    }

    public function title(): string
    {
        return '同步异常商品';
    }
}
