<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicineDepot;
use App\Models\MedicineDepotCategory;
use Illuminate\Http\Request;

class DepotMedicineController extends Controller
{
    public function __construct()
    {
        $this->middleware(["permission:admin_depot"]);
    }

    public function index(Request $request)
    {
        $query = MedicineDepot::with(['category' => function ($query) {
            $query->select('id', 'name');
        }])->select('id','name','cover','price','upc','sequence');

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($upc = $request->get('upc')) {
            $query->where('upc', $upc);
        }
        if ($id = $request->get('id')) {
            $query->where('id', $id);
        }
        if ($category_id = $request->get('category_id')) {
            $query->whereHas('category', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        $data =$query->paginate($request->get('page_size', 10));

        return $this->page($data, [],'data');
    }

    public function update(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('请选择药品');
        }
        if (!$category_id1 = $request->get('category1')) {
            return $this->error('请选择一级分类');
        }
        $category_id2 = $request->get('category2');
        if (!$medicine = MedicineDepot::find($id)) {
            return $this->error('药品不存在');
        }
        if (!$category1 = MedicineDepotCategory::find($category_id1)) {
            return $this->error('一级分类不存在');
        }
        if (!$category_id2) {
            if (MedicineDepotCategory::where('pid', $category_id1)->first()) {
                return $this->error('请选择二级分类');
            }
        }
        \DB::table('wm_depot_medicine_category')->where('medicine_id', $id)->delete();
        \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $id, 'category_id' => $category_id2 ?: $category_id1]);

        return $this->success();
    }

    public function update_category(Request $request)
    {
        $ids = $request->get('ids', []);
        if (empty($ids)) {
            return $this->error('请选择商品');
        }
        if (!$type = (int) $request->get('type', 0)) {
            return $this->error('请选择修改方式');
        }
        if (!in_array(1, [1, 2, 3])) {
            return $this->error('请选择修改方式');
        }
        if (!$category = (int) $request->get('category', 0)) {
            return $this->error('请选目标分类');
        }
        if (!MedicineDepotCategory::find($category)) {
            return $this->error('目标分类错误');
        }
        if (MedicineDepotCategory::where('pid', $category)->count() > 0) {
            return $this->error('目标分类不能添加商品');
        }
        if ($type === 1) {
            foreach ($ids as $id) {
                if (MedicineDepot::find($id)) {
                    if (!\DB::table('wm_depot_medicine_category')->where(['medicine_id' => $id, 'category_id' => $category])->first()) {
                        \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $id, 'category_id' => $category]);
                        \DB::table('wm_depot_medicine_category')->where(['medicine_id' => $id, 'category_id' => 215])->delete();
                    }
                }
            }
        } elseif ($type === 2) {
            foreach ($ids as $id) {
                if (MedicineDepot::find($id)) {
                    \DB::table('wm_depot_medicine_category')->where('medicine_id', $id)->delete();
                    \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $id, 'category_id' => $category]);
                }
            }
        } elseif ($type === 3) {
            foreach ($ids as $id) {
                if (MedicineDepot::find($id)) {
                    \DB::table('wm_depot_medicine_category')->where(['medicine_id' => $id, 'category_id' => $category])->delete();
                    if (\DB::table('wm_depot_medicine_category')->where(['medicine_id' => $id])->count() === 0) {
                        \DB::table('wm_depot_medicine_category')->insert(['medicine_id' => $id, 'category_id' => 215]);
                    }
                }
            }
        }
        return $this->success();
    }

    public function delete(Request $request)
    {
        $ids = $request->get('ids', []);
        if (empty($ids)) {
            return $this->error('请选择商品');
        }
        \DB::table('wm_depot_medicine_category')->whereIn('medicine_id', $ids)->delete();
        \DB::table('wm_depot_medicines')->whereIn('id', $ids)->delete();
        return $this->success();
    }
}
