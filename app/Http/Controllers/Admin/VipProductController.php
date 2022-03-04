<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\VipProductExport;
use App\Http\Controllers\Controller;
use App\Imports\Admin\VipProductImport;
use App\Models\Shop;
use App\Models\VipProduct;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VipProductController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $query = VipProduct::query();

        if ($shop_id = $request->get('shop_id', '')) {
            $query->where('shop_id',$shop_id);
        }
        if ($name = $request->get('name', '')) {
            $query->where('name','like', "%{$name}%");
        }
        if ($category = $request->get('category', '')) {
            $query->where('category_name','like', "%{$category}%");
        }
        if ($stock = $request->get('stock', '')) {
            if ($stock == 1) {
                $query->where('stock','>',0);
            }
            if ($stock == 2) {
                $query->where('stock',0);
            }
        }
        if ($cost = $request->get('cost', '')) {
            if ($cost == 1) {
                $query->where('cost','>',0);
            }
            if ($cost == 2) {
                $query->where('cost',0);
            }
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }
        $mt = app("minkang");

        $params = [
            'offset' => 0,
            'limit' => 200,
            'app_poi_code' => $shop->mtwm
        ];

        $data = $mt->medicineList($params);
        // return $data;
        $total = $data['extra_info']['total_count'] ?? 0;
        $total_page = ceil($total / 200);

        $products = $data['data'];
        if (!empty($products)) {
            // VipProduct::where('shop_id', $shop->id)->delete();
            // $tmp = [];
            foreach ($products as $product) {
                if (!$_p = VipProduct::where(['shop_id' => $shop->id, 'upc' => $product['upc']])->first()) {
                    // return $_p;
                    $tmp = [
                        'platform_id' => $shop->mtwm,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->shop_name,
                        'app_medicine_code' => $product['app_medicine_code'],
                        'name' => $product['name'],
                        'upc' => $product['upc'],
                        'medicine_no' => $product['medicine_no'] ?? '',
                        'spec' => $product['spec'],
                        'price' => $product['price'],
                        'sequence' => $product['sequence'],
                        'category_name' => $product['category_name'],
                        'stock' => $product['stock'],
                        'ctime' => $product['ctime'],
                        'utime' => $product['utime'],
                        'platform' => 1,
                    ];
                    VipProduct::create($tmp);
                } else {
                    VipProduct::where('id', $_p->id)->update([
                        'app_medicine_code' => $product['app_medicine_code'],
                        'medicine_no' => $product['medicine_no'] ?? '',
                        'price' => $product['price'],
                        'stock' => $product['stock'],
                        'utime' => $product['utime'],
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                }
            }
        }

        for ($i = 1; $i < $total_page; $i++) {
            $params['offset'] = $i * 200;
            $data = $mt->medicineList($params);
            $products = $data['data'];
            if (!empty($products)) {
                // $tmp = [];
                foreach ($products as $product) {
                    if (!$_p = VipProduct::where(['shop_id' => $shop->id, 'upc' => $product['upc']])->first()) {
                        $tmp = [
                            'platform_id' => $shop->mtwm,
                            'shop_id' => $shop->id,
                            'shop_name' => $shop->shop_name,
                            'app_medicine_code' => $product['app_medicine_code'],
                            'name' => $product['name'],
                            'upc' => $product['upc'],
                            'medicine_no' => $product['medicine_no'] ?? '',
                            'spec' => $product['spec'],
                            'price' => $product['price'],
                            'sequence' => $product['sequence'],
                            'category_name' => $product['category_name'],
                            'stock' => $product['stock'],
                            'ctime' => $product['ctime'],
                            'utime' => $product['utime'],
                            'platform' => 1,
                        ];
                        VipProduct::create($tmp);
                    } else {
                        VipProduct::where('id', $_p->id)->update([
                            'app_medicine_code' => $product['app_medicine_code'],
                            'medicine_no' => $product['medicine_no'] ?? '',
                            'price' => $product['price'],
                            'stock' => $product['stock'],
                            'utime' => $product['utime'],
                            'updated_at' => date("Y-m-d H:i:s"),
                        ]);
                    }
                }
            }
        }

        return $this->success();
    }

    public function export(Request $request, VipProductExport $export)
    {
        return $export->withRequest($request);
    }

    public function import(Request $request, VipProductImport $import)
    {
        Excel::import($import, $request->file('file'));
        return back()->with('success', '导入成功');
    }
}
