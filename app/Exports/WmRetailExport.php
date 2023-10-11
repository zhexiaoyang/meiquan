<?php

namespace App\Exports;

use App\Models\WmRetailSku;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class WmRetailExport extends DefaultValueBinder implements  WithStrictNullComparison, Responsable, FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = '外卖商品导出.xlsx';

    protected $shop_id;
    protected $type;

    public function withRequest($shop_id)
    {
        $this->shop_id = $shop_id;
        return $this;
    }

    public function query()
    {
        $query = WmRetailSku::where('shop_id', $this->shop_id);

        return $query;
    }

    public function map($medicine): array
    {
        return [
            $medicine->id,
            $medicine->name,
            $medicine->spec,
            $medicine->price,
            $medicine->down_price,
            $medicine->guidance_price,
            $medicine->gpm,
            $medicine->down_gpm,
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            '商品名称',
            '规格',
            '线上价格',
            '线下价格',
            '成本价',
            '线上毛利率',
            '线下毛利率',
        ];
    }

    public function title(): string
    {
        return '外卖商品导出';
    }

    public function bindValue(Cell $cell, $value)
    {
        $column = $cell->getColumn();
        if (in_array( $column, ['A'])) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    /**
     * 注册事件
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class  => function(AfterSheet $event) {
                //设置列宽
                $event->sheet->autoSize();
                // //设置区域单元格垂直居中
                // $event->sheet->getDelegate()->getStyle('A1:K1265')->getAlignment()->setVertical('center');
                // //设置区域单元格字体、颜色、背景等，其他设置请查看 applyFromArray 方法，提供了注释
                // $event->sheet->getDelegate()->getStyle('A1:K6')->applyFromArray([
                //     'font' => [
                //         'name' => 'Arial',
                //         'bold' => true,
                //         'italic' => false,
                //         'strikethrough' => false,
                //         'color' => [
                //             'rgb' => '808080'
                //         ]
                //     ],
                //     'fill' => [
                //         'fillType' => 'linear', //线性填充，类似渐变
                //         'rotation' => 45, //渐变角度
                //         'startColor' => [
                //             'rgb' => '000000' //初始颜色
                //         ],
                //         //结束颜色，如果需要单一背景色，请和初始颜色保持一致
                //         'endColor' => [
                //             'argb' => 'FFFFFF'
                //         ]
                //     ]
                // ]);
                // //合并单元格
                // $event->sheet->getDelegate()->mergeCells('A1:B1');
            }
        ];
    }
}
