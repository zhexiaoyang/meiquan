<?php

namespace App\Imports;

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
        foreach ($row as $item) {
            $upc = trim($item['商品条码']);
            $price = floatval($item['销售价']);
            $cost = floatval($item['成本价']);

            if (!$upc) {
                continue;
            }

            if ($medicine = Medicine::where('upc', $upc)->first()) {
                $medicine->update([
                    'price' => $price,
                    'guidance_price' => $cost,
                ]);
            } else {
                if ($depot = MedicineDepot::where('upc', $upc)->first()) {
                    $medicine_arr = [
                        'shop_id' => $this->shop_id,
                        'name' => $depot->name,
                        'upc' => $depot->upc,
                        'brand' => $depot->brand,
                        'spec' => $depot->spec,
                        'price' => $price,
                        'guidance_price' => $cost,
                        'depot_id' => $depot->id,
                    ];
                } else {
                    $name = trim($item['商品名称']);
                    $medicine_arr = [
                        'shop_id' => $this->shop_id,
                        'name' => $name,
                        'upc' => $upc,
                        'brand' => '',
                        'spec' => '',
                        'price' => $price,
                        'guidance_price' => $cost,
                        'depot_id' => 0,
                    ];
                }
                Medicine::create($medicine_arr);
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
            '商品名称' => 'required|string',
            '商品条码' => 'required|string',
            '销售价' => 'required|numeric',
            '成本价' => 'required|numeric',
        ];
    }
}
