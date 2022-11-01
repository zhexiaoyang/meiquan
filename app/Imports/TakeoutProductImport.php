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
                    if (is_numeric($item[0]) && is_numeric($item[7])) {
                        $id = intval($item[0]);
                        $cost = floatval($item[7]);
                        $res = WmProductSku::where('id', $id)->update(['cost' => $cost]);
                        // \Log::info("ID:{$id},COST:{$cost}", [$res]);
                    }
                }
            }
        }
    }
}
