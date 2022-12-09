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
}
