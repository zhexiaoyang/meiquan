<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicineDepotCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepotMedicineCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(["permission:admin_depot"]);
    }

    public function index(Request $request)
    {
        $data = [];
        $count = 0;

        $categories = MedicineDepotCategory::orderBy('pid')->orderBy('sort')->get()->toArray();
        // select category_id,count(*) as count from wm_medicine_category group by category_id order by category_id asc;
        $category_count = DB::table('wm_depot_medicine_category')->select('category_id', DB::raw('count(*) as count'))
            ->groupBy('category_id')->pluck('count', 'category_id')->toArray();
        // \Log::info('aaa', $category_count);

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $number = $category_count[$category['id']] ?? 0;
                if ($category['pid'] === 0) {
                    $data[$category['id']] = $category;
                    $data[$category['id']]['children'] = [];
                    $data[$category['id']]['products_count'] = $number;
                    $count += $number;
                } else {
                    $count += $number;
                    $category['products_count'] = $number;
                    $data[$category['pid']]['products_count'] += $number;
                    $data[$category['pid']]['children'][] = $category;
                }
            }
        }
        // \Log::info("aaa", $data);

        return $this->success(['list' => array_values($data), 'count' => $count]);
    }

    public function list_one()
    {
        $categories = MedicineDepotCategory::select('id', 'pid', 'name', 'sort')->where('pid', 0)->orderBy('sort')->get();

        return $this->success($categories);
    }

    public function cascader(Request $request)
    {
        $data = [];
        $categories = MedicineDepotCategory::select('id', 'name', 'pid')->orderBy('pid')->orderBy('sort')->get()->toArray();

        if (!empty($categories)) {
            foreach ($categories as $category) {
                if ($category['name'] == '暂未分类') {
                    continue;
                }
                if ($category['pid'] === 0) {
                    $data[$category['id']] = $category;
                    $data[$category['id']]['children'] = [];
                } else {
                    $data[$category['pid']]['children'][] = $category;
                }
            }
        }

        return $this->success(array_values($data));
    }

    public function update(Request $request)
    {

        $id = $request->get('id', 0);
        $pid = $request->get('pid', 0);
        if ($id === 215) {
            return $this->error('该分类不可编辑');
        }
        if (!$sort = $request->get('sort', 0)) {
            return $this->error('分类排序必须大于0');
        }
        if (!$name = $request->get('name')) {
            return $this->error('分类名称不能为空');
        }
        if ($pid) {
            if (!$parent_cat = MedicineDepotCategory::find($pid)) {
                return $this->error('上级分类不存在');
            }
            if (DB::table('wm_depot_medicine_category')->where('category_id', $pid)->count() > 0) {
                return $this->error('一级分类「'. $parent_cat->name.'」下面有商品，不能添加二级分类');
            }
            if ($id && MedicineDepotCategory::where('pid', $id)->count() > 0) {
                return $this->error('该分类下存在二级分类，不能作为二级分类');
            }
        }

        $update_data = [
            'name' => $name,
            'pid' => $pid,
            'sort' => $sort,
        ];

        if ($id) {
            if (!MedicineDepotCategory::find($id)) {
                return $this->error('分类不存在');
            }
            MedicineDepotCategory::where('id', $id)->update($update_data);
        } else {
            MedicineDepotCategory::create($update_data);
        }

        return $this->success($update_data);
    }

    public function delete(Request $request)
    {
        $id = $request->get('id', 0);
        if ($id === 215) {
            return $this->error('该分类不可以删除');
        }
        if (!MedicineDepotCategory::find($id)) {
            return $this->error('分类不存在');
        }
        if (MedicineDepotCategory::where('pid', $id)->count() > 0) {
            return $this->error('该分类下存在二级分类，不能删除');
        }
        if (DB::table('wm_depot_medicine_category')->where('category_id', $id)->count() > 0) {
            return $this->error('该分类下有商品，不能删除');
        }
        MedicineDepotCategory::where('id', $id)->delete();
        return $this->success();
    }
}
