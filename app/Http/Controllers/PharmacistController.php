<?php

namespace App\Http\Controllers;

use App\Models\Pharmacist;
use Illuminate\Http\Request;

class PharmacistController extends Controller
{
    public function store(Request $request)
    {
        if (!$name = $request->get('name')) {
            return $this->error('姓名不能为空');
        }
        if (!$phone = $request->get('phone')) {
            return $this->error('电话不能为空');
        }
        if (strlen($phone) !== 11) {
            return $this->error('电话格式不正确');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不能为空');
        }
        Pharmacist::query()->create(compact('name', 'phone', 'shop_id'));

        return $this->success();
    }

    public function destroy(Pharmacist $pharmacist)
    {
        $pharmacist->delete();
        return $this->success();
    }
}
