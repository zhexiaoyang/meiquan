<?php

namespace App\Imports;

use App\Exceptions\InvalidRequestException;
use App\Models\WmRetailSku;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RetailImport implements ToCollection, WithHeadingRow, WithValidation, WithCalculatedFormulas
{
    public $shop_id;

    public function collection(Collection $row)
    {
        $row = $row->toArray();
        // throw new InvalidRequestException($row[0]['条形码'], 422);
        if (count($row) > 5000) {
            throw new InvalidRequestException('商品数量不能超过5000', 422);
        }
        if (count($row) < 1) {
            throw new InvalidRequestException('商品数量为空', 422);
        }

        foreach ($row as $item) {
            // $name = $item['商品名称'] ?? 0;
            $cost = $item['成本价格'] ?? 0;
            $id = $item['ID'] ?? 0;
            // \Log::info("$name|$cost|$id");
            if ($id && $cost) {
                $sku = WmRetailSku::find($id);
                if ($sku->shop_id == $this->shop_id) {
                    $sku->update(['guidance_price' => $cost]);
                }
            }
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
            '成本价' => 'required|numeric',
        ];
    }
}
