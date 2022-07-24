<?php

namespace App\Imports;

use App\Models\VipProduct;
use App\Models\WmProductSku;
use Maatwebsite\Excel\Concerns\ToArray;

class TakeoutProductImport implements ToArray
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function array(array $array)
    {
        array_shift($array);
        if (!empty($array)) {
            if (!empty($array)) {
                foreach ($array as $item) {
                    if (is_numeric($item[0]) && is_numeric($item[6])) {
                        WmProductSku::where('id', $item[0])->update(['cost' => $item[6]]);
                    }
                }
            }
        }
    }
}
