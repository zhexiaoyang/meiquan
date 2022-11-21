<?php

namespace App\Exports;

use App\Models\Medicine;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class WmMedicineExport extends DefaultValueBinder implements  WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '外卖药品导出.xlsx';

    protected $shop_id;
    protected $type;

    public function withRequest($shop_id, $type = 1)
    {
        $this->type = (int) $type;
        $this->shop_id = $shop_id;
        return $this;
    }

    public function query()
    {
        $query = Medicine::where('shop_id', $this->shop_id);

        if ($this->type === 2) {
            $query->where(function($query) {
                $query->where('mt_status', 2)->orWhere('ele_status', 2);
            });
        }

        return $query;
    }

    public function map($medicine): array
    {
        $type = [0 => '未同步', 1 => '成功', 2 => '失败'];
        return [
            $medicine->name,
            $medicine->upc,
            $medicine->price,
            $medicine->guidance_price,
            $type[$medicine->mt_status],
            $medicine->mt_status !== 2 ? '' : $medicine->mt_error,
            $type[$medicine->ele_status],
            $medicine->ele_status !== 2 ? '' : $medicine->ele_error,
        ];
    }

    public function headings(): array
    {
        return [
            '商品名称',
            '商品条码',
            '销售价',
            '成本价',
            '美团状态',
            '美团异常',
            '饿了么状态',
            '饿了么异常',
        ];
    }

    public function title(): string
    {
        return '外卖药品导出';
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A', 'B', 'E', 'F', 'G', 'H'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        // if (in_array( $column, ['C', 'D'])) {
        //     $cell->setValueExplicit($value, DataType::TYPE_FORMULA);
        //     return true;
        // }
        return parent::bindValue($cell, $value);
    }
}
