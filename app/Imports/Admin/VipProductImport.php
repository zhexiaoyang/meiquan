<?php

namespace App\Imports\Admin;

use App\Models\VipProduct;
use Maatwebsite\Excel\Concerns\ToArray;
// use Mavinoo\Batch\Batch;

class VipProductImport implements ToArray
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
                // $tmp = [];
                foreach ($array as $item) {
                    // $tmp[] = [
                    //     'id' => $item[0],
                    //     'cost' => $item[7],
                    // ];
                    VipProduct::where('id', $item[0])->update(['cost' => $item[7]]);
                }
            }
        }
    }
}
