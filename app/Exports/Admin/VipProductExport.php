<?php

namespace App\Exports\Admin;

use App\Models\VipProduct;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class VipProductExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '商品信息表.xlsx';

    protected $request;

    public function withRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function query()
    {
        return VipProduct::where('shop_id', $this->request->shop_id);
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->shop_name,
            $product->name,
            $product->spec,
            $product->medicine_no,
            $product->upc,
            $product->price,
            $product->cost,
        ];
    }

    public function headings(): array
    {
        return [
            '美全ID',
            '门店名称',
            '商品名称',
            '商品规格',
            '国药准字号',
            '条码',
            '销售价格',
            '成本价格',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['B','H'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
