<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineSelectShop;
use App\Models\Shop;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function shops(Request $request)
    {
        $user = $request->user();
        $shops = Shop::select('id', 'shop_name', 'wm_shop_name')->where('second_category', 200001)->where('user_id', $user->id)->get();
        // 选择门店ID
        $select_shop_id = 0;
        if ($select_shops = MedicineSelectShop::where('user_id', $request->user()->id)->first()) {
            $select_shop_id = $select_shops->shop_id;
        }
        // 判断选中门店
        if (!empty($shops)) {
            $shop_ids = $shops->pluck('id')->toArray();
            if (in_array($select_shop_id, $shop_ids)) {
                // 如果选中门店在用户数组里面
                foreach ($shops as $shop) {
                    $shop->checked = $shop->id == $select_shop_id ? 1 : 0;
                }
            } else {
                // 如果选中门店 不在 用户数组里面，默认第一个门店选中
                foreach ($shops as $k => $shop) {
                    $shop->checked = $k == 0 ? 1 : 0;
                }
            }
        }
        foreach ($shops as $shop) {
            if (!$shop->wm_shop_name) {
                $shop->wm_shop_name = $shop->shop_name;
            }
        }
        return $this->success($shops);
    }

    public function index(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$shop = Shop::select('id','user_id')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if ($shop->user_id != $user->id) {
            return $this->error('门店不存在!');
        }
        MedicineSelectShop::updateOrCreate(
            [ 'user_id' => $user->id ],
            [ 'user_id' => $user->id, 'shop_id' => $shop_id ]
        );

        $query = Medicine::select('id','shop_id','name','cover','price','down_price','guidance_price','spec','stock',
            'mt_status','ele_status','online_mt','online_ele','mt_error','ele_error')
            ->where('shop_id', $shop_id);
        if ($search_key = $request->get('search_key')) {
            if (is_numeric($search_key)) {
                $query->where('upc', $search_key);
            } else {
                $query->where('name', $search_key);
            }
        }

        $medicines = $query->orderByDesc('id')->paginate($request->get('page_size', 10));
        if (!empty($medicines)) {
            foreach ($medicines as $medicine) {
                if ($medicine->mt_status == 1) {
                    $medicine->mt_error = '';
                }
                if ($medicine->ele_status == 1) {
                    $medicine->ele_error = '';
                }
                $medicine->price_error = $medicine->price < $medicine->guidance_price ? 1 : 0;
                $medicine->down_price_error = $medicine->down_price < $medicine->guidance_price ? 1 : 0;
            }
        }

        return $this->page($medicines);
    }

    public function statistics(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$shop = Shop::select('id','user_id')->find($shop_id)) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if ($shop->user_id != $user->id) {
            return $this->error('门店不存在!');
        }

        $query = Medicine::select('id','price','down_price','guidance_price','stock','online_mt','online_ele')
            ->where('shop_id', $shop_id);
        if ($search_key = $request->get('search_key')) {
            if (is_numeric($search_key)) {
                $query->where('upc', $search_key);
            } else {
                $query->where('name', $search_key);
            }
        }

        $medicines = $query->get();

        $result = [
            'total' => 0,
            'sell_out' => 0,
            'price_anomaly' => 0,
            'off_shelf' => 0,
        ];
        if (!empty($medicines)) {
            $result['total'] = $medicines->count();
            foreach ($medicines as $medicine) {
                if ($medicine->stock <= 0) {
                    $result['sell_out'] += 1;
                }
                if ($medicine->price < $medicine->guidance_price || $medicine->down_price < $medicine->guidance_price) {
                    $result['price_anomaly'] += 1;
                }
                if ($medicine->online_ele = 0 || $medicine->online_mt = 0) {
                    $result['off_shelf'] += 1;
                }
            }
        }

        return $this->success($result);
    }

    public function update(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('药品ID不能为空');
        }
        if (!$down_price = $request->get('down_price')) {
            return $this->error('线下价格不能为空');
        }
        if (!$price = $request->get('price')) {
            return $this->error('线上价格不能为空');
        }
        if (!$guidance_price = $request->get('guidance_price')) {
            return $this->error('成本价不能为空');
        }
        if (!$medicine = Medicine::find($id)) {
            return $this->error('药品不存在');
        }
        $user = $request->user();
        if (!in_array($medicine->shop_id, $user->shops()->pluck('id')->toArray())) {
            return $this->error('药品不存在!');
        }
        $online_update = $price != $medicine->price;
        $update_data = [
            'price' => $price,
            'down_price' => $down_price,
            'guidance_price' => $guidance_price,
        ];
        $medicine->update($update_data);
        // 更新线上价格
        if ($online_update && $price > 0) {
            $shop = Shop::find($medicine->shop_id);
            if ($medicine->mt_status == 1 && $price > 0) {
                $shop = Shop::find($medicine->shop_id);
                $meituan = null;
                if ($shop->meituan_bind_platform === 4) {
                    $meituan = app('minkang');
                } elseif ($shop->meituan_bind_platform === 31) {
                    $meituan = app('meiquan');
                }
                if ($meituan !== null) {
                    $params = [
                        'app_poi_code' => $shop->waimai_mt,
                        'app_medicine_code' => $medicine->store_id ?: $medicine->upc,
                        'price' => $price,
                        // 'stock' => $stock,
                    ];
                    if ($shop->meituan_bind_platform == 31) {
                        $params['access_token'] = $meituan->getShopToken($shop->waimai_mt);
                    }
                    $meituan->medicineUpdate($params);
                    // $res = $meituan->medicineUpdate($params);
                    // \Log::info('aaa美团', [$res]);
                }
            }
            if ($medicine->ele_status == 1 && $price > 0) {
                if (!$shop) {
                    $shop = Shop::find($medicine->shop_id);
                }
                $ele = app('ele');
                $params = [
                    'shop_id' => $shop->waimai_ele,
                    'custom_sku_id' => $medicine->store_id ?: $medicine->upc,
                    'sale_price' => (int) ($medicine->price * 100),
                    // 'left_num' => $medicine->stock,
                ];
                $ele->skuUpdate($params);
            }
        }

        return $this->success();
    }
}
