<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineDepotCategory;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicineCategoryController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;
        $data = [];
        if (!$shop_id = $request->get('shop_id')) {
            if ($shop = Shop::where('user_id', $user_id)->orderBy('id')->first()) {
                $shop_id = $shop->id;
            } else {
                return $this->error('暂无门店，请先创建门店');
            }
        } else {
            if (!Shop::where('id', $shop_id)->where('user_id', $user_id)->first()) {
                return $this->error('门店错误');
            }
        }

        $categories = MedicineCategory::select('id', 'name', 'pid')->where('shop_id', $shop_id)->orderBy('pid')->orderBy('sort')->get()->toArray();
        $category_ids = [];
        foreach ($categories as $v) {
            $category_ids[] = $v['id'];
        }

        $category_count = DB::table('wm_medicine_category')->select('category_id', DB::raw('count(*) as count'))
            ->whereIn('category_id', $category_ids)->groupBy('category_id')->pluck('count', 'category_id')->toArray();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $number = $category_count[$category['id']] ?? 0;
                if ($category['pid'] === 0) {
                    $data[$category['id']] = $category;
                    $data[$category['id']]['children'] = [];
                    $data[$category['id']]['products_count'] = $number;
                } else {
                    $category['products_count'] = $number;
                    $data[$category['pid']]['products_count'] += $number;
                    $data[$category['pid']]['children'][] = $category;
                }
            }
        }

        return $this->success(['list' => array_values($data), 'count' => Medicine::where('shop_id', $shop_id)->count()]);
    }

    public function sync(Request $request)
    {
        $user_id = $request->user()->id;
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!Shop::where('id', $shop_id)->where('user_id', $user_id)->first()) {
            return $this->error('门店错误');
        }

        $categories = MedicineDepotCategory::orderBy('pid')->orderBy('sort')->get()->toArray();
        if (!empty($categories)) {
            $data = [];
            foreach ($categories as $category) {
                if ($category['pid'] === 0) {
                    $data[$category['id']] = $category;
                    $data[$category['id']]['children'] = [];
                } else {
                    $data[$category['pid']]['children'][] = $category;
                }
            }
            if (!empty($data)) {
                foreach ($data as $v) {
                    $_tmp = [
                        'shop_id' => $shop_id,
                        'pid' => 0,
                        'name' => $v['name'],
                        'sort' => $v['sort'],
                    ];
                    $_c = MedicineCategory::firstOrCreate(['shop_id' => $shop_id, 'name' => $v['name']], $_tmp);
                    if (!empty($v['children'])) {
                        // $insert = [];
                        foreach ($v['children'] as $child) {
                            // $insert[] = [
                            //     'shop_id' => $shop_id,
                            //     'pid' => $_c->id,
                            //     'name' => $child['name'],
                            //     'sort' => $child['sort'],
                            //     'created_at' => $_c->created_at,
                            //     'updated_at' => $_c->updated_at,
                            // ];
                            $_tmp = [
                                'shop_id' => $shop_id,
                                'pid' => $_c->id,
                                'name' => $child['name'],
                                'sort' => $child['sort'],
                            ];
                            MedicineCategory::firstOrCreate(['shop_id' => $shop_id, 'name' => $child['name']], $_tmp);
                        }
                        // if (!empty($insert)) {
                        //     MedicineCategory::insert($insert);
                        // }
                    }
                }
            }
        }

        return $this->success();
    }
}
