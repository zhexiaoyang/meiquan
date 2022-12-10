<?php

namespace App\Imports;

use App\Exceptions\InvalidRequestException;
use App\Jobs\MedicineImportJob;
use App\Models\Medicine;
use App\Models\MedicineDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MedicineImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $shop_id;

    public function collection(Collection $row)
    {
        $row = $row->toArray();
        // 第一行是说明，去掉
        array_shift($row);
        // throw new InvalidRequestException($row[0]['商品条码'], 422);
        if (count($row) > 5000) {
            throw new InvalidRequestException('药品数量不能超过5000', 422);
        }
        if (count($row) < 1) {
            throw new InvalidRequestException('药品数量为空', 422);
        }


        foreach ($row as $k => $item) {
            $line = $k + 3;
            if (!isset($item['商品条码'])) {
                throw new InvalidRequestException("第{$line}不存在商品条码", 422);
            }
            if (empty($item['商品条码'])) {
                throw new InvalidRequestException("第{$line}商品条码不能为空", 422);
            }
            if (!isset($item['销售价'])) {
                throw new InvalidRequestException("第{$line}不存在销售价", 422);
            }
            if (!isset($item['成本价'])) {
                throw new InvalidRequestException("第{$line}不存在成本价", 422);
            }
            if (!isset($item['库存'])) {
                throw new InvalidRequestException("第{$line}不存在库存", 422);
            }
        }

        foreach ($row as $item) {
            $medicine_data = [
                'name' => trim($item['商品名称']),
                'upc' => trim($item['商品条码']),
                'stock' => trim($item['库存']),
                'price' => trim($item['销售价']),
                'guidance_price' => trim($item['成本价']),
            ];
            MedicineImportJob::dispatch($this->shop_id, $medicine_data);
        }
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function rules(): array
    {
        return [
            '商品名称' => 'required|string',
            '商品条码' => 'required|string',
            '销售价' => 'required|numeric',
            '成本价' => 'required|numeric',
        ];
    }
}