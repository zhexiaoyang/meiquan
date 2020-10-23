<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierFreight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FreightController extends Controller
{
    public function index()
    {
        $result = [];
        $_data = [];

        $user = Auth::user();

        $data = SupplierFreight::with('city')->where('user_id', $user->id)->get();

        if (!empty($data)) {
            foreach ($data as $v) {
                if (isset($v->city)) {
                    $_data[$v->first_weight][$v->continuation_weight][] = $v->city->title;
                }
            }
        }

        if (!empty($_data)) {
            $i = 1;
            foreach ($_data as $k => $v) {
                if (!empty($v)) {
                    $tmp = [];
                    foreach ($v as $m => $n) {
                        $tmp['first_weight'] = $k;
                        $tmp['continuation_weight'] = $m;
                        $tmp['cities'] = $n;
                        $tmp['id'] = $i++;
                        $result[] = $tmp;
                    }
                }
            }
        }

        return $this->success($result);
    }

    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$first_weight = $request->get('first_weight')) {
            return $this->error('首重价格不能为空');
        }

        if (!$continuation_weight = $request->get('continuation_weight')) {
            return $this->error('续重价格不能为空');
        }

        $result = ['first_weight' => $first_weight, 'continuation_weight' => $continuation_weight, 'city_code' => []];

        $data = SupplierFreight::query()->where(
            ['user_id' => $user->id, 'first_weight' => $first_weight, 'continuation_weight' => $continuation_weight]
        )->pluck('city_code');

        if (!empty($data)) {
            $result['city_code'] = $data->toArray();
        }

        return $this->success($result);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$first_weight = $request->get('first_weight')) {
            return $this->error('首重价格不能为空');
        }

        if (!$continuation_weight = $request->get('continuation_weight')) {
            return $this->error('续重价格不能为空');
        }

        if (!$city_code = $request->get('city_code')) {
            return $this->error('城市编码不能为空');
        }

        $data = [];
        $tmp = ['user_id' => $user->id, 'first_weight' => $first_weight, 'continuation_weight' => $continuation_weight];

        if (!empty($city_code)) {
            foreach ($city_code as $item) {
                $tmp['city_code'] = $item;
                $data[] = $tmp;
            }
        }

        if (!empty($data)) {
            SupplierFreight::query()->where(
                ['first_weight' => $first_weight, 'continuation_weight' => $continuation_weight, 'user_id' => $user->id]
            )->delete();
            SupplierFreight::query()->insert($data);
        }

        return $this->success();
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();

        if (!$first_weight = $request->get('first_weight')) {
            return $this->error('首重价格不能为空');
        }

        if (!$continuation_weight = $request->get('continuation_weight')) {
            return $this->error('续重价格不能为空');
        }

        SupplierFreight::query()->where(
            ['first_weight' => $first_weight, 'continuation_weight' => $continuation_weight, 'user_id' => $user->id]
        )->delete();

        return $this->success();
    }
}
