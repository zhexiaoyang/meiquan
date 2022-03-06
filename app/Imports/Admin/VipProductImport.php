<?php

namespace App\Imports\Admin;

use App\Models\VipProduct;
use Maatwebsite\Excel\Concerns\ToArray;

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
                foreach ($array as $item) {
                    if (is_numeric($item[0])) {
                        VipProduct::where('id', $item[0])->update(['cost' => $item[7], 'updated_at' => date("Y-m-d H:i:s")]);
                    }
                }
            }
        }
    }
}
