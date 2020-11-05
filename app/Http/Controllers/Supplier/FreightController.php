<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierFreight;
use App\Models\SupplierFreightCity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FreightController extends Controller
{
    /**
     * 列表
     * @return mixed
     * @author zhangzhen
     * @data 2020/10/30 12:22 下午
     */
    public function index()
    {
        $result = [];

        $user = Auth::user();

        $data = SupplierFreight::with('cities.city')->where("user_id", $user->id)->get();

        if (!empty($data)) {
            foreach ($data as $v) {
                $cities = [];
                if (!empty($v->cities)) {
                    foreach ($v->cities as $city) {
                        if (isset($city->city->title)) {
                            $cities[] = $city->city->title;
                        }
                    }
                }
                $_tmp['id'] = $v->id;
                $_tmp['first_weight'] = $v->first_weight;
                $_tmp['weight1'] = $v->weight1;
                $_tmp['continuation_weight'] = $v->continuation_weight;
                $_tmp['weight2'] = $v->weight2;
                $_tmp['cities'] = $cities;
                $result[] = $_tmp;
            }
        }

        return $this->success($result);
    }

    public function show(Request $request)
    {
        $id = $request->get("id", 0);

        $user = Auth::user();

        if (!$freight = SupplierFreight::with("cities")->where(["user_id" => $user->id, "id" => $id])->first()) {
            return $this->error("配送费不存在");
        }

        $result = [];

        $city_codes = [];

        if (!empty($freight->cities)) {
            foreach ($freight->cities as $city) {
                $city_codes[] = $city->city_code;
            }
        }

        $result['id'] = $freight->id;
        $result['first_weight'] = $freight->first_weight;
        $result['weight1'] = $freight->weight1;
        $result['continuation_weight'] = $freight->continuation_weight;
        $result['weight2'] = $freight->weight2;
        $result['cities'] = $city_codes;

        return $this->success($result);
    }

    /**
     * 添加
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2020/10/30 12:22 下午
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $id = $request->get('id', 0);

        if (!$first_weight = $request->get('first_weight')) {
            return $this->error('首重价格不能为空');
        }

        if (!$weight1 = $request->get('weight1', 0)) {
            return $this->error('首重重量不能小于0');
        }

        if (!$continuation_weight = $request->get('continuation_weight')) {
            return $this->error('续重价格不能为空');
        }

        if (!$city_code = $request->get('city_code')) {
            return $this->error('城市编码不能为空');
        }

        if (!$weight2 = $request->get('weight2', 0)) {
            return $this->error('续重重量不能小于0');
        }

        // 参数
        $tmp = [
            'user_id' => $user->id,
            'first_weight' => $first_weight,
            'continuation_weight' => $continuation_weight,
            'weight1' => $weight1,
            'weight2' => $weight2
        ];

        // 判断是否存在
        if ($id) {
            // 存在-修改
            if (!$freight = SupplierFreight::query()->where(["user_id" => $user->id, "id" => $id])->first()) {
                return $this->error("配送费不存在");
            }

            $freight->update($tmp);

            SupplierFreightCity::query()->where("freight_id", $freight->id)->delete();
        } else {
            // 不存在-添加
            $data = [];

            if (!$freight = SupplierFreight::query()->create($tmp)) {
                return $this->error("添加失败，请稍后再试");
            }
        }

        $tmp['freight_id'] = $freight->id;

        if (!empty($city_code)) {
            foreach ($city_code as $item) {
                $tmp['city_code'] = $item;
                $data[] = $tmp;
            }
        }

        if (!empty($data)) {
            SupplierFreightCity::query()->where(
                ['freight_id' => $freight->id]
            )->delete();
            SupplierFreightCity::query()->insert($data);
        }

        return $this->success();
    }

    /**
     * 删除
     * @param Request $request
     * @return mixed
     * @throws \Exception
     * @author zhangzhen
     * @data 2020/10/30 12:21 下午
     */
    public function destroy(Request $request)
    {
        $id = $request->get("id", 0);

        $user = Auth::user();

        if (!$freight = SupplierFreight::query()->where(["user_id" => $user->id, "id" => $id])->first()) {
            return $this->error("配送费不存在");
        }

        SupplierFreightCity::query()->where("freight_id", $freight->id)->delete();
        $freight->delete();

        return $this->success();
    }
}
