<?php

namespace App\Exports;

use App\Models\VipProduct;
use App\Models\WmProductSku;
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

class TakeoutProductExport extends DefaultValueBinder implements WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
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
        return WmProductSku::with(['product' => function ($query) {
            $query->select('id', 'name');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_name');
        }])->where('shop_id', $this->request->shop_id);
    }

    public function map($sku): array
    {
        return [
            $sku->id,
            $sku->shop->shop_name ?? '',
            $sku->product->name ?? '',
            $sku->spec,
            $sku->upc,
            $sku->stock,
            $sku->price,
            $sku->cost,
        ];
    }

    public function headings(): array
    {
        return [
            '美全ID',
            '门店名称',
            '商品名称',
            '商品规格',
            '条码',
            '库存',
            '销售价格',
            '成本价格',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['E'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
