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
        $query = MedicineDepot::select('id','name','cover','price','upc','sequence');

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
}
