<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\AddressProvince;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function cities()
    {
        $data = AddressProvince::with("children")->select("id","title")->get();

        return $this->success($data);
    }
}
