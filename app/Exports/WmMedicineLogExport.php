<?php

namespace App\Exports;

use App\Models\Medicine;
use App\Models\MedicineSyncLog;
use App\Models\MedicineSyncLogItem;
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

class WmMedicineLogExport extends DefaultValueBinder implements  WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '药品管理日志导出.xlsx';

    protected $id;

    public function withRequest($id)
    {
        $this->id = $id;
        return $this;
    }

    public function query()
    {
        $query = MedicineSyncLogItem::where('log_id', $this->id);
        return $query;
    }

    public function map($item): array
    {
        return [
            $item->name,
            $item->upc,
            $item->msg ?: '成功',
        ];
    }

    public function headings(): array
    {
        return [
            '商品名称',
            '商品条码',
            '操作结果',
        ];
    }

    public function title(): string
    {
        return '药品管理日志导出';
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A', 'B', 'C'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
