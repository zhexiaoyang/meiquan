<?php

namespace App\Imports;

use App\Models\Medicine;
use App\Models\MedicineDepot;
use Maatwebsite\Excel\Concerns\ToModel;

class MedicineImport implements ToModel
{
    public $shop_id;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    // public function array(array $array)
    // {
    //     // return '234234234';
    //     array_shift($array);
    //     if (!empty($array)) {
    //         if (!empty($array)) {
    //             foreach ($array as $item) {
    //                 $upc = trim($item[1]);
    //                 $price = floatval($item[2]);
    //                 $cost = floatval($item[3]);
    //                 if (MedicineDepot::where('upc', $upc)->first()) {
    //                     $_tmp = [
    //                         ''
    //                     ];
    //                 }
    //             }
    //         }
    //     }
    // }

    public function model(array $row)
    {
        $upc = trim($row[1]);
        $price = floatval($row[2]);
        $cost = floatval($row[3]);
        if ($depot = MedicineDepot::where('upc', $upc)->first()) {
            $medicine = new Medicine([
                'shop_id' => $this->shop_id,
                'name' => $depot->name,
                'category' => $depot->category,
                'second_category' => $depot->second_category,
                'upc' => $depot->upc,
                'brand' => $depot->brand,
                'spec' => $depot->spec,
                'price' => $price,
                'guidance_price' => $cost,
            ]);

            return $medicine;
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
}
